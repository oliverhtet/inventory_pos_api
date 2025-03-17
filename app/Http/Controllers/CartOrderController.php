<?php

namespace App\Http\Controllers;

use Exception;
use Carbon\Carbon;
use App\Models\{Cart,
    ReturnSaleInvoice,
    SaleInvoice,
    Users,
    Coupon,
    Product,
    Customer,
    Discount,
    CartOrder,
    ProductVat,
    CartProduct,
    DeliveryFee,
    Transaction,
    CourierMedium,
    ManualPayment,
    ReturnCartOrder,
    CartOrderProduct,
    CartAttributeValue,
    CartOrderAttributeValue
};
use Illuminate\Http\{Request, JsonResponse};
use Illuminate\Support\Facades\DB;
use App\MailStructure\MailStructure;

class CartOrderController extends Controller
{

    protected MailStructure $MailStructure;

    public function __construct(MailStructure $MailStructure)
    {
        $this->MailStructure = $MailStructure;
    }

    // create a single CartOrder controller method
    public function createSingleCartOrder(Request $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $customer = Customer::where('id', $request->input('customerId'))->first();
            if (!$customer) {
                return response()->json(['error' => 'Customer Not Found!'], 404);
            }
            // is cart exist or not validation
            $cart = Cart::where('id', $request->input('cartId'))->where('customerId', $request->input('customerId'))->first();
            if (!$cart) {
                return response()->json(['error' => 'No Cart Found!'], 404);
            }

            // Get all the cart product
            $allCartProducts = CartProduct::where('cartId', $request->input('cartId'))
                ->with("cartAttributeValue")
                ->get();

            // Get all the product
            $allProducts = collect($allCartProducts)->map(function ($item) {
                $data = Product::where('id', (int)$item['productId'])
                    ->first();

                // vat
                $productVat = 0;
                $productVatId = $data->productVatId ?? null;
                if ($productVatId !== null) {
                    $productVatData = ProductVat::where('id', $productVatId)->where('status', 'true')
                        ->orderBy('id', 'desc')->first();
                    if (!$productVatData) {
                        return response()->json(['error' => 'Invalid product vat Id'], 404);
                    }
                    if ($productVatData) {
                        $productVat = $productVatData->percentage;
                    }
                }

                // discount
                $discountType = null;
                $discount = 0;
                $discountId = $data->discountId ?? null;
                if ($discountId !== null) {
                    $discountData = Discount::where('id', $discountId)->where('status', 'true')->first();
                    if (!$discountData) {
                        return response()->json(['error' => 'Invalid discount Id'], 404);
                    }
                    if ($discountData) {
                        $discountType = $discountData->type;
                        $discount = $discountData->value;
                    }
                }

                $data->productVat = $productVat;
                $data->discountType = $discountType;
                $data->discount = $discount;

                return $data;
            });

            // Calculate the product total price with their VAT and discount
            $productSalePriceWithVatAndDiscount = collect($allCartProducts)->map(function ($item) use ($allProducts) {

                $product = $allProducts->firstWhere('id', $item['productId']);

                $productTotalPrice = (float)$product->productSalePrice * (float)$item['productQuantity'];

                // vat calculation
                $productVat = 0;
                if ($product->productVat !== 0) {
                    $productVat = ($productTotalPrice * $product->productVat) / 100;
                }

                // discount calculation
                $discount = 0;
                if ($product->discount !== 0) {
                    if ($product->discountType === 'percentage') {
                        $discount = ($productTotalPrice * $product->discount) / 100;
                    } else if ($product->discountType === 'flat') {
                        $discount = $product->discount;
                    }
                }

                return ($productTotalPrice + $productVat) - $discount;
            });

            // calculate total sale price
            $totalSalePrice = (float)$productSalePriceWithVatAndDiscount->sum();

            // calculated coupon with sale price
            $couponAmount = 0;
            $couponCode = $request->input('couponCode');
            $couponData = Coupon::where('couponCode', $couponCode)->where('status', 'true')->where('endDate', '>=', Carbon::now())->first();
            if ($couponCode) {
                if ($couponData) {
                    if ($couponData->type === 'flat') {
                        $couponAmount = $couponData->value;
                    } else {
                        $couponAmount = ($totalSalePrice * $couponData->value) / 100;
                    }
                } else {
                    return response()->json(['error' => 'Invalid coupon code'], 404);
                }
            }

            $totalSalePrice = $totalSalePrice - $couponAmount;
            $deliveryFee = 0;
            if ($request->input('deliveryFeeId')) {
                $deliveryFeeData = DeliveryFee::where('id', $request->input('deliveryFeeId'))->first();
                if (!$deliveryFeeData) {
                    return response()->json(['error' => 'Invalid delivery fee Id'], 404);
                }
                $deliveryFee = $deliveryFeeData->deliveryFee;
                $totalSalePrice = $totalSalePrice + $deliveryFee;
            }
            // Check if any product is out of stock
            $filteredProducts = collect($allCartProducts)->filter(function ($item) use ($allProducts) {
                $product = $allProducts->firstWhere('id', $item['productId']);
                return $item['productQuantity'] <= $product->productQuantity;
            });

            if ($filteredProducts->count() !== collect($allCartProducts)->count()) {
                return response()->json(['error' => 'products are out of stock'], 400);
            }

            // calculate total purchase price
            $totalPurchasePrice = 0;
            foreach (collect($allCartProducts) as $item) {
                $product = $allProducts->firstWhere('id', $item['productId']);
                $totalPurchasePrice += (float)$product->productPurchasePrice * (float)$item['productQuantity'];
            }


            $createdCartOrder = CartOrder::create([
                'date' => new Carbon(),
                'totalAmount' => takeUptoThreeDecimal($totalSalePrice),
                'paidAmount' => 0,
                'due' => takeUptoThreeDecimal($totalSalePrice),
                'isPaid' => 'false',
                'profit' => takeUptoThreeDecimal((float)$totalSalePrice - (float)$totalPurchasePrice),
                'couponId' => $couponData->id ?? null,
                'couponAmount' => $couponAmount ? $couponAmount : 0,
                'customerId' => $request->input('customerId'),
                'userId' => 4,
                'deliveryAddress' => $request->input('deliveryAddress') ? $request->input('deliveryAddress') : $customer->address ?? null,
                'customerPhone' => $request->input('customerPhone') ? $request->input('customerPhone') : $customer->phone ?? null,
                'deliveryFeeId' => (int)$request->input('deliveryFeeId'),
                'deliveryFee' => $deliveryFee,
            ]);

            if ($createdCartOrder) {
                foreach (collect($allCartProducts) as $item) {
                    $product = $allProducts->firstWhere('id', $item['productId']);

                    $createdCartOrderProduct = CartOrderProduct::create([
                        'invoiceId' => $createdCartOrder->id,
                        'productId' => (int)$item['productId'],
                        'colorId' => $item['colorId'] ?? null,
                        'productQuantity' => (int)$item['productQuantity'],
                        'productSalePrice' => takeUptoThreeDecimal((float)$product->productSalePrice),
                        'productVat' => $product->productVat ?? 0,
                        'discountType' => $product->discountType ?? null,
                        'discount' => $product->discount ?? 0,
                    ]);

                    if ($createdCartOrderProduct) {
                        if (count($item['cartAttributeValue']) !== 0) {
                            foreach (collect($item['cartAttributeValue']) as $attribute) {
                                CartOrderAttributeValue::create([
                                    'cartOrderProductId' => $createdCartOrderProduct->id,
                                    'productAttributeValueId' => $attribute['productAttributeValueId']
                                ]);
                            }
                        }
                    }
                }
            }

            $data = $request->attributes->get("data");
            if ($request->input('paymentMethodId') === 1) {
                ManualPayment::create([
                    'paymentMethodId' => $request->input('paymentMethodId'),
                    'customerId' => $data['sub'],
                    'cartOrderId' => $createdCartOrder->id,
                    'amount' => takeUptoThreeDecimal($totalSalePrice),
                    'manualTransactionId' => $this->manualTransaction(10),
                ]);
            } else {
                ManualPayment::create([
                    'paymentMethodId' => $request->input('paymentMethodId'),
                    'customerId' => $data['sub'],
                    'cartOrderId' => $createdCartOrder->id,
                    'amount' => takeUptoThreeDecimal($totalSalePrice),
                    'manualTransactionId' => $this->manualTransaction(10),
                    'CustomerAccount' => $request->input('CustomerAccount'),
                    'CustomerTransactionId' => $request->input('CustomerTransactionId'),
                ]);

                //created for coupon code transaction
                if ($createdCartOrder->couponAmount !== 0) {
                    Transaction::create([
                        'date' => new Carbon(),
                        'debitId' => 14,
                        'creditId' => 8,
                        'amount' => takeUptoThreeDecimal($createdCartOrder->couponAmount),
                        'particulars' => "Coupon Code on cart order #{$createdCartOrder->id}",
                        'type' => 'sale',
                        'relatedId' => $createdCartOrder->id,
                    ]);
                }

                // create due transaction
                Transaction::create([
                    'date' => new Carbon(),
                    'debitId' => 4,
                    'creditId' => 8,
                    'amount' => takeUptoThreeDecimal($createdCartOrder->due),
                    'particulars' => "Due on  cart order #{$createdCartOrder->id}",
                    'type' => 'sale',
                    'relatedId' => $createdCartOrder->id,
                ]);
            }

            // deleting cart, cartProduct and cart productAttribute value after creating saleInvoice
            if ($createdCartOrder) {
                $cartProductData = CartProduct::where('cartId', $request->input('cartId'))->get();
                collect($cartProductData)->map(function ($item) {
                    CartAttributeValue::where('cartProductId', $item['id'])->delete();
                });

                CartProduct::where('cartId', $request->input('cartId'))->delete();
                Cart::where('id', $request->input('cartId'))->delete();
            }

            // iterate through all products of this sale invoice and decrease product quantity
            foreach (collect($allCartProducts) as $item) {
                $productId = (int)$item['productId'];
                $productQuantity = (int)$item['productQuantity'];

                Product::where('id', $productId)->update([
                    'productQuantity' => DB::raw("productQuantity - $productQuantity"),
                ]);
            }
            $mailCartOrderData = CartOrder::where('id', $createdCartOrder->id)
                ->with('customer', 'cartOrderProduct', 'cartOrderProduct.product',)
                ->first();

            if ($customer->email) {
                try {
                    $this->MailStructure->OrderPlace($customer->email, $mailCartOrderData->toArray());
                } catch (Exception $err) {
                    $converted = arrayKeysToCamelCase($createdCartOrder->toArray());
                    DB::commit();
                    return response()->json([
                        'createdCartOrder' => $converted,
                    ], 201);
                }
            }

            $converted = arrayKeysToCamelCase($createdCartOrder->toArray());
            DB::commit();
            return response()->json(['createdCartOrder' => $converted], 201);
        } catch (Exception $err) {
            DB::rollBack();
            return response()->json(['error' => $err->getMessage()], 500);
        }
    }

    // create a single reorder CartOrder controller method

    public function manualTransaction($length_of_string): string
    {
        // String of all alphanumeric character
        $str_result = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        return substr(
            str_shuffle($str_result),
            0,
            $length_of_string
        );
    }

    public function createReOrderForReturn(Request $request): JsonResponse
    {
        DB::beginTransaction();
        try {

            $getSingleReturnCartOrder = ReturnCartOrder::where('cartOrderId', $request->input('cartOrderId'))
                ->where('id', $request->input('returnCartOrderId'))
                ->where('returnCartOrderStatus', "RESEND")
                ->where('returnType', 'PRODUCT')
                ->with(
                    'returnCartOrderProduct.product',
                    'returnCartOrderProduct.returnCartOrderAttributeValue',
                    'cartOrder'
                )
                ->first();

            if (!$getSingleReturnCartOrder) {
                return response()->json(['error' => 'No return Cart Order Found!'], 404);
            }

            $manualPaymentData = ManualPayment::where('cartOrderId', $request->input('cartOrderId'))
                ->with('paymentMethod')
                ->first();

            // Get all the cart product
            $allCartProducts = $getSingleReturnCartOrder->returnCartOrderProduct;

            // Get all the product
            $allProducts = collect($allCartProducts)->map(function ($item) {
                return $item['product'];
            });

            // Calculate the product total price with their VAT and discount
            $productSalePriceWithVatAndDiscount = collect($allCartProducts)->map(function ($item) {

                $productTotalPrice = (float)$item['productSalePrice'] * (float)$item['productQuantity'];

                // vat calculation
                $productVat = 0;
                if ($item['productVat'] !== 0) {
                    $productVat = ($productTotalPrice * $item['productVat']) / 100;
                }

                // discount calculation
                $discount = 0;
                if ($item['discount'] !== 0) {
                    if ($item['discountType'] === 'percentage') {
                        $discount = ($productTotalPrice * $item['discount']) / 100;
                    } else if ($item['discountType'] === 'flat') {
                        $discount = $item['discount'];
                    }
                }

                return ($productTotalPrice + $productVat) - $discount;
            });

            // calculate total sale price
            $totalSalePrice = (float)$productSalePriceWithVatAndDiscount->sum();

            // Check if any product is out of stock
            $filteredProducts = collect($allCartProducts)->filter(function ($item) use ($allProducts) {
                $product = $allProducts->firstWhere('id', $item['productId']);
                return $item['productQuantity'] <= $product->productQuantity;
            });

            if ($filteredProducts->count() !== collect($allCartProducts)->count()) {
                return response()->json(['error' => 'products are out of stock'], 400);
            }

            // calculate total purchase price
            $totalPurchasePrice = 0;
            foreach (collect($allCartProducts) as $item) {
                $product = $allProducts->firstWhere('id', $item['productId']);
                $totalPurchasePrice += (float)$product->productPurchasePrice * (float)$item['productQuantity'];
            }

            //get the customer
            $customer = Customer::where('id', $getSingleReturnCartOrder->cartOrder->customerId)->first();

            $createdCartOrder = CartOrder::create([
                'date' => new Carbon(),
                'totalAmount' => takeUptoThreeDecimal($totalSalePrice),
                'paidAmount' => 0,
                'due' => takeUptoThreeDecimal($totalSalePrice),
                'isPaid' => 'false',
                'profit' => takeUptoThreeDecimal((float)$totalSalePrice - (float)$totalPurchasePrice),
                'couponId' => null,
                'couponAmount' => 0,
                'customerId' => $customer->id,
                'userId' => 4,
                'deliveryAddress' => $getSingleReturnCartOrder->cartOrder->deliveryAddress ?? $customer->address,
                'deliveryFeeId' => $getSingleReturnCartOrder->cartOrder->deliveryFeeId ?? null,
                'deliveryFee' => 0,
                'customerPhone' => $getSingleReturnCartOrder->cartOrder->customerPhone ?? $customer->phone,
                'note' => "Previous cart Order Invoice id is: #{$getSingleReturnCartOrder->cartOrderId} and return cart Order Invoice id is: #{$getSingleReturnCartOrder->id}",
                'isReOrdered' => "true",
                'previousCartOrderId' => $getSingleReturnCartOrder->cartOrder->id,
            ]);

            if ($createdCartOrder) {
                foreach (collect($allCartProducts) as $item) {

                    $createdCartOrderProduct = CartOrderProduct::create([
                        'invoiceId' => $createdCartOrder->id,
                        'productId' => (int)$item['productId'],
                        'colorId' => $item['colorId'] ?? null,
                        'productQuantity' => (int)$item['productQuantity'],
                        'productSalePrice' => takeUptoThreeDecimal((float)$item['productSalePrice']),
                        'productVat' => $item['productVat'] ?? 0,
                        'discountType' => $item['discountType'] ?? null,
                        'discount' => $item['discount'] ?? 0,
                    ]);

                    if ($createdCartOrderProduct) {
                        if (count($item['returnCartOrderAttributeValue']) !== 0) {
                            foreach (collect($item['returnCartOrderAttributeValue']) as $attribute) {
                                CartOrderAttributeValue::create([
                                    'cartOrderProductId' => $createdCartOrderProduct->id,
                                    'productAttributeValueId' => $attribute['productAttributeValueId']
                                ]);
                            }
                        }
                    }
                }
            }

            if ($manualPaymentData->paymentMethodId === 1) {
                ManualPayment::create([
                    'paymentMethodId' => $manualPaymentData->paymentMethodId,
                    'customerId' => $manualPaymentData->customerId,
                    'cartOrderId' => $createdCartOrder->id,
                    'amount' => takeUptoThreeDecimal($totalSalePrice),
                    'manualTransactionId' => $this->manualTransaction(10),
                ]);
            } else {
                ManualPayment::create([
                    'paymentMethodId' => $manualPaymentData->paymentMethodId,
                    'customerId' => $manualPaymentData->customerId,
                    'cartOrderId' => $createdCartOrder->id,
                    'amount' => takeUptoThreeDecimal($totalSalePrice),
                    'manualTransactionId' => $this->manualTransaction(10),
                    'CustomerAccount' => $manualPaymentData->CustomerAccount,
                    'CustomerTransactionId' => null,
                    'isVerified' => 'Accept'
                ]);
            }

            // return cart order status changing
            if ($createdCartOrder) {
                ReturnCartOrder::where('cartOrderId', $getSingleReturnCartOrder->cartOrderId)
                    ->where('id', $getSingleReturnCartOrder->id)
                    ->where('returnType', 'PRODUCT')
                    ->where('returnCartOrderStatus', 'RESEND')
                    ->update([
                        'returnCartOrderStatus' => 'RESENDED'
                    ]);
            }

            // iterate through all products of this sale invoice and decrease product quantity
            foreach (collect($allCartProducts) as $item) {
                $productId = (int)$item['productId'];
                $productQuantity = (int)$item['productQuantity'];

                Product::where('id', $productId)->update([
                    'productQuantity' => DB::raw("productQuantity - $productQuantity"),
                ]);
            }

            $converted = arrayKeysToCamelCase($createdCartOrder->toArray());
            DB::commit();
            return response()->json(['createdCartOrder' => $converted], 201);
        } catch (Exception) {
            DB::rollBack();
            return response()->json(['error' => 'An error occurred during create reorder cartOrder for return product. Please try again later.'], 500);
        }
    }

    // get all the CartOrder controller method

    public function getAllCartOrder(Request $request): JsonResponse
    {
        $data = $request->attributes->get("data");
        $userSub = $data['sub'];
        $userRole = $data['role'];

        if ($userRole === 'customer') {
            // check authentication
            $customerFromDB = Customer::where('id', $userSub)->with('role:id,name')->first();
            if ($userSub !== (int)$customerFromDB->id && $userRole !== $customerFromDB->role->name) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            try {
                $pagination = getPagination($request->query());

                $allOrder = SaleInvoice::with('saleInvoiceProduct.product', 'user:id,firstName,lastName,username', 'customer:id,username')
                    ->where('isHold', 'false')
                    ->orderBy('created_at', 'desc')
                    ->when($request->query('salePersonId'), function ($query) use ($request) {
                        return $query->whereIn('userId', explode(',', $request->query('salePersonId')));
                    })
                    ->when($request->query('orderStatus'), function ($query) use ($request) {
                        return $query->whereIn('orderStatus', explode(',', $request->query('orderStatus')));
                    })
                    ->when($request->query('customerId'), function ($query) use ($request) {
                        return $query->whereIn('customerId', explode(',', $request->query('customerId')));
                    })
                    ->when($request->query('startDate') && $request->query('endDate'), function ($query) use ($request) {
                        return $query->where('date', '>=', Carbon::createFromFormat('Y-m-d', $request->query('startDate'))->startOfDay())
                            ->where('date', '<=', Carbon::createFromFormat('Y-m-d', $request->query('endDate'))->endOfDay());
                    })
                    ->skip($pagination['skip'])
                    ->take($pagination['limit'])
                    ->get();

                $saleInvoicesIds = $allOrder->pluck('id')->toArray();
                // modify data to actual data of sale invoice's current value by adjusting with transactions and returns
                $totalAmount = Transaction::where('type', 'sale')
                    ->whereIn('relatedId', $saleInvoicesIds)
                    ->where(function ($query) {
                        $query->where('debitId', 4);
                    })
                    ->get();

                // transaction of the paidAmount
                $totalPaidAmount = Transaction::where('type', 'sale')
                    ->whereIn('relatedId', $saleInvoicesIds)
                    ->where(function ($query) {
                        $query->orWhere('creditId', 4);
                    })
                    ->get();

                // transaction of the total amount
                $totalAmountOfReturn = Transaction::where('type', 'sale_return')
                    ->whereIn('relatedId', $saleInvoicesIds)
                    ->where(function ($query) {
                        $query->where('creditId', 4);
                    })
                    ->get();

                // transaction of the total instant return
                $totalInstantReturnAmount = Transaction::where('type', 'sale_return')
                    ->whereIn('relatedId', $saleInvoicesIds)
                    ->where(function ($query) {
                        $query->where('debitId', 4);
                    })
                    ->get();

                // calculate grand total due amount
                $totalDueAmount = (($totalAmount->sum('amount') - $totalAmountOfReturn->sum('amount')) - $totalPaidAmount->sum('amount')) + $totalInstantReturnAmount->sum('amount');

                // calculate paid amount and due amount of individual sale invoice from transactions and returnSaleInvoice and attach it to saleInvoices
                $allSaleInvoice = $allOrder->map(function ($item) use ($totalAmount, $totalPaidAmount, $totalAmountOfReturn, $totalInstantReturnAmount) {

                    $totalAmount = $totalAmount->filter(function ($trans) use ($item) {
                        return ($trans->relatedId === $item->id && $trans->type === 'sale' && $trans->debitId === 4);
                    })->reduce(function ($acc, $current) {
                        return $acc + $current->amount;
                    }, 0);

                    $totalPaid = $totalPaidAmount->filter(function ($trans) use ($item) {
                        return ($trans->relatedId === $item->id && $trans->type === 'sale' && $trans->creditId === 4);
                    })->reduce(function ($acc, $current) {
                        return $acc + $current->amount;
                    }, 0);

                    $totalReturnAmount = $totalAmountOfReturn->filter(function ($trans) use ($item) {
                        return ($trans->relatedId === $item->id && $trans->type === 'sale_return' && $trans->creditId === 4);
                    })->reduce(function ($acc, $current) {
                        return $acc + $current->amount;
                    }, 0);

                    $instantPaidReturnAmount = $totalInstantReturnAmount->filter(function ($trans) use ($item) {
                        return ($trans->relatedId === $item->id && $trans->type === 'sale_return' && $trans->debitId === 4);
                    })->reduce(function ($acc, $current) {
                        return $acc + $current->amount;
                    }, 0);

                    $totalDueAmount = (($totalAmount - $totalReturnAmount) - $totalPaid) + $instantPaidReturnAmount;


                    $item->paidAmount = $totalPaid;
                    $item->instantPaidReturnAmount = $instantPaidReturnAmount;
                    $item->dueAmount = $totalDueAmount;
                    $item->returnAmount = $totalReturnAmount;
                    return $item;
                });

                $converted = arrayKeysToCamelCase($allSaleInvoice->toArray());

                //rename the saleInvoiceProduct to cartOrderProduct
                $converted = array_map(function ($item) {
                    $item['cartOrderProduct'] = $item['saleInvoiceProduct'];
                    unset($item['saleInvoiceProduct']);
                    unset($item['profit']);
                    unset($item['isHold']);
                    return $item;
                }, $converted);

                //unset the productPurchasePrice from saleInvoiceProduct
                $converted = array_map(function ($item) {
                    $item['cartOrderProduct'] = array_map(function ($product) {
                        unset($product['product']['productPurchasePrice']);
                        unset($product['product']['purchaseInvoiceId']);
                        return $product;
                    }, $item['cartOrderProduct']);
                    return $item;
                }, $converted);


                $totaluomValue = $allSaleInvoice->sum('totaluomValue');
                $totalUnitQuantity = $allSaleInvoice->map(function ($item) {
                    return $item->saleInvoiceProduct->sum('productQuantity');
                })->sum();


                $counted = $allOrder->count();
                return response()->json([
                    'aggregations' => [
                        '_count' => [
                            'id' => $counted,
                        ],
                        '_sum' => [
                            'totalAmount' => $totalAmount->sum('amount'),
                            'paidAmount' => $totalPaidAmount->sum('amount'),
                            'dueAmount' => $totalDueAmount,
                            'totalReturnAmount' => $totalAmountOfReturn->sum('amount'),
                            'instantPaidReturnAmount' => $totalInstantReturnAmount->sum('amount'),
                            'totaluomValue' => $totaluomValue,
                            'totalUnitQuantity' => $totalUnitQuantity,
                        ],
                    ],
                    'getAllCartOrder' => $converted,
                    'totalCartOrder' => $counted,
                ], 200);
            } catch (Exception $err) {
                return Response()->json(['error' => $err->getMessage()], 500);
            }
        } else if ($userRole === 'admin') {
            // check authentication
            $userFromDB = Users::where('id', $userSub)->with('role:id,name')->first();
            if ($userSub !== (int)$userFromDB->id && $userRole !== $userFromDB->role->name) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }
            if ($request->query('query') === 'search') {
                try {
                    $pagination = getPagination($request->query());

                    $allOrder = CartOrder::where('id', $request->query('key'))
                        ->orWhereHas('customer', function ($query) use ($request) {
                            $query->where('username', 'LIKE', '%' . $request->query('key') . '%');
                        })
                        ->with('cartOrderProduct', 'user:id,firstName,lastName,username', 'customer:id,username', 'manualPayment.paymentMethod:id,methodName')
                        ->orderBy('created_at', 'desc')
                        ->skip($pagination['skip'])
                        ->take($pagination['limit'])
                        ->get();

                    $allOrderCount = CartOrder::where('id', $request->query('key'))
                        ->orWhereHas('customer', function ($query) use ($request) {
                            $query->where('username', 'LIKE', '%' . $request->query('key') . '%');
                        })
                        ->with('cartOrderProduct', 'user:id,firstName,lastName,username', 'customer:id,username', 'manualPayment.paymentMethod:id,methodName')
                        ->orderBy('created_at', 'desc')
                        ->count();

                    // calculate paid amount and due amount of individual sale invoice from transactions and returnSaleInvoice and attach it to saleInvoices
                    $allCartOrder = $allOrder->map(function ($item) {
                        $singleReturnCartOrder = ReturnCartOrder::where('cartOrderId', $item['id'])
                            ->with('returnCartOrderProduct.product')
                            ->get();

                        $manualPaymentData = ManualPayment::where('cartOrderId', $item['id'])
                            ->with('paymentMethod')
                            ->first();
                        $subAccountIdForMainTransaction = $manualPaymentData->paymentMethod->subAccountId ?? 1;

                        // get the transaction of the total paid Amount
                        $transactionsOfPaidAmount = Transaction::where('relatedId', $item['id'])
                            ->where('type', 'sale')
                            ->where(function ($query) use ($subAccountIdForMainTransaction) {
                                $query->where('debitId', (int)$subAccountIdForMainTransaction);
                            })
                            ->with('debit:id,name', 'credit:id,name')
                            ->get();

                        // get the transaction of the total return paid Amount
                        $transactionsOfReturnPaidAmount = Transaction::where('relatedId', $item['id'])
                            ->where('type', 'sale_return')
                            ->where(function ($query) use ($subAccountIdForMainTransaction) {
                                $query->orWhere('creditId', (int)$subAccountIdForMainTransaction);
                            })
                            ->with('debit:id,name', 'credit:id,name')
                            ->get();

                        // get the transaction of the discountGiven amount
                        $transactionsOfDiscountAmount = Transaction::where('relatedId', $item['id'])
                            ->where('type', 'sale')
                            ->where('debitId', 14)
                            ->where('creditId', (int)$subAccountIdForMainTransaction)
                            ->with('debit:id,name', 'credit:id,name')
                            ->get();

                        // get the transaction of the return discountGiven amount
                        $transactionsOfReturnDiscountAmount = Transaction::where('relatedId', $item['id'])
                            ->where('type', 'sale_return')
                            ->where('debitId', (int)$subAccountIdForMainTransaction)
                            ->where('creditId', 14)
                            ->with('debit:id,name', 'credit:id,name')
                            ->get();

                        // transactions of vatAmount
                        $transactionsOfVatAmount = Transaction::where('relatedId', $item['id'])
                            ->where('type', 'vat')
                            ->where('debitId', (int)$subAccountIdForMainTransaction)
                            ->where('creditId', 15)
                            ->with('debit:id,name', 'credit:id,name')
                            ->get();

                        // transactions of return vatAmount
                        $transactionsOfReturnVatAmount = Transaction::where('relatedId', $item['id'])
                            ->where('type', 'vat_return')
                            ->where('debitId', 15)
                            ->where('creditId', (int)$subAccountIdForMainTransaction)
                            ->with('debit:id,name', 'credit:id,name')
                            ->get();

                        // transactions of couponAmount
                        $transactionsOfCouponAmount = Transaction::where('relatedId', $item['id'])
                            ->where('type', 'sale')
                            ->where('debitId', 14)
                            ->where('creditId', 8)
                            ->with('debit:id,name', 'credit:id,name')
                            ->get();

                        // transactions of return couponAmount
                        $transactionsOfReturnCouponAmount = Transaction::where('relatedId', $item['id'])
                            ->where('type', 'sale_return')
                            ->where('debitId', 8)
                            ->where('creditId', 14)
                            ->with('debit:id,name', 'credit:id,name')
                            ->get();

                        // transactions of due amount
                        $transactionsOfDueAmount = Transaction::where('relatedId', $item['id'])
                            ->where('type', 'sale')
                            ->where('debitId', 4)
                            ->where('creditId', 8)
                            ->with('debit:id,name', 'credit:id,name')
                            ->get();

                        // transactions of return due amount
                        $transactionsOfReturnDueAmount = Transaction::where('relatedId', $item['id'])
                            ->where('type', 'sale_return')
                            ->where('debitId', 8)
                            ->where('creditId', 4)
                            ->with('debit:id,name', 'credit:id,name')
                            ->get();

                        // sum of total paidAmount
                        $totalPaidAmount = $transactionsOfPaidAmount->sum('amount') ?? 0;

                        // sum of total return paidAmount
                        $totalReturnPaidAmount = $transactionsOfReturnPaidAmount->sum('amount') ?? 0;

                        // sum of total discountGiven amount at the time of make the payment
                        $totalDiscountAmount = $transactionsOfDiscountAmount->sum('amount') ?? 0;

                        // sum of total return discountGiven amount at the time of make the payment
                        $totalReturnDiscountAmount = $transactionsOfReturnDiscountAmount->sum('amount') ?? 0;

                        // sum of total vat amount at the time of make the payment
                        $totalVatAmount = $transactionsOfVatAmount->sum('amount') ?? 0;

                        // sum of total return vat amount at the time of make the payment
                        $totalReturnVatAmount = $transactionsOfReturnVatAmount->sum('amount') ?? 0;

                        // sum of total coupon amount at the time of make the payment
                        $totalCouponAmount = $transactionsOfCouponAmount->sum('amount') ?? 0;

                        // sum of total return coupon amount at the time of make the payment
                        $totalReturnCouponAmount = $transactionsOfReturnCouponAmount->sum('amount') ?? 0;

                        // sum of total coupon amount at the time of make the payment
                        $totalDueAmount = $transactionsOfDueAmount->sum('amount') ?? 0;

                        // sum of total return coupon amount at the time of make the payment
                        $totalReturnDueAmount = $transactionsOfReturnDueAmount->sum('amount') ?? 0;

                        // dueAmount calculation
                        $dueAmount = $totalReturnPaidAmount ? ($totalDueAmount - $totalReturnDueAmount) -
                            ($totalPaidAmount - $totalReturnPaidAmount) : $totalDueAmount -
                            $totalPaidAmount;

                        // calculate total uomValue
                        $totaluomValue = $item['cartOrderProduct']->reduce(function ($acc, $item) {
                            return $acc + (int)$item->product->uomValue * $item->productQuantity;
                        }, 0);

                        // calculate total return uomValue
                        $totalReturnuomValue = count($singleReturnCartOrder) !== 0 ? (collect($singleReturnCartOrder)->map(function ($item) {
                            return $item->returnCartOrderProduct->map(function ($item2) {
                                return (int)$item2->product->uomValue * $item2->productQuantity;
                            });
                        })->flatten())->sum() : 0;
                        $totaluomValue = $totalReturnPaidAmount ? $totaluomValue - $totalReturnuomValue : $totaluomValue;

                        // calculate total quantity
                        $totalCartOrderUnitQuantity = $item->cartOrderProduct->sum('productQuantity');

                        $totalReturnCartOrderUnitQuantity = count($singleReturnCartOrder) !== 0 ? (collect($singleReturnCartOrder)->map(function ($item) {
                            return $item->returnCartOrderProduct->sum('productQuantity');
                        }))->sum() : 0;

                        $totalUnitQuantity = $totalReturnCartOrderUnitQuantity ? $totalCartOrderUnitQuantity - $totalReturnCartOrderUnitQuantity : $totalCartOrderUnitQuantity;

                        $item->totalPaidAmount = $totalPaidAmount - $totalReturnPaidAmount;
                        $item->dueAmount = $dueAmount;
                        $item->totalReturnPaidAmount = $totalReturnPaidAmount ?? 0;
                        $item->discount = $totalDiscountAmount - $totalReturnDiscountAmount;
                        $item->coupon = $totalCouponAmount - $totalReturnCouponAmount;
                        $item->vat = $totalVatAmount - $totalReturnVatAmount;
                        $item->totaluomValue = $totaluomValue;
                        $item->totalUnitQuantity = $totalUnitQuantity;
                        return $item;
                    });


                    $converted = arrayKeysToCamelCase($allCartOrder->toArray());

                    $finalResult = [
                        'aggregations' => [
                            '_count' => [
                                'id' => $allOrderCount,
                            ],
                            '_sum' => [
                                'totalAmount' => takeUptoThreeDecimal($allOrder->sum('totalAmount')),
                                'paidAmount' => takeUptoThreeDecimal($allOrder->sum('totalPaidAmount')),
                                'returnPaidAmount' => takeUptoThreeDecimal($allOrder->sum('totalReturnPaidAmount')),
                                'discount' => takeUptoThreeDecimal($allOrder->sum('discount')),
                                'coupon' => takeUptoThreeDecimal($allOrder->sum('coupon')),
                                'vat' => takeUptoThreeDecimal($allOrder->sum('vat')),
                                'dueAmount' => takeUptoThreeDecimal($allOrder->sum('dueAmount')),
                                'profit' => takeUptoThreeDecimal($allOrder->sum('profit')),
                                'totaluomValue' => $allOrder->sum('totaluomValue'),
                                'totalUnitQuantity' => $allOrder->sum('totalUnitQuantity'),
                            ],
                        ],
                        'getAllCartOrder' => $converted,
                        'totalCartOrder' => $allOrderCount,
                    ];

                    return response()->json($finalResult, 200);
                } catch (Exception $err) {
                    return response()->json(['error' => $err->getMessage()], 500);
                }
            } else if ($request->query()) {
                try {
                    $pagination = getPagination($request->query());
                    $allOrder = CartOrder::with('cartOrderProduct', 'user:id,firstName,lastName,username', 'customer:id,username', 'manualPayment.paymentMethod:id,methodName')
                        ->orderBy('created_at', 'desc')
                        ->when($request->query('orderStatus'), function ($query) use ($request) {
                            return $query->whereIn('orderStatus', explode(',', $request->query('orderStatus')));
                        })
                        ->when($request->query('startDate') && $request->query('endDate'), function ($query) use ($request) {
                            return $query->where('date', '>=', Carbon::createFromFormat('Y-m-d', $request->query('startDate'))->startOfDay())
                                ->where('date', '<=', Carbon::createFromFormat('Y-m-d', $request->query('endDate'))->endOfDay());
                        })
                        ->when($request->query('customerId'), function ($query) use ($request) {
                            return $query->whereIn('customerId', explode(',', $request->query('customerId')));
                        })
                        ->skip($pagination['skip'])
                        ->take($pagination['limit'])
                        ->get();


                    $allOrderCount = CartOrder::orderBy('created_at', 'desc')
                        ->when($request->query('orderStatus'), function ($query) use ($request) {
                            return $query->whereIn('orderStatus', explode(',', $request->query('orderStatus')));
                        })
                        ->when($request->query('startDate') && $request->query('endDate'), function ($query) use ($request) {
                            return $query->where('date', '>=', Carbon::createFromFormat('Y-m-d', $request->query('startDate'))->startOfDay())
                                ->where('date', '<=', Carbon::createFromFormat('Y-m-d', $request->query('endDate'))->endOfDay());
                        })
                        ->when($request->query('customerId'), function ($query) use ($request) {
                            return $query->whereIn('customerId', explode(',', $request->query('customerId')));
                        })
                        ->count();


                    $allCartOrder = $allOrder->map(function ($item) {
                        $singleReturnCartOrder = ReturnCartOrder::where('cartOrderId', $item['id'])
                            ->with('returnCartOrderProduct.product')
                            ->get();

                        $manualPaymentData = ManualPayment::where('cartOrderId', $item['id'])
                            ->with('paymentMethod')
                            ->first();
                        $subAccountIdForMainTransaction = $manualPaymentData->paymentMethod->subAccountId ?? 1;

                        // get the transaction of the total paid Amount
                        $transactionsOfPaidAmount = Transaction::where('relatedId', $item['id'])
                            ->where('type', 'sale')
                            ->where(function ($query) use ($subAccountIdForMainTransaction) {
                                $query->where('debitId', (int)$subAccountIdForMainTransaction);
                            })
                            ->with('debit:id,name', 'credit:id,name')
                            ->get();

                        // get the transaction of the total return paid Amount
                        $transactionsOfReturnPaidAmount = Transaction::where('relatedId', $item['id'])
                            ->where('type', 'sale_return')
                            ->where(function ($query) use ($subAccountIdForMainTransaction) {
                                $query->where('creditId', (int)$subAccountIdForMainTransaction);
                            })
                            ->with('debit:id,name', 'credit:id,name')
                            ->get();

                        // get the transaction of the discountGiven amount
                        $transactionsOfDiscountAmount = Transaction::where('relatedId', $item['id'])
                            ->where('type', 'sale')
                            ->where('debitId', 14)
                            ->where('creditId', (int)$subAccountIdForMainTransaction)
                            ->with('debit:id,name', 'credit:id,name')
                            ->get();

                        // get the transaction of the return discountGiven amount
                        $transactionsOfReturnDiscountAmount = Transaction::where('relatedId', $item['id'])
                            ->where('type', 'sale_return')
                            ->where('debitId', (int)$subAccountIdForMainTransaction)
                            ->where('creditId', 14)
                            ->with('debit:id,name', 'credit:id,name')
                            ->get();

                        // transactions of vatAmount
                        $transactionsOfVatAmount = Transaction::where('relatedId', $item['id'])
                            ->where('type', 'vat')
                            ->where('debitId', (int)$subAccountIdForMainTransaction)
                            ->where('creditId', 15)
                            ->with('debit:id,name', 'credit:id,name')
                            ->get();

                        // transactions of return vatAmount
                        $transactionsOfReturnVatAmount = Transaction::where('relatedId', $item['id'])
                            ->where('type', 'vat_return')
                            ->where('debitId', 15)
                            ->where('creditId', (int)$subAccountIdForMainTransaction)
                            ->with('debit:id,name', 'credit:id,name')
                            ->get();

                        // transactions of couponAmount
                        $transactionsOfCouponAmount = Transaction::where('relatedId', $item['id'])
                            ->where('type', 'sale')
                            ->where('debitId', 14)
                            ->where('creditId', 8)
                            ->with('debit:id,name', 'credit:id,name')
                            ->get();

                        // transactions of return couponAmount
                        $transactionsOfReturnCouponAmount = Transaction::where('relatedId', $item['id'])
                            ->where('type', 'sale_return')
                            ->where('debitId', 8)
                            ->where('creditId', 14)
                            ->with('debit:id,name', 'credit:id,name')
                            ->get();

                        // transactions of due amount
                        $transactionsOfDueAmount = Transaction::where('relatedId', $item['id'])
                            ->where('type', 'sale')
                            ->where('debitId', 4)
                            ->where('creditId', 8)
                            ->with('debit:id,name', 'credit:id,name')
                            ->get();

                        // transactions of return due amount
                        $transactionsOfReturnDueAmount = Transaction::where('relatedId', $item['id'])
                            ->where('type', 'sale_return')
                            ->where('debitId', 8)
                            ->where('creditId', 4)
                            ->with('debit:id,name', 'credit:id,name')
                            ->get();

                        // sum of total paidAmount
                        $totalPaidAmount = $transactionsOfPaidAmount->sum('amount') ?? 0;

                        // sum of total return paidAmount
                        $totalReturnPaidAmount = $transactionsOfReturnPaidAmount->sum('amount') ?? 0;

                        // sum of total discountGiven amount at the time of make the payment
                        $totalDiscountAmount = $transactionsOfDiscountAmount->sum('amount') ?? 0;

                        // sum of total return discountGiven amount at the time of make the payment
                        $totalReturnDiscountAmount = $transactionsOfReturnDiscountAmount->sum('amount') ?? 0;

                        // sum of total vat amount at the time of make the payment
                        $totalVatAmount = $transactionsOfVatAmount->sum('amount') ?? 0;

                        // sum of total return vat amount at the time of make the payment
                        $totalReturnVatAmount = $transactionsOfReturnVatAmount->sum('amount') ?? 0;

                        // sum of total coupon amount at the time of make the payment
                        $totalCouponAmount = $transactionsOfCouponAmount->sum('amount') ?? 0;

                        // sum of total return coupon amount at the time of make the payment
                        $totalReturnCouponAmount = $transactionsOfReturnCouponAmount->sum('amount') ?? 0;

                        // sum of total coupon amount at the time of make the payment
                        $totalDueAmount = $transactionsOfDueAmount->sum('amount') ?? 0;

                        // sum of total return coupon amount at the time of make the payment
                        $totalReturnDueAmount = $transactionsOfReturnDueAmount->sum('amount') ?? 0;

                        // dueAmount calculation
                        $dueAmount = $totalReturnPaidAmount ? ($totalDueAmount - $totalReturnDueAmount) -
                            ($totalPaidAmount - $totalReturnPaidAmount) : $totalDueAmount -
                            $totalPaidAmount;

                        // calculate total uomValue
                        $totaluomValue = $item['cartOrderProduct']->reduce(function ($acc, $item) {
                            return $acc + (int)$item->product->uomValue * $item->productQuantity;
                        }, 0);
                        // calculate total return uomValue
                        $totalReturnuomValue = count($singleReturnCartOrder) !== 0 ? (collect($singleReturnCartOrder)->map(function ($item) {
                            return $item->returnCartOrderProduct->map(function ($item2) {
                                return (int)$item2->product->uomValue * $item2->productQuantity;
                            });
                        })->flatten())->sum() : 0;
                        $totaluomValue = $totalReturnPaidAmount ? $totaluomValue - $totalReturnuomValue : $totaluomValue;

                        // calculate total quantity
                        $totalCartOrderUnitQuantity = $item->cartOrderProduct->sum('productQuantity');

                        $totalReturnCartOrderUnitQuantity = count($singleReturnCartOrder) !== 0 ? (collect($singleReturnCartOrder)->map(function ($item) {
                            return $item->returnCartOrderProduct->sum('productQuantity');
                        }))->sum() : 0;

                        $totalUnitQuantity = $totalReturnCartOrderUnitQuantity ? $totalCartOrderUnitQuantity - $totalReturnCartOrderUnitQuantity : $totalCartOrderUnitQuantity;

                        $item->totalPaidAmount = $totalPaidAmount - $totalReturnPaidAmount;
                        $item->dueAmount = $dueAmount;
                        $item->totalReturnPaidAmount = $totalReturnPaidAmount ?? 0;
                        $item->discount = $totalDiscountAmount - $totalReturnDiscountAmount;
                        $item->coupon = $totalCouponAmount - $totalReturnCouponAmount;
                        $item->vat = $totalVatAmount - $totalReturnVatAmount;
                        $item->totaluomValue = $totaluomValue;
                        $item->totalUnitQuantity = $totalUnitQuantity;
                        return $item;
                    });


                    $converted = arrayKeysToCamelCase($allCartOrder->toArray());

                    $finalResult = [
                        'aggregations' => [
                            '_count' => [
                                'id' => $allOrderCount,
                            ],
                            '_sum' => [
                                'totalAmount' => takeUptoThreeDecimal($allOrder->sum('totalAmount')),
                                'paidAmount' => takeUptoThreeDecimal($allOrder->sum('totalPaidAmount')),
                                'returnPaidAmount' => takeUptoThreeDecimal($allOrder->sum('totalReturnPaidAmount')),
                                'discount' => takeUptoThreeDecimal($allOrder->sum('discount')),
                                'coupon' => takeUptoThreeDecimal($allOrder->sum('coupon')),
                                'vat' => takeUptoThreeDecimal($allOrder->sum('vat')),
                                'dueAmount' => takeUptoThreeDecimal($allOrder->sum('dueAmount')),
                                'profit' => takeUptoThreeDecimal($allOrder->sum('profit')),
                                'totaluomValue' => $allOrder->sum('totaluomValue'),
                                'totalUnitQuantity' => $allOrder->sum('totalUnitQuantity'),
                            ],
                        ],
                        'getAllCartOrder' => $converted,
                        'totalCartOrder' => $allOrderCount,
                    ];

                    return response()->json($finalResult, 200);
                } catch (Exception $err) {
                    echo $err;
                    return response()->json(['error' => $err->getMessage()], 500);
                }
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    // get a single CartOrder controller method
    public function getSingleCartOrder(Request $request, $id): JsonResponse
    {
        // for validation system implement here
        $data = $request->attributes->get("data");
        $userSub = $data['sub'];
        $userRole = $data['role'];

        if ($userRole === 'customer') {
            try {
                $singleSaleInvoice = SaleInvoice::where('id', $id)
                    ->with(['saleInvoiceProduct', 'saleInvoiceProduct' => function ($query) {
                        $query->with('product')->orderBy('id', 'desc');
                    }, 'customer:id,username,address,phone,email', 'user:id,firstName,lastName,username'])
                    ->where('isHold', 'false')
                    ->first();

                if (!$singleSaleInvoice) {
                    return response()->json(['error' => 'This invoice not Found'], 400);
                }


                // transaction of the total amount
                $totalAmount = Transaction::where('type', 'sale')
                    ->where('relatedId', $id)
                    ->where(function ($query) {
                        $query->where('debitId', 4);
                    })
                    ->with('debit:id,name', 'credit:id,name')
                    ->get();

                // transaction of the paidAmount
                $totalPaidAmount = Transaction::where('type', 'sale')
                    ->where('relatedId', $id)
                    ->where(function ($query) {
                        $query->orWhere('creditId', 4);
                    })
                    ->with('debit:id,name', 'credit:id,name')
                    ->get();

                // transaction of the total amount
                $totalAmountOfReturn = Transaction::where('type', 'sale_return')
                    ->where('relatedId', $id)
                    ->where(function ($query) {
                        $query->where('creditId', 4);
                    })
                    ->with('debit:id,name', 'credit:id,name')
                    ->get();

                // transaction of the total instant return
                $totalInstantReturnAmount = Transaction::where('type', 'sale_return')
                    ->where('relatedId', $id)
                    ->where(function ($query) {
                        $query->where('debitId', 4);
                    })
                    ->with('debit:id,name', 'credit:id,name')
                    ->get();

                // calculation of due amount
                $totalDueAmount = (($totalAmount->sum('amount') - $totalAmountOfReturn->sum('amount')) - $totalPaidAmount->sum('amount')) + $totalInstantReturnAmount->sum('amount');

                // get all transactions related to this sale invoice
                $transactions = Transaction::where('relatedId', $id)
                    ->where(function ($query) {
                        $query->orWhere('type', 'sale')
                            ->orWhere('type', 'sale_return');
                    })
                    ->with('debit:id,name', 'credit:id,name')
                    ->orderBy('id', 'desc')
                    ->get();

                // get totalReturnAmount of saleInvoice
                $returnSaleInvoice = ReturnSaleInvoice::where('saleInvoiceId', $id)
                    ->with('returnSaleInvoiceProduct', 'returnSaleInvoiceProduct.product')
                    ->orderBy('id', 'desc')
                    ->get();

                $status = 'UNPAID';
                if ($totalDueAmount <= 0.0) {
                    $status = "PAID";
                }

                // calculate total uomValue
                $totaluomValue = $singleSaleInvoice->saleInvoiceProduct->reduce(function ($acc, $item) {
                    return $acc + (int)$item->product->uomValue * $item->productQuantity;
                }, 0);


                $convertedSingleSaleInvoice = arrayKeysToCamelCase($singleSaleInvoice->toArray());
                $convertedReturnSaleInvoice = arrayKeysToCamelCase($returnSaleInvoice->toArray());
                $convertedTransactions = arrayKeysToCamelCase($transactions->toArray());
                //rename the saleInvoiceProduct to cartOrderProduct
                $convertedSingleSaleInvoice['cartOrderProduct'] = $convertedSingleSaleInvoice['saleInvoiceProduct'];
                unset($convertedSingleSaleInvoice['saleInvoiceProduct']);

                unset($convertedSingleSaleInvoice['profit']);
                unset($convertedSingleSaleInvoice['isHold']);

                //unset the productPurchasePrice from saleInvoiceProduct
                $convertedSingleSaleInvoice['cartOrderProduct'] = array_map(function ($product) {
                    unset($product['product']['productPurchasePrice']);
                    unset($product['product']['purchaseInvoiceId']);
                    return $product;
                }, $convertedSingleSaleInvoice['cartOrderProduct']);

                //unset the profit from saleInvoiceProduct
                $convertedSingleSaleInvoice['cartOrderProduct'] = array_map(function ($product) {
                    unset($product['profit']);
                    return $product;
                }, $convertedSingleSaleInvoice['cartOrderProduct']);

                $finalResult = [
                    'status' => $status,
                    'totalAmount' => takeUptoThreeDecimal($totalAmount->sum('amount')),
                    'totalPaidAmount' => takeUptoThreeDecimal($totalPaidAmount->sum('amount')),
                    'totalReturnAmount' => takeUptoThreeDecimal($totalAmountOfReturn->sum('amount')),
                    'instantPaidReturnAmount' => takeUptoThreeDecimal($totalInstantReturnAmount->sum('amount')),
                    'dueAmount' => takeUptoThreeDecimal($totalDueAmount),
                    'totaluomValue' => $totaluomValue,
                    'singleCartOrder' => $convertedSingleSaleInvoice,
                    'returnCartOrder' => $convertedReturnSaleInvoice,
                ];

                return response()->json($finalResult, 200);
            } catch (Exception $err) {
                return response()->json(['error' => $err->getMessage()], 500);
            }
        } else if ($userRole === 'admin') {

            // check authentication
            $userFromDB = Users::where('id', $userSub)->with('role:id,name')->first();
            if ($userSub !== (int)$userFromDB->id && $userRole !== $userFromDB->role->name) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }
            try {
                // get single Sale invoice information with products
                $singleCartOrder = CartOrder::where('id', $id)
                    ->with(
                        'cartOrderProduct',
                        'cartOrderProduct.product',
                        'cartOrderProduct.colors',
                        'cartOrderProduct.cartOrderAttributeValue.productAttributeValue.productAttribute',
                        'customer:id,username,address,phone,email',
                        'user:id,firstName,lastName,username',
                        'manualPayment.paymentMethod:id,methodName,subAccountId',
                        'courierMedium',
                        'deliveryFee',
                        'previousCartOrder'
                    )
                    ->first();

                if (!$singleCartOrder) {
                    return response()->json(['error' => 'Invoice not Found!'], 404);
                }

                $manualPaymentData = ManualPayment::where('cartOrderId', $id)
                    ->with('paymentMethod')
                    ->first();
                $subAccountIdForMainTransaction = $manualPaymentData->paymentMethod->subAccountId ?? 1;

                $singleReturnCartOrder = ReturnCartOrder::where('cartOrderId', $singleCartOrder->id)
                    ->with('returnCartOrderProduct.product')
                    ->get();

                // get all transactions related to this cart order
                $transactions = Transaction::where('relatedId', $id)
                    ->where(function ($query) {
                        $query->orWhere('type', 'sale')
                            ->orWhere('type', 'sale_return')
                            ->orWhere('type', 'vat')
                            ->orWhere('type', 'vat_return');
                    })
                    ->with('debit:id,name', 'credit:id,name')
                    ->get();

                // get the transaction of the total paid Amount
                $transactionsOfPaidAmount = Transaction::where('relatedId', $id)
                    ->where('type', 'sale')
                    ->where(function ($query) use ($subAccountIdForMainTransaction) {
                        $query->where('debitId', (int)$subAccountIdForMainTransaction);
                    })
                    ->with('debit:id,name', 'credit:id,name')
                    ->get();

                // get the transaction of the total return paid Amount
                $transactionsOfReturnPaidAmount = Transaction::where('relatedId', $id)
                    ->where('type', 'sale_return')
                    ->where(function ($query) use ($subAccountIdForMainTransaction) {
                        $query->where('creditId', (int)$subAccountIdForMainTransaction);
                    })
                    ->with('debit:id,name', 'credit:id,name')
                    ->get();

                // get the transaction of the discountGiven amount
                $transactionsOfDiscountAmount = Transaction::where('relatedId', $id)
                    ->where('type', 'sale')
                    ->where('debitId', 14)
                    ->where('creditId', (int)$subAccountIdForMainTransaction)
                    ->with('debit:id,name', 'credit:id,name')
                    ->get();

                // get the transaction of the return discountGiven amount
                $transactionsOfReturnDiscountAmount = Transaction::where('relatedId', $id)
                    ->where('type', 'sale_return')
                    ->where('debitId', (int)$subAccountIdForMainTransaction)
                    ->where('creditId', 14)
                    ->with('debit:id,name', 'credit:id,name')
                    ->get();

                // transactions of vatAmount
                $transactionsOfVatAmount = Transaction::where('relatedId', $id)
                    ->where('type', 'vat')
                    ->where('debitId', (int)$subAccountIdForMainTransaction)
                    ->where('creditId', 15)
                    ->with('debit:id,name', 'credit:id,name')
                    ->get();

                // transactions of return vatAmount
                $transactionsOfReturnVatAmount = Transaction::where('relatedId', $id)
                    ->where('type', 'vat_return')
                    ->where('debitId', 15)
                    ->where('creditId', (int)$subAccountIdForMainTransaction)
                    ->with('debit:id,name', 'credit:id,name')
                    ->get();

                // transactions of couponAmount
                $transactionsOfCouponAmount = Transaction::where('relatedId', $id)
                    ->where('type', 'sale')
                    ->where('debitId', 14)
                    ->where('creditId', 8)
                    ->with('debit:id,name', 'credit:id,name')
                    ->get();

                // transactions of return couponAmount
                $transactionsOfReturnCouponAmount = Transaction::where('relatedId', $id)
                    ->where('type', 'sale_return')
                    ->where('debitId', 8)
                    ->where('creditId', 14)
                    ->with('debit:id,name', 'credit:id,name')
                    ->get();

                // transactions of due amount
                $transactionsOfDueAmount = Transaction::where('relatedId', $id)
                    ->where('type', 'sale')
                    ->where('debitId', 4)
                    ->where('creditId', 8)
                    ->with('debit:id,name', 'credit:id,name')
                    ->get();

                // transactions of return due amount
                $transactionsOfReturnDueAmount = Transaction::where('relatedId', $id)
                    ->where('type', 'sale_return')
                    ->where('debitId', 8)
                    ->where('creditId', 4)
                    ->with('debit:id,name', 'credit:id,name')
                    ->get();

                // sum of total paidAmount
                $totalPaidAmount = $transactionsOfPaidAmount->sum('amount') ?? 0;

                // sum of total return paidAmount
                $totalReturnPaidAmount = $transactionsOfReturnPaidAmount->sum('amount') ?? 0;

                // sum of total discountGiven amount at the time of make the payment
                $totalDiscountAmount = $transactionsOfDiscountAmount->sum('amount') ?? 0;

                // sum of total return discountGiven amount at the time of make the payment
                $totalReturnDiscountAmount = $transactionsOfReturnDiscountAmount->sum('amount') ?? 0;

                // sum of total vat amount at the time of make the payment
                $totalVatAmount = $transactionsOfVatAmount->sum('amount') ?? 0;

                // sum of total return vat amount at the time of make the payment
                $totalReturnVatAmount = $transactionsOfReturnVatAmount->sum('amount') ?? 0;

                // sum of total coupon amount at the time of make the payment
                $totalCouponAmount = $transactionsOfCouponAmount->sum('amount') ?? 0;

                // sum of total return coupon amount at the time of make the payment
                $totalReturnCouponAmount = $transactionsOfReturnCouponAmount->sum('amount') ?? 0;

                // sum of total coupon amount at the time of make the payment
                $totalDueAmount = $transactionsOfDueAmount->sum('amount') ?? 0;

                // sum of total return coupon amount at the time of make the payment
                $totalReturnDueAmount = $transactionsOfReturnDueAmount->sum('amount') ?? 0;


                if ($singleCartOrder->totalAmount === 'undefined' || null) {
                    return response()->json(['message' => 'This invoice is not valid'], 400);
                }

                $dueAmount = $totalReturnPaidAmount ? ($totalDueAmount - $totalReturnDueAmount) -
                    ($totalPaidAmount - $totalReturnPaidAmount) : $totalDueAmount -
                    $totalPaidAmount;


                if ($singleCartOrder->orderStatus === 'PENDING') {
                    $dueAmount = $singleCartOrder->due;
                }
                $status = 'UNPAID';
                if ($dueAmount <= 0.0) {
                    $status = "PAID";
                }

                // calculate total uomValue
                $totaluomValue = $singleCartOrder->cartOrderProduct->reduce(function ($acc, $item) {
                    return $acc + (int)$item->product->uomValue * $item->productQuantity;
                }, 0);

                // calculate total return uomValue
                $totalReturnuomValue = count($singleReturnCartOrder) !== 0 ? (collect($singleReturnCartOrder)->map(function ($item) {
                    return $item->returnCartOrderProduct->map(function ($item2) {
                        return (int)$item2->product->uomValue * $item2->productQuantity;
                    });
                })->flatten())->sum() : 0;

                $totaluomValue = $totalReturnPaidAmount ? $totaluomValue - $totalReturnuomValue : $totaluomValue;


                $convertedSingleCartOrder = arrayKeysToCamelCase($singleCartOrder->toArray());
                $convertedSingleReturnCartOrder = count($singleReturnCartOrder) !== 0 ? arrayKeysToCamelCase($singleReturnCartOrder->toArray()) : [];
                $convertedTransactions = arrayKeysToCamelCase($transactions->toArray());

                // concat the productSalePrice and vat amount
                foreach ($convertedSingleCartOrder['cartOrderProduct'] as $key => $value) {
                    $productVat = $value['productVat'];
                    $productSalePrice = $value['productSalePrice'];
                    $salePriceWithVat = $productSalePrice + (($productSalePrice * $productVat) / 100);

                    $convertedSingleCartOrder['cartOrderProduct'][$key]['product']['productSalePriceWithVat'] = $salePriceWithVat;
                }

                $finalResult = [
                    'status' => $status,
                    'totalAmount' => takeUptoThreeDecimal($singleCartOrder->totalAmount),
                    'totalPaidAmount' => takeUptoThreeDecimal($totalPaidAmount - $totalReturnPaidAmount),
                    'totalReturnPaidAmount' => takeUptoThreeDecimal($totalReturnPaidAmount) ?? 0,
                    'dueAmount' => takeUptoThreeDecimal($dueAmount),
                    'totalVatAmount' => takeUptoThreeDecimal($totalVatAmount - $totalReturnVatAmount),
                    'totalDiscountAmount' => takeUptoThreeDecimal($totalDiscountAmount - $totalReturnDiscountAmount),
                    'totalCouponAmount' => takeUptoThreeDecimal($totalCouponAmount - $totalReturnCouponAmount),
                    'deliveryFee' => takeUptoThreeDecimal($singleCartOrder->deliveryFee) ?? 0,
                    'totaluomValue' => $totaluomValue,
                    'singleCartOrder' => $convertedSingleCartOrder,
                    'returnSingleCartOrder' => $convertedSingleReturnCartOrder ?? [],
                    'transactions' => $convertedTransactions,
                ];

                return response()->json($finalResult, 200);
            } catch (Exception $err) {
                return response()->json(['error' => $err->getMessage()], 500);
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    // update cartOrder controller method
    public function updateCartOrderStatus(Request $request): JsonResponse
    {
        DB::beginTransaction();
        try {

            // get the cart order
            $cartOrderData = CartOrder::where('id', $request->input('invoiceId'))->first();

            if (!$cartOrderData) {
                return response()->json(['error' => 'No cart order Invoice Found!'], 404);
            }

            // Get all the cart order products
            $cartOrderProducts = CartOrderProduct::where('invoiceId', $request->input('invoiceId'))
                ->get();

            // Get all the sale products
            $allCartProducts = collect($cartOrderProducts)->map(function ($item) {

                $data = Product::where('id', (int)$item['productId'])
                    ->first();
                if (!$data) {
                    return response()->json([
                        'error' => 'Product not Found!'
                    ]);
                }

                // vat
                $productVat = 0;
                $productVatId = $data->productVatId ?? null;
                if ($productVatId !== null) {
                    $productVatData = ProductVat::where('id', $productVatId)->where('status', 'true')
                        ->orderBy('id', 'desc')->first();
                    if (!$productVatData) {
                        return response()->json(['error' => 'Invalid product vat Id'], 404);
                    }
                    if ($productVatData) {
                        $productVat = $productVatData->percentage;
                    }
                }

                // discount
                $discountType = null;
                $discount = 0;
                $discountId = $data->discountId ?? null;
                if ($discountId !== null) {
                    $discountData = Discount::where('id', $discountId)->where('status', 'true')->first();
                    if (!$discountData) {
                        return response()->json(['error' => 'Invalid discount Id'], 404);
                    }
                    if ($discountData) {
                        $discountType = $discountData->type;
                        $discount = $discountData->value;
                    }
                }

                $data->productVat = $productVat;
                $data->discountType = $discountType;
                $data->discount = $discount;
                return $data;
            });

            // calculate total purchase price
            $totalPurchasePrice = 0;
            foreach (collect($cartOrderProducts) as $item) {
                $product = $allCartProducts->firstWhere('id', $item['productId']);
                $totalPurchasePrice += (float)$product->productPurchasePrice * (float)$item['productQuantity'];
            }

            // Calculate all the discount of cart products
            $discountArrayOfCart = collect($cartOrderProducts)->map(function ($item) use ($allCartProducts) {

                $product = $allCartProducts->firstWhere('id', $item['productId']);

                $productTotalPrice = (float)$product->productSalePrice * (float)$item['productQuantity'];

                // discount calculation
                $discount = 0;

                if ($product->discount !== 0) {
                    if ($product->discountType === 'percentage') {
                        $discount = ($productTotalPrice * $product->discount) / 100;
                    } else if ($product->discountType === 'flat') {
                        $discount = $product->discount;
                    }
                }
                return (float)$discount;
            });

            // Calculate all the vat of cart products
            $vatArrayOfCart = collect($cartOrderProducts)->map(function ($item) use ($allCartProducts) {

                $product = $allCartProducts->firstWhere('id', $item['productId']);

                $productTotalPrice = (float)$product->productSalePrice * (float)$item['productQuantity'];

                // vat calculation
                $productVat = 0;

                if ($product->productVat !== 0) {
                    $productVat = ($productTotalPrice * $product->productVat) / 100;
                }
                return (float)$productVat;
            });

            $totalDiscount = $discountArrayOfCart->sum() ?? 0;
            $totalVat = $vatArrayOfCart->sum() ?? 0;

            $manualPaymentData = ManualPayment::where('cartOrderId', $request->input('invoiceId'))
                ->with('paymentMethod')
                ->first();
            $subAccountIdForMainTransaction = $manualPaymentData->paymentMethod->subAccountId ?? 1;

            if ($request->input('orderStatus') === 'RECEIVED') {

                // duplicate validation
                $cartOrderInvoiceData = CartOrder::where('id', $request->input('invoiceId'))
                    ->first();

                if ($cartOrderInvoiceData->orderStatus === 'RECEIVED') {
                    return response()->json(['error' => "Already received!"], 400);
                }
                if ($cartOrderInvoiceData->orderStatus === 'CANCELLED') {
                    return response()->json(['error' => "Already Cancelled!"], 400);
                }
                if ($cartOrderInvoiceData->orderStatus === 'DELIVERED') {
                    return response()->json(['error' => 'Already Delivered!'], 400);
                }
                if ($cartOrderInvoiceData->orderStatus === 'PACKED') {
                    return response()->json(['error' => 'Already PACKED!'], 400);
                }
                if ($cartOrderInvoiceData->orderStatus === 'SHIPPED') {
                    return response()->json(['error' => 'Already PACKED!'], 400);
                }

                if ($subAccountIdForMainTransaction !== 1 && $manualPaymentData->isVerified !== 'Accept') {
                    return response()->json(['error' => 'Need to verify the payment of customer!'], 400);
                }

                if ($manualPaymentData->isVerified === 'Reject') {
                    return response()->json(['error' => 'Rejected payment!'], 400);
                }


                $courierMediumId = $request->input('courierMediumId') ?? null;

                if (!$courierMediumId) {
                    return response()->json(['error' => 'Delivery Medium required!'], 400);
                }

                $courierMediumData = CourierMedium::where('id', $courierMediumId)->first();

                if (!$courierMediumData) {
                    return response()->json(['error' => 'NO courier Found!'], 404);
                }

                //created for coupon code transaction
                if ($subAccountIdForMainTransaction === 1) {
                    if ($cartOrderInvoiceData->couponAmount > 0) {
                        Transaction::create([
                            'date' => new Carbon(),
                            'debitId' => 14,
                            'creditId' => 8,
                            'amount' => takeUptoThreeDecimal($cartOrderInvoiceData->couponAmount),
                            'particulars' => "Coupon Code on cart order #{$cartOrderInvoiceData->id}",
                            'type' => 'sale',
                            'relatedId' => $cartOrderInvoiceData->id,
                        ]);
                    }
                }


                $updatedCartOrderStatus = CartOrder::where('id', $request->input('invoiceId'))
                    ->update([
                        'orderStatus' => $request->input('orderStatus'),
                        'totalAmount' => $request->input('deliveryFee') ? takeUptoThreeDecimal(($cartOrderData->totalAmount - $cartOrderData->deliveryFee) + $request->input('deliveryFee')) : takeUptoThreeDecimal($cartOrderData->totalAmount),
                        'due' => $request->input('deliveryFee') ? takeUptoThreeDecimal(($cartOrderData->due - $cartOrderData->deliveryFee) + $request->input('deliveryFee')) : takeUptoThreeDecimal($cartOrderData->due),
                        'deliveryFee' => $request->input('deliveryFee') ? takeUptoThreeDecimal($request->input('deliveryFee')) : takeUptoThreeDecimal($cartOrderData->deliveryFee),
                        'courierMediumId' => $courierMediumId,
                    ]);


                $updatedCartOrderInvoiceData = CartOrder::with('customer')->where('id', $request->input('invoiceId'))
                    ->first();
                // cost of sales will be created as journal entry
                Transaction::create([
                    'date' => new Carbon(),
                    'debitId' => 9,
                    'creditId' => 3,
                    'amount' => takeUptoThreeDecimal((float)$totalPurchasePrice),
                    'particulars' => "Cost of sales on cart order #{$request->input('invoiceId')}",
                    'type' => 'sale',
                    'relatedId' => $request->input('invoiceId'),
                ]);


                // create due transaction
                if ($subAccountIdForMainTransaction === 1) {
                    Transaction::create([
                        'date' => new Carbon(),
                        'debitId' => 4,
                        'creditId' => 8,
                        'amount' => takeUptoThreeDecimal($updatedCartOrderInvoiceData->due),
                        'particulars' => "Due on  cart order #{$updatedCartOrderInvoiceData->id}",
                        'type' => 'sale',
                        'relatedId' => $updatedCartOrderInvoiceData->id,
                    ]);
                }


            } else if ($request->input('orderStatus') === 'DELIVERED') {

                // duplicate validation
                $invoiceData = CartOrder::where('id', $request->input('invoiceId'))
                    ->first();
                if ($invoiceData->orderStatus === 'DELIVERED') {
                    return response()->json(['error' => 'Already Delivered!'], 400);
                }
                if ($invoiceData->orderStatus === 'CANCELLED') {
                    return response()->json(['error' => "Already Cancelled!"], 400);
                }
                if ($invoiceData->orderStatus === 'PENDING') {
                    return response()->json(['error' => 'Need to Receive the Order first!'], 400);
                }

                $dueAmount = $cartOrderData->due;

                if ($subAccountIdForMainTransaction === 1) {
                    // new transactions will be created as journal entry for paid amount
                    if ($dueAmount > 0) {
                        Transaction::create([
                            'date' => new Carbon(),
                            'debitId' => (int)$subAccountIdForMainTransaction,
                            'creditId' => 8,
                            'amount' => takeUptoThreeDecimal((float)$dueAmount),
                            'particulars' => "Cash receive on cart order #{$request->input('invoiceId')}",
                            'type' => 'sale',
                            'relatedId' => $request->input('invoiceId'),
                        ]);
                    }

                    // created vat into transaction
                    if ($totalVat > 0) {
                        Transaction::create([
                            'date' => new Carbon(),
                            'debitId' => (int)$subAccountIdForMainTransaction,
                            'creditId' => 15,
                            'amount' => takeUptoThreeDecimal((float)$totalVat),
                            'particulars' => "Vat Collected on  cart order #{$request->input('invoiceId')}",
                            'type' => 'vat',
                            'relatedId' => $request->input('invoiceId'),
                        ]);
                    }

                    //created discount into transaction
                    if ($totalDiscount > 0) {
                        Transaction::create([
                            'date' => new Carbon(),
                            'debitId' => 14,
                            'creditId' => (int)$subAccountIdForMainTransaction,
                            'amount' => takeUptoThreeDecimal((float)$totalDiscount),
                            'particulars' => "Discount on cart order #{$request->input('invoiceId')}",
                            'type' => 'sale',
                            'relatedId' => $request->input('invoiceId'),
                        ]);
                    }

                    $updatedCartOrderStatus = CartOrder::where('id', $request->input('invoiceId'))
                        ->update([
                            'orderStatus' => $request->input('orderStatus'),
                            'paidAmount' => takeUptoThreeDecimal($dueAmount),
                            'isPaid' => "true",
                            'due' => takeUptoThreeDecimal($cartOrderData->totalAmount - $cartOrderData->due),
                        ]);

                    ManualPayment::where('cartOrderId', $request->input('invoiceId'))
                        ->update([
                            'isVerified' => 'Accept'
                        ]);
                } else {
                    $updatedCartOrderStatus = CartOrder::where('id', $request->input('invoiceId'))
                        ->update([
                            'orderStatus' => $request->input('orderStatus'),
                        ]);
                }
                $updatedCartOrderInvoiceData = CartOrder::with('customer')->where('id', $request->input('invoiceId'))
                    ->first();
            } else if ($request->input('orderStatus') === 'PENDING') {
                // duplicate validation
                $invoiceData = CartOrder::where('id', $request->input('invoiceId'))
                    ->first();

                if ($invoiceData->orderStatus === 'RECEIVED') {
                    return response()->json(['error' => "Already received!"], 400);
                }
                if ($invoiceData->orderStatus === 'CANCELLED') {
                    return response()->json(['error' => "Already Cancelled!"], 400);
                }
                if ($invoiceData->orderStatus === 'DELIVERED') {
                    return response()->json(['error' => 'Already Delivered!'], 400);
                }
                if ($invoiceData->orderStatus === 'PACKED') {
                    return response()->json(['error' => 'Already PACKED!'], 400);
                }
                if ($invoiceData->orderStatus === 'SHIPPED') {
                    return response()->json(['error' => 'Already PACKED!'], 400);
                }
            } elseif ($request->input('orderStatus') === 'CANCELLED') {

                // duplicate validation
                $cartOrderInvoiceData = CartOrder::where('id', $request->input('invoiceId'))
                    ->first();

                if ($cartOrderInvoiceData->orderStatus === 'DELIVERED') {
                    return response()->json(['error' => 'Already Delivered!'], 400);
                }
                if ($cartOrderInvoiceData->orderStatus === 'CANCELLED') {
                    return response()->json(['error' => "Already Cancelled!"], 400);
                }
                if ($cartOrderInvoiceData->orderStatus === 'SHIPPED') {
                    return response()->json(['error' => 'Already PACKED!'], 400);
                }

                // delete cost of sales from transaction
                $transaction1 = Transaction::where('type', 'sale')
                    ->where('relatedId', $cartOrderInvoiceData->id)
                    ->where('debitId', 9)
                    ->where('creditId', 3)
                    ->first();
                if ($transaction1) {
                    $transaction1->delete();
                }

                // delete due transaction from transaction
                $transaction2 = Transaction::where('type', 'sale')
                    ->where('relatedId', $cartOrderInvoiceData->id)
                    ->where('debitId', 4)
                    ->where('creditId', 8)
                    ->first();
                if ($transaction2) {
                    $transaction2->delete();
                }

                // delete coupon transaction from transaction
                $transaction3 = Transaction::where('type', 'sale')
                    ->where('relatedId', $cartOrderInvoiceData->id)
                    ->where('debitId', 14)
                    ->where('creditId', 8)
                    ->first();
                if ($transaction3) {
                    $transaction3->delete();
                }

                if ($manualPaymentData->paymentMethod->subAccountId !== 1) {
                    // delete paid transaction from transaction
                    $transaction4 = Transaction::where('type', 'sale')
                        ->where('relatedId', $cartOrderInvoiceData->id)
                        ->where('debitId', (int)$subAccountIdForMainTransaction)
                        ->where('creditId', 8)
                        ->first();
                    if ($transaction4) {
                        $transaction4->delete();
                    }

                    // delete vat transaction from transaction
                    $transaction5 = Transaction::where('type', 'vat')
                        ->where('relatedId', $cartOrderInvoiceData->id)
                        ->where('debitId', (int)$subAccountIdForMainTransaction)
                        ->where('creditId', 15)
                        ->first();
                    if ($transaction5) {
                        $transaction5->delete();
                    }

                    // delete discount transaction from transaction
                    $transaction5 = Transaction::where('type', 'vat')
                        ->where('relatedId', $cartOrderInvoiceData->id)
                        ->where('debitId', 14)
                        ->where('creditId', (int)$subAccountIdForMainTransaction)
                        ->first();
                    if ($transaction5) {
                        $transaction5->delete();
                    }
                }

                $updatedCartOrderStatus = CartOrder::where('id', $request->input('invoiceId'))
                    ->update([
                        'orderStatus' => $request->input('orderStatus')
                    ]);

                $updatedCartOrderInvoiceData = CartOrder::with('customer')->where('id', $request->input('invoiceId'))
                    ->first();

            } else {

                // validation for received
                $invoiceData = CartOrder::where('id', $request->input('invoiceId'))
                    ->first();
                if ($invoiceData->orderStatus === 'PENDING') {
                    return response()->json(['error' => 'Need to Receive the Order first!'], 400);
                }
                if ($invoiceData->orderStatus === 'CANCELLED') {
                    return response()->json(['error' => "Already Cancelled!"], 400);
                }
                if ($invoiceData->orderStatus === 'DELIVERED') {
                    return response()->json(['error' => 'Already Delivered!'], 400);
                }

                $updatedCartOrderStatus = CartOrder::where('id', $request->input('invoiceId'))
                    ->update([
                        'orderStatus' => $request->input('orderStatus')
                    ]);

                $updatedCartOrderInvoiceData = CartOrder::with('customer')->where('id', $request->input('invoiceId'));
            }

            if (!$updatedCartOrderStatus) {
                DB::rollBack();
                return response()->json(['error' => 'Failed To Update cart order status'], 404);
            }

            if ($updatedCartOrderInvoiceData) {
                try {
                    $this->MailStructure->StatusChange($updatedCartOrderInvoiceData->customer->email, $updatedCartOrderInvoiceData);
                } catch (Exception $err) {
                    DB::commit();
                    return response()->json(['message' => 'Cart Order updated successfully!'], 200);
                }
            }
            DB::commit();
            return response()->json(['message' => 'Cart Order updated successfully!'], 200);
        } catch (Exception $err) {
            DB::rollBack();
            echo $err;
            return response()->json(['error' => $err->getMessage()], 500);
        }
    }
}
