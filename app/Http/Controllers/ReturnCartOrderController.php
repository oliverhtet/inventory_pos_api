<?php

namespace App\Http\Controllers;

use Exception;
use Carbon\Carbon;
use App\Models\{Users,
    Transaction,
    Product,
    ReturnCartOrder,
    ReturnCartOrderProduct,
    ReturnCartOrderAttributeValue,
    CartOrder,
    CartOrderProduct,
    Customer,
    ManualPayment};
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\DB;
use App\MailStructure\MailStructure;

class ReturnCartOrderController extends Controller
{

    protected MailStructure $MailStructure;

    public function __construct(MailStructure $MailStructure)
    {
        $this->MailStructure = $MailStructure;
    }

    //create returnCartOrder controller method
    public function createSingleReturnCartOrder(Request $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            // is cartOrder exist or not validation
            $cartOrder = CartOrder::with('customer')->where('id', $request->input('cartOrderId'))->where('customerId', $request->input('customerId'))->first();
            if (!$cartOrder) {
                return response()->json(['error' => 'No Cart Order Found!'], 404);
            }

            if ($cartOrder->orderStatus !== 'DELIVERED') {
                return response()->json(['error' => 'Need to delivered first!'], 400);
            }

            $returnCartOrderData = ReturnCartOrder::where('cartOrderId', $cartOrder->id)
                ->whereHas('returnCartOrderProduct', function ($subQuery) use ($request) {
                    $subQuery->when($request->input('cartOrderProductId'), function ($finalQuery) use ($request) {
                        $finalQuery->where('cartOrderProductId', $request->input('cartOrderProductId'));
                    });
                })
                ->with([
                    'returnCartOrderProduct' => function ($query) use ($request) {
                        $query->when($request->input('cartOrderProductId'), function ($subQuery) use ($request) {
                            $subQuery->where('cartOrderProductId', $request->input('cartOrderProductId'));
                        });
                    }
                ])
                ->get();

            // Get all the cart order product
            $allReturnCartOrderProducts = CartOrderProduct::select(
                "cartOrderProduct.*",
                DB::raw("'{$request->input('productQuantity')}' as returnProductQuantity")
            )
                ->where('invoiceId', $request->input('cartOrderId'))
                ->where('id', $request->input('cartOrderProductId'))
                ->with(
                    'cartOrderAttributeValue',
                    'colors',
                    'product'
                )
                ->get();

            $alreadyReturnedProductQuantity = count($returnCartOrderData) !== 0 ? collect($returnCartOrderData)->map(function ($item) {
                return $item->returnCartOrderProduct[0]->productQuantity;
            })->sum() : 0;
            $totalOrderedQuantity = count($allReturnCartOrderProducts) !== 0 ? $allReturnCartOrderProducts[0]->productQuantity : 0;
            $remainForReturn = $totalOrderedQuantity - $alreadyReturnedProductQuantity;

            if (count($allReturnCartOrderProducts) === 0) {
                return response()->json(['error' => 'No Ordered product found!, please check ordered product properly'], 400);
            }

            if ($request->input('productQuantity') === 0) {
                return response()->json(['error' => 'quantity cannot be null or zero'], 400);
            }

            if ($request->input('productQuantity') > $remainForReturn) {
                return response()->json(['error' => 'insufficient product quantity to return!, please check the product quantity properly'], 400);
            }

            if (0 >= $remainForReturn) {
                return response()->json(['error' => 'Already Returned!'], 400);
            }

            // calculate total salePrice of returned product
            $totalReturnSalePriceWithVatAndDiscount = 0;
            $totalReturnVat = 0;
            $totalReturnDiscount = 0;
            $totalReturnCoupon = 0;

            foreach (collect($allReturnCartOrderProducts) as $item) {
                $productTotalPrice = (float)$item['productSalePrice'] * (int)$item['returnProductQuantity'];

                if ((int)$item['returnProductQuantity'] > $item['productQuantity']) {
                    return response()->json(['error' => 'Return not possible because of incorrect product return Quantity'], 400);
                }

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
                $totalAmount = ($productTotalPrice + $productVat) - $discount;
                $totalVat = $productVat;
                $totalDiscount = $discount;

                $totalReturnSalePriceWithVatAndDiscount += $totalAmount;
                $totalReturnVat += $totalVat;
                $totalReturnDiscount += $totalDiscount;
            }

            if ($cartOrder->couponAmount > 0) {
                $baseAmount = ($cartOrder->totalAmount - $cartOrder->deliveryFee) + $cartOrder->couponAmount;
                $totalReturnCoupon = takeUptoThreeDecimal(($cartOrder->couponAmount * $totalReturnSalePriceWithVatAndDiscount) / $baseAmount);
            }

            // create returnCartOrder method
            $createdReturnCartOrder = ReturnCartOrder::create([
                'date' => new Carbon(),
                'cartOrderId' => $cartOrder->id,
                'totalAmount' => takeUptoThreeDecimal((float)$totalReturnSalePriceWithVatAndDiscount - $totalReturnCoupon),
                'totalVatAmount' => takeUptoThreeDecimal((float)$totalReturnVat),
                'totalDiscountAmount' => takeUptoThreeDecimal((float)$totalReturnDiscount),
                'note' => $request->input('note'),
                'couponAmount' => $totalReturnCoupon ?? 0,
                'returnType' => $request->input('returnType'),
                'returnCartOrderStatus' => 'PENDING',
            ]);

            if ($createdReturnCartOrder) {
                foreach ($allReturnCartOrderProducts as $item) {
                    $createdReturnCartOrderProduct = ReturnCartOrderProduct::create([
                        'returnCartOrderId' => $createdReturnCartOrder->id,
                        'productId' => (int)$item['productId'],
                        'cartOrderProductId' => (int)$item['id'],
                        'productQuantity' => (int)$item['returnProductQuantity'],
                        'productSalePrice' => takeUptoThreeDecimal((float)$item['productSalePrice']),
                        'colorId' => $item['colorId'],
                        'productVat' => $item['productVat'],
                        'discountType' => $item['discountType'],
                        'discount' => $item['discount'],
                    ]);

                    if ($createdReturnCartOrderProduct) {
                        if (count($item['cartOrderAttributeValue']) !== 0) {
                            foreach (collect($item['cartOrderAttributeValue']) as $attribute) {
                                ReturnCartOrderAttributeValue::create([
                                    'returnCartOrderProductId' => $createdReturnCartOrderProduct->id,
                                    'productAttributeValueId' => $attribute['productAttributeValueId']
                                ]);
                            }
                        }
                    }
                }
            }

            $this->MailStructure->ReturnOrder($cartOrder->customer, $createdReturnCartOrder->toArray(), $allReturnCartOrderProducts->toArray(), $returnCartOrderData->toArray());
            $converted = arrayKeysToCamelCase($createdReturnCartOrder->toArray());
            DB::commit();
            return response()->json($converted, 201);
        } catch (Exception) {
            DB::rollBack();
            return response()->json(['error' => 'An error occurred during create Return Cart Order. Please try again later.'], 500);
        }
    }

    //get all returnCartOrder controller method
    public function getAllReturnCartOrder(Request $request): JsonResponse
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
                $getAllReturnCartOrder = ReturnCartOrder::with('returnCartOrderProduct.product', 'cartOrder')
                    ->whereHas('cartOrder', function ($query) use ($userSub) {
                        return $query->where('customerId', $userSub);
                    })
                    ->orderBy('id', 'desc')
                    ->when($request->query('returnCartOrderStatus'), function ($query) use ($request) {
                        return $query->whereIn('returnCartOrderStatus', explode(',', $request->query('returnCartOrderStatus')));
                    })
                    ->when($request->query('returnType'), function ($query) use ($request) {
                        return $query->whereIn('returnType', explode(',', $request->query('returnType')));
                    })
                    ->when($request->query('startDate') && $request->query('endDate'), function ($query) use ($request) {
                        return $query->where('date', '>=', Carbon::createFromFormat('Y-m-d', $request->query('startDate')))
                            ->where('date', '<=', Carbon::createFromFormat('Y-m-d', $request->query('endDate')));
                    })
                    ->skip($pagination['skip'])
                    ->take($pagination['limit'])
                    ->get();

                $getAllReturnCartOrderCount = ReturnCartOrder::with('returnCartOrderProduct', 'cartOrder')
                    ->whereHas('cartOrder', function ($query) use ($userSub) {
                        return $query->where('customerId', $userSub);
                    })
                    ->orderBy('id', 'desc')
                    ->when($request->query('returnCartOrderStatus'), function ($query) use ($request) {
                        return $query->whereIn('returnCartOrderStatus', explode(',', $request->query('returnCartOrderStatus')));
                    })
                    ->when($request->query('returnType'), function ($query) use ($request) {
                        return $query->whereIn('returnType', explode(',', $request->query('returnType')));
                    })
                    ->when($request->query('startDate') && $request->query('endDate'), function ($query) use ($request) {
                        return $query->where('date', '>=', Carbon::createFromFormat('Y-m-d', $request->query('startDate')))
                            ->where('date', '<=', Carbon::createFromFormat('Y-m-d', $request->query('endDate')));
                    })
                    ->count();

                // remove product purchase price and profit for customer view
                collect($getAllReturnCartOrder)->each(function ($item) {
                    unset($item->cartOrder->profit);
                    ($item->returnCartOrderProduct)->each(function ($item2) {
                        unset($item2->product->productPurchasePrice);
                    });
                });

                $converted = arrayKeysToCamelCase($getAllReturnCartOrder->toArray());

                $finalResult = [
                    'getAllReturnCartOrder' => $converted,
                    'totalReturnCartOrder' => $getAllReturnCartOrderCount,
                ];

                return response()->json($finalResult, 200);
            } catch (Exception $err) {
                return Response()->json(['error' => 'An error occurred during getting return cart order by Customer. Please try again later.', $err->getMessage()], 500);
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
                    $allReturnCartOrder = ReturnCartOrder::with('returnCartOrderProduct', 'cartOrder')
                        ->orWhereHas('cartOrder.customer', function ($query) use ($request) {
                            $query->where('username', 'LIKE', '%' . $request->query('key') . '%');
                        })
                        ->orderBy('id', 'desc')
                        ->skip($pagination['skip'])
                        ->take($pagination['limit'])
                        ->get();


                    $allReturnCartOrderCount = ReturnCartOrder::with('returnCartOrderProduct', 'cartOrder')
                        ->orderBy('id', 'desc')
                        ->orWhereHas('cartOrder.customer', function ($query) use ($request) {
                            $query->where('username', 'LIKE', '%' . $request->query('key') . '%');
                        })
                        ->count();


                    $cartOrdersIds = $allReturnCartOrder->pluck('cartOrderId')->toArray();
                    $transactionsForPaidAmount = Transaction::where('type', 'sale_return')
                        ->whereIn('relatedId', $cartOrdersIds)
                        ->where(function ($query) {
                            $query->orWhere('creditId', 1)
                                ->orWhere('creditId', 2);
                        })
                        ->get();

                    $transactionsForVatAmount = Transaction::where('type', 'vat_return')
                        ->whereIn('relatedId', $cartOrdersIds)
                        ->where(function ($query) {
                            $query->where('creditId', 1)
                                ->where('debitId', 15);
                        })
                        ->get();

                    $transactionsForDiscountAmount = Transaction::where('type', 'sale_return')
                        ->whereIn('relatedId', $cartOrdersIds)
                        ->where(function ($query) {
                            $query->where('creditId', 14)
                                ->where('debitId', 1);
                        })
                        ->get();

                    $transactionsForCouponAmount = Transaction::where('type', 'sale_return')
                        ->whereIn('relatedId', $cartOrdersIds)
                        ->where(function ($query) {
                            $query->where('creditId', 14)
                                ->where('debitId', 8);
                        })
                        ->get();


                    $allReturnCartOrder = $allReturnCartOrder->map(function ($item) use ($transactionsForPaidAmount, $transactionsForVatAmount, $transactionsForDiscountAmount, $transactionsForCouponAmount) {
                        $returnPaidAmount = $transactionsForPaidAmount->filter(function ($transaction) use ($item) {
                            return $transaction->relatedId === $item->cartOrderId;
                        })->sum('amount');

                        $returnVatAmount = $transactionsForVatAmount->filter(function ($transaction) use ($item) {
                            return $transaction->relatedId === $item->cartOrderId;
                        })->sum('amount');

                        $returnDiscountAmount = $transactionsForDiscountAmount->filter(function ($transaction) use ($item) {
                            return $transaction->relatedId === $item->cartOrderId;
                        })->sum('amount');

                        $returnCouponAmount = $transactionsForCouponAmount->filter(function ($transaction) use ($item) {
                            return $transaction->relatedId === $item->cartOrderId;
                        })->sum('amount');

                        $totaluomValue = $item->returnCartOrderProduct->reduce(function ($acc, $curr) {
                            return $acc + ((int)$curr->product->uomValue * $curr->productQuantity);
                        }, 0);


                        $item->totalReturnPaidAmount = $returnPaidAmount;
                        $item->totalReturnVatAmount = $returnVatAmount;
                        $item->totalReturnDiscountAmount = $returnDiscountAmount;
                        $item->totalReturnCouponAmount = $returnCouponAmount;

                        $item->totaluomValue = $totaluomValue;

                        return $item;
                    });

                    $converted = arrayKeysToCamelCase($allReturnCartOrder->toArray());
                    $totaluomValue = $allReturnCartOrder->sum('totaluomValue');
                    $totalUnitQuantity = $allReturnCartOrder->map(function ($item) {
                        return $item->returnCartOrderProduct->sum('productQuantity');
                    })->sum();

                    $finalResult = [
                        'aggregations' => [
                            '_count' => [
                                'id' => $allReturnCartOrder->count(),
                            ],
                            '_sum' => [
                                'totalAmount' => takeUptoThreeDecimal($allReturnCartOrder->sum('totalAmount')),
                                'totalReturnPaidAmount' => takeUptoThreeDecimal($allReturnCartOrder->sum('totalReturnPaidAmount')),
                                'totalReturnDiscountAmount' => takeUptoThreeDecimal($allReturnCartOrder->sum('totalReturnDiscountAmount')),
                                'totalReturnVatAmount' => takeUptoThreeDecimal($allReturnCartOrder->sum('totalReturnVatAmount')),
                                'totalReturnCouponAmount' => takeUptoThreeDecimal($allReturnCartOrder->sum('totalReturnCouponAmount')),
                                'totaluomValue' => $totaluomValue,
                                'totalUnitQuantity' => $totalUnitQuantity,
                            ],
                        ],
                        'getAllReturnCartOrder' => $converted,
                        'totalReturnCartOrder' => $allReturnCartOrderCount,
                    ];

                    return response()->json($finalResult, 200);
                } catch (Exception $err) {
                    echo $err;
                    return response()->json(['error' => 'An error occurred during getting cart order. Please try again later.'], 500);
                }
            } else if ($request->query('query') === 'report'){
                try {
                    $allReturnCartOrder = ReturnCartOrder::with('returnCartOrderProduct', 'cartOrder', 'cartOrder.customer:id,username')
                        ->orderBy('id', 'desc')
                        
                        ->when($request->query('customerId'), function ($query) use ($request) {
                            return $query->whereHas('cartOrder.customer', function ($subQuery) use ($request) {
                                $subQuery->where('id', $request->query('customerId'));
                            });
                        })
                        ->when($request->query('returnCartOrderStatus'), function ($query) use ($request) {
                            return $query->where('returnCartOrderStatus', $request->query('returnCartOrderStatus'));
                        })
                        ->when($request->query('returnType'), function ($query) use ($request) {
                            return $query->where('returnType', $request->query('returnType'));
                        })
                        ->when($request->query('startDate') && $request->query('endDate'), function ($query) use ($request) {
                            return $query->where('date', '>=', Carbon::createFromFormat('Y-m-d', $request->query('startDate')))
                                ->where('date', '<=', Carbon::createFromFormat('Y-m-d', $request->query('endDate')));
                        })
                        ->get();
                    

                    


                    $cartOrdersIds = $allReturnCartOrder->pluck('cartOrderId')->toArray();
                    $transactionsForPaidAmount = Transaction::where('type', 'sale_return')
                        ->whereIn('relatedId', $cartOrdersIds)
                        ->where(function ($query) {
                            $query->orWhere('creditId', 1)
                                ->orWhere('creditId', 2);
                        })
                        ->get();

                    $transactionsForVatAmount = Transaction::where('type', 'vat_return')
                        ->whereIn('relatedId', $cartOrdersIds)
                        ->where(function ($query) {
                            $query->where('creditId', 1)
                                ->where('debitId', 15);
                        })
                        ->get();

                    $transactionsForDiscountAmount = Transaction::where('type', 'sale_return')
                        ->whereIn('relatedId', $cartOrdersIds)
                        ->where(function ($query) {
                            $query->where('creditId', 14)
                                ->where('debitId', 1);
                        })
                        ->get();

                    $transactionsForCouponAmount = Transaction::where('type', 'sale_return')
                        ->whereIn('relatedId', $cartOrdersIds)
                        ->where(function ($query) {
                            $query->where('creditId', 14)
                                ->where('debitId', 8);
                        })
                        ->get();


                    $allReturnCartOrder = $allReturnCartOrder->map(function ($item) use ($transactionsForPaidAmount, $transactionsForVatAmount, $transactionsForDiscountAmount, $transactionsForCouponAmount) {
                        $returnPaidAmount = $transactionsForPaidAmount->filter(function ($transaction) use ($item) {
                            return $transaction->relatedId === $item->cartOrderId;
                        })->sum('amount');

                        $returnVatAmount = $transactionsForVatAmount->filter(function ($transaction) use ($item) {
                            return $transaction->relatedId === $item->cartOrderId;
                        })->sum('amount');

                        $returnDiscountAmount = $transactionsForDiscountAmount->filter(function ($transaction) use ($item) {
                            return $transaction->relatedId === $item->cartOrderId;
                        })->sum('amount');

                        $returnCouponAmount = $transactionsForCouponAmount->filter(function ($transaction) use ($item) {
                            return $transaction->relatedId === $item->cartOrderId;
                        })->sum('amount');

                        $totaluomValue = $item->returnCartOrderProduct->reduce(function ($acc, $curr) {
                            return $acc + ((int)$curr->product->uomValue * $curr->productQuantity);
                        }, 0);


                        $item->totalReturnPaidAmount = $returnPaidAmount;
                        $item->totalReturnVatAmount = $returnVatAmount;
                        $item->totalReturnDiscountAmount = $returnDiscountAmount;
                        $item->totalReturnCouponAmount = $returnCouponAmount;

                        $item->totaluomValue = $totaluomValue;

                        return $item;
                    });

                    $converted = arrayKeysToCamelCase($allReturnCartOrder->toArray());
                    $totaluomValue = $allReturnCartOrder->sum('totaluomValue');
                    $totalUnitQuantity = $allReturnCartOrder->map(function ($item) {
                        return $item->returnCartOrderProduct->sum('productQuantity');
                    })->sum();

                    $finalResult = [
                        'aggregations' => [
                            '_count' => [
                                'id' => $allReturnCartOrder->count(),
                            ],
                            '_sum' => [
                                'totalAmount' => takeUptoThreeDecimal($allReturnCartOrder->sum('totalAmount')),
                                'totalReturnPaidAmount' => takeUptoThreeDecimal($allReturnCartOrder->sum('totalReturnPaidAmount')),
                                'totalReturnDiscountAmount' => takeUptoThreeDecimal($allReturnCartOrder->sum('totalReturnDiscountAmount')),
                                'totalReturnVatAmount' => takeUptoThreeDecimal($allReturnCartOrder->sum('totalReturnVatAmount')),
                                'totalReturnCouponAmount' => takeUptoThreeDecimal($allReturnCartOrder->sum('totalReturnCouponAmount')),
                                'totaluomValue' => $totaluomValue,
                                'totalUnitQuantity' => $totalUnitQuantity,
                            ],
                        ],
                        'getAllReturnCartOrder' => $converted,
                        'totalReturnCartOrder' => $allReturnCartOrder->count(),
                    ];

                    return response()->json($finalResult, 200);
                } catch (Exception $err) {
                    echo $err;
                    return response()->json(['error' => 'An error occurred during getting cart order. Please try again later.'], 500);
                }
            } else if ($request->query()) {
                try {
                    $pagination = getPagination($request->query());
                    $allReturnCartOrder = ReturnCartOrder::with('returnCartOrderProduct', 'cartOrder')
                        ->orderBy('id', 'desc')
                        ->when($request->query('returnCartOrderStatus'), function ($query) use ($request) {
                            return $query->whereIn('returnCartOrderStatus', explode(',', $request->query('returnCartOrderStatus')));
                        })
                        ->when($request->query('returnType'), function ($query) use ($request) {
                            return $query->whereIn('returnType', explode(',', $request->query('returnType')));
                        })
                        ->when($request->query('startDate') && $request->query('endDate'), function ($query) use ($request) {
                            return $query->where('date', '>=', Carbon::createFromFormat('Y-m-d', $request->query('startDate')))
                                ->where('date', '<=', Carbon::createFromFormat('Y-m-d', $request->query('endDate')));
                        })
                        ->skip($pagination['skip'])
                        ->take($pagination['limit'])
                        ->get();


                    $allReturnCartOrderCount = ReturnCartOrder::with('returnCartOrderProduct', 'cartOrder')
                        ->orderBy('id', 'desc')
                        ->when($request->query('returnCartOrderStatus'), function ($query) use ($request) {
                            return $query->whereIn('returnCartOrderStatus', explode(',', $request->query('returnCartOrderStatus')));
                        })
                        ->when($request->query('returnType'), function ($query) use ($request) {
                            return $query->whereIn('returnType', explode(',', $request->query('returnType')));
                        })
                        ->when($request->query('startDate') && $request->query('endDate'), function ($query) use ($request) {
                            return $query->where('date', '>=', Carbon::createFromFormat('Y-m-d', $request->query('startDate')))
                                ->where('date', '<=', Carbon::createFromFormat('Y-m-d', $request->query('endDate')));
                        })
                        ->count();


                    $cartOrdersIds = $allReturnCartOrder->pluck('cartOrderId')->toArray();
                    $transactionsForPaidAmount = Transaction::where('type', 'sale_return')
                        ->whereIn('relatedId', $cartOrdersIds)
                        ->where(function ($query) {
                            $query->orWhere('creditId', 1)
                                ->orWhere('creditId', 2);
                        })
                        ->get();

                    $transactionsForVatAmount = Transaction::where('type', 'vat_return')
                        ->whereIn('relatedId', $cartOrdersIds)
                        ->where(function ($query) {
                            $query->where('creditId', 1)
                                ->where('debitId', 15);
                        })
                        ->get();

                    $transactionsForDiscountAmount = Transaction::where('type', 'sale_return')
                        ->whereIn('relatedId', $cartOrdersIds)
                        ->where(function ($query) {
                            $query->where('creditId', 14)
                                ->where('debitId', 1);
                        })
                        ->get();

                    $transactionsForCouponAmount = Transaction::where('type', 'sale_return')
                        ->whereIn('relatedId', $cartOrdersIds)
                        ->where(function ($query) {
                            $query->where('creditId', 14)
                                ->where('debitId', 8);
                        })
                        ->get();


                    $allReturnCartOrder = $allReturnCartOrder->map(function ($item) use ($transactionsForPaidAmount, $transactionsForVatAmount, $transactionsForDiscountAmount, $transactionsForCouponAmount) {
                        $returnPaidAmount = $transactionsForPaidAmount->filter(function ($transaction) use ($item) {
                            return $transaction->relatedId === $item->cartOrderId;
                        })->sum('amount');

                        $returnVatAmount = $transactionsForVatAmount->filter(function ($transaction) use ($item) {
                            return $transaction->relatedId === $item->cartOrderId;
                        })->sum('amount');

                        $returnDiscountAmount = $transactionsForDiscountAmount->filter(function ($transaction) use ($item) {
                            return $transaction->relatedId === $item->cartOrderId;
                        })->sum('amount');

                        $returnCouponAmount = $transactionsForCouponAmount->filter(function ($transaction) use ($item) {
                            return $transaction->relatedId === $item->cartOrderId;
                        })->sum('amount');

                        $totaluomValue = $item->returnCartOrderProduct->reduce(function ($acc, $curr) {
                            return $acc + ((int)$curr->product->uomValue * $curr->productQuantity);
                        }, 0);


                        $item->totalReturnPaidAmount = $returnPaidAmount;
                        $item->totalReturnVatAmount = $returnVatAmount;
                        $item->totalReturnDiscountAmount = $returnDiscountAmount;
                        $item->totalReturnCouponAmount = $returnCouponAmount;

                        $item->totaluomValue = $totaluomValue;

                        return $item;
                    });

                    $converted = arrayKeysToCamelCase($allReturnCartOrder->toArray());
                    $totaluomValue = $allReturnCartOrder->sum('totaluomValue');
                    $totalUnitQuantity = $allReturnCartOrder->map(function ($item) {
                        return $item->returnCartOrderProduct->sum('productQuantity');
                    })->sum();

                    $finalResult = [
                        'aggregations' => [
                            '_count' => [
                                'id' => $allReturnCartOrder->count(),
                            ],
                            '_sum' => [
                                'totalAmount' => takeUptoThreeDecimal($allReturnCartOrder->sum('totalAmount')),
                                'totalReturnPaidAmount' => takeUptoThreeDecimal($allReturnCartOrder->sum('totalReturnPaidAmount')),
                                'totalReturnDiscountAmount' => takeUptoThreeDecimal($allReturnCartOrder->sum('totalReturnDiscountAmount')),
                                'totalReturnVatAmount' => takeUptoThreeDecimal($allReturnCartOrder->sum('totalReturnVatAmount')),
                                'totalReturnCouponAmount' => takeUptoThreeDecimal($allReturnCartOrder->sum('totalReturnCouponAmount')),
                                'totaluomValue' => $totaluomValue,
                                'totalUnitQuantity' => $totalUnitQuantity,
                            ],
                        ],
                        'getAllReturnCartOrder' => $converted,
                        'totalReturnCartOrder' => $allReturnCartOrderCount,
                    ];

                    return response()->json($finalResult, 200);
                } catch (Exception $err) {
                    echo $err;
                    return response()->json(['error' => 'An error occurred during getting cart order. Please try again later.'], 500);
                }
            }
        } else {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    //get single returnCartOrder controller method
    public function getSingleReturnCartOrder(Request $request, $id): JsonResponse
    {

        // for validation system implement here
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
                $getSingleReturnCartOrder = ReturnCartOrder::where('id', $id)
                    ->whereHas('cartOrder', function ($query) use ($userSub) {
                        return $query->where('customerId', $userSub);
                    })
                    ->with('returnCartOrderProduct', 'cartOrder', 'cartOrder.customer:id,firstName,lastName,username,email,phone,address,profileImage')
                    ->first();

                if (!$getSingleReturnCartOrder) {
                    return response()->json(['error' => 'No return cart order found!'], 404);
                }

                $transactionsForPaidAmount = Transaction::where('type', 'sale_return')
                    ->where('relatedId', $getSingleReturnCartOrder->cartOrderId)
                    ->where(function ($query) {
                        $query->orWhere('creditId', 1)
                            ->orWhere('creditId', 2);
                    })
                    ->get();

                $transactionsForVatAmount = Transaction::where('type', 'vat_return')
                    ->where('relatedId', $getSingleReturnCartOrder->cartOrderId)
                    ->where(function ($query) {
                        $query->where('creditId', 1)
                            ->where('debitId', 15);
                    })
                    ->get();

                $transactionsForDiscountAmount = Transaction::where('type', 'sale_return')
                    ->where('relatedId', $getSingleReturnCartOrder->cartOrderId)
                    ->where(function ($query) {
                        $query->where('creditId', 14)
                            ->where('debitId', 1);
                    })
                    ->get();

                $transactionsForCouponAmount = Transaction::where('type', 'sale_return')
                    ->where('relatedId', $getSingleReturnCartOrder->cartOrderId)
                    ->where(function ($query) {
                        $query->where('creditId', 14)
                            ->where('debitId', 8);
                    })
                    ->get();

                $returnPaidAmount = $transactionsForPaidAmount->sum('amount');
                $returnVatAmount = $transactionsForVatAmount->sum('amount');
                $returnDiscountAmount = $transactionsForDiscountAmount->sum('amount');
                $returnCouponAmount = $transactionsForCouponAmount->sum('amount');

                $totaluomValue = $getSingleReturnCartOrder->returnCartOrderProduct->reduce(function ($acc, $curr) {
                    return $acc + ((int)$curr->product->uomValue * $curr->productQuantity);
                }, 0);

                $totalUnitQuantity = $getSingleReturnCartOrder->returnCartOrderProduct->sum('productQuantity');

                $getSingleReturnCartOrder->totalReturnPaidAmount = $returnPaidAmount;
                $getSingleReturnCartOrder->totalReturnVatAmount = $returnVatAmount;
                $getSingleReturnCartOrder->totalReturnDiscountAmount = $returnDiscountAmount;
                $getSingleReturnCartOrder->totalReturnCouponAmount = $returnCouponAmount;
                $getSingleReturnCartOrder->totaluomValue = $totaluomValue;
                $getSingleReturnCartOrder->totalUnitQuantity = $totalUnitQuantity;

                // remove profit and purchase price
                unset($getSingleReturnCartOrder->cartOrder->profit);
                $getSingleReturnCartOrder->returnCartOrderProduct->each(function ($item) {
                    unset($item->product->productPurchasePrice);
                });


                $converted = arrayKeysToCamelCase($getSingleReturnCartOrder->toArray());

                return response()->json($converted, 200);
            } catch (Exception $err) {
                echo $err;
                return response()->json(['error' => 'An error occurred during getting cart order. Please try again later.'], 500);
            }
        } else if ($userRole === 'admin') {

            // check authentication
            $userFromDB = Users::where('id', $userSub)->with('role:id,name')->first();
            if ($userSub !== (int)$userFromDB->id && $userRole !== $userFromDB->role->name) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            try {
                $getSingleReturnCartOrder = ReturnCartOrder::where('id', $id)
                    ->with('returnCartOrderProduct', 'cartOrder', 'cartOrder.customer:id,firstName,lastName,username,email,phone,address,profileImage')
                    ->first();

                if (!$getSingleReturnCartOrder) {
                    return response()->json(['error' => 'No return cart order found!'], 404);
                }

                $transactionsForPaidAmount = Transaction::where('type', 'sale_return')
                    ->where('relatedId', $getSingleReturnCartOrder->cartOrderId)
                    ->where(function ($query) {
                        $query->orWhere('creditId', 1)
                            ->orWhere('creditId', 2);
                    })
                    ->get();

                $transactionsForVatAmount = Transaction::where('type', 'vat_return')
                    ->where('relatedId', $getSingleReturnCartOrder->cartOrderId)
                    ->where(function ($query) {
                        $query->where('creditId', 1)
                            ->where('debitId', 15);
                    })
                    ->get();

                $transactionsForDiscountAmount = Transaction::where('type', 'sale_return')
                    ->where('relatedId', $getSingleReturnCartOrder->cartOrderId)
                    ->where(function ($query) {
                        $query->where('creditId', 14)
                            ->where('debitId', 1);
                    })
                    ->get();

                $transactionsForCouponAmount = Transaction::where('type', 'sale_return')
                    ->where('relatedId', $getSingleReturnCartOrder->cartOrderId)
                    ->where(function ($query) {
                        $query->where('creditId', 14)
                            ->where('debitId', 8);
                    })
                    ->get();

                $returnPaidAmount = $transactionsForPaidAmount->sum('amount');
                $returnVatAmount = $transactionsForVatAmount->sum('amount');
                $returnDiscountAmount = $transactionsForDiscountAmount->sum('amount');
                $returnCouponAmount = $transactionsForCouponAmount->sum('amount');

                $totaluomValue = $getSingleReturnCartOrder->returnCartOrderProduct->reduce(function ($acc, $curr) {
                    return $acc + ((int)$curr->product->uomValue * $curr->productQuantity);
                }, 0);

                $totalUnitQuantity = $getSingleReturnCartOrder->returnCartOrderProduct->sum('productQuantity');

                $getSingleReturnCartOrder->totalReturnPaidAmount = $returnPaidAmount;
                $getSingleReturnCartOrder->totalReturnVatAmount = $returnVatAmount;
                $getSingleReturnCartOrder->totalReturnDiscountAmount = $returnDiscountAmount;
                $getSingleReturnCartOrder->totalReturnCouponAmount = $returnCouponAmount;
                $getSingleReturnCartOrder->totaluomValue = $totaluomValue;
                $getSingleReturnCartOrder->totalUnitQuantity = $totalUnitQuantity;


                $converted = arrayKeysToCamelCase($getSingleReturnCartOrder->toArray());

                return response()->json($converted, 200);
            } catch (Exception $err) {
                echo $err;
                return response()->json(['error' => 'An error occurred during getting cart order. Please try again later.'], 500);
            }
        }
    }

    //get resend cart order list for reorder controller method
    public function getResendCartOrderList(Request $request): JsonResponse
    {
        try {
            $getSingleReturnCartOrder = ReturnCartOrder::where('returnCartOrderStatus', "RESEND")
                ->where('returnType', 'PRODUCT')
                ->get();

            $converted = arrayKeysToCamelCase($getSingleReturnCartOrder->toArray());

            return response()->json($converted, 200);
        } catch (Exception $err) {
            echo $err;
            return response()->json(['error' => 'An error occurred during getting cart order. Please try again later.'], 500);
        }
    }

    //update status returnCartOrder controller method
    public function updateReturnCartOrderStatus(Request $request, $id): JsonResponse
    {
        try {
            $returnCartOrderId = (int)$id;

            // is returnCartOrder exist or not validation
            $returnCartOrder = ReturnCartOrder::where('id', $returnCartOrderId)
                ->with('returnCartOrderProduct.product')
                ->first();
            if (!$returnCartOrder) {
                return response()->json(['error' => 'No Return Cart Order Found!'], 404);
            }

            // is cartOrder exist or not validation
            $cartOrder = CartOrder::with('customer')->where('id', $returnCartOrder->cartOrderId)->first();
            if (!$cartOrder) {
                return response()->json(['error' => 'No Cart Order Found!'], 404);
            }

            // already exist status validation
            if ($returnCartOrder->returnCartOrderStatus === $request->input('returnCartOrderStatus')) {
                return response()->json(['error' => "Already {$request->input('returnCartOrderStatus')}!"], 400);
            }

            // status validation
            if (($request->input('returnCartOrderStatus') === 'REJECTED') && $returnCartOrder->returnCartOrderStatus === "RECEIVED") {
                return response()->json(['error' => "already received!"], 400);
            }

            // status validation
            if (($request->input('returnCartOrderStatus') === 'REJECTED') && $returnCartOrder->returnCartOrderStatus === "REFUNDED") {
                return response()->json(['error' => "already refunded!"], 400);
            }

            // status validation
            if (($request->input('returnCartOrderStatus') === 'REJECTED') && $returnCartOrder->returnCartOrderStatus === "RESEND") {
                return response()->json(['error' => "already resend!"], 400);
            }

            // status validation
            if (($request->input('returnCartOrderStatus') !== $returnCartOrder->returnCartOrderStatus) && $returnCartOrder->returnCartOrderStatus === "REJECTED") {
                return response()->json(['error' => "already rejected!, cannot {$request->input('returnCartOrderStatus')}"], 400);
            }

            // status validation
            if ($request->input('returnCartOrderStatus') === 'REFUNDED' && $returnCartOrder->returnCartOrderStatus === "RESEND") {
                return response()->json(['error' => "already RESEND!"], 400);
            }

            // status validation
            if ($request->input('returnCartOrderStatus') === 'RESEND' && $returnCartOrder->returnCartOrderStatus === "REFUNDED") {
                return response()->json(['error' => "already REFUNDED!"], 400);
            }

            // status validation
            if (($request->input('returnCartOrderStatus') === 'REFUNDED' || $request->input('returnCartOrderStatus') === 'RESEND') && $returnCartOrder->returnCartOrderStatus !== "RECEIVED") {
                return response()->json(['error' => "Need to Receive First"], 400);
            }

            // status validation
            if ($request->input('returnCartOrderStatus') === 'RESENDED' && $returnCartOrder->returnCartOrderStatus === "RESENDED") {
                return response()->json(['error' => "already RESENDED!"], 400);
            }

            // status validation
            if (($request->input('returnCartOrderStatus') === 'RECEIVED' || $request->input('returnCartOrderStatus') === 'PENDING') && $returnCartOrder->returnCartOrderStatus === "REFUNDED" || $returnCartOrder->returnCartOrderStatus === "RESEND") {
                return response()->json(['error' => "Cannot {$request->input('returnCartOrderStatus')} because already {$returnCartOrder->returnCartOrderStatus} "], 400);
            }

            // status validation
            if (($request->input('returnCartOrderStatus') === 'RESENDED') && $returnCartOrder->returnCartOrderStatus !== "RECEIVED") {
                return response()->json(['error' => "Need to Receive First"], 400);
            }

            // status validation
            if (($request->input('returnCartOrderStatus') === 'RESENDED') && $returnCartOrder->returnCartOrderStatus !== "RESEND") {
                return response()->json(['error' => "Need to resend First"], 400);
            }

            // status validation
            if (($request->input('returnCartOrderStatus') === 'RECEIVED' || $request->input('returnCartOrderStatus') === 'PENDING' || $request->input('returnCartOrderStatus') === 'REFUNDED' || $request->input('returnCartOrderStatus') === 'RESEND') && $returnCartOrder->returnCartOrderStatus === "RESENDED") {
                return response()->json(['error' => "Cannot {$request->input('returnCartOrderStatus')} because already {$returnCartOrder->returnCartOrderStatus} "], 400);
            }

            // iterate over allProduct and calculate totalPurchase price
            $totalPurchasePriceOfReturnProduct = 0;
            foreach (collect($returnCartOrder->returnCartOrderProduct) as $index => $item) {
                $productPrice = $item['product']->productPurchasePrice * $item['productQuantity'];

                $totalPurchasePriceOfReturnProduct += $productPrice;
            }

            $manualPaymentData = ManualPayment::where('cartOrderId', $returnCartOrder->cartOrderId)
                ->with('paymentMethod')
                ->first();
            $subAccountIdForMainTransaction = $manualPaymentData->paymentMethod->subAccountId ?? 1;

            $date = new Carbon();
            // main business login implemented here
            if ($request->input('returnCartOrderStatus') === 'RECEIVED') {

                // goods received on return sale transaction create
                Transaction::create([
                    'date' => $date,
                    'debitId' => 3,
                    'creditId' => 9,
                    'amount' => takeUptoThreeDecimal((float)$totalPurchasePriceOfReturnProduct),
                    'particulars' => "Cost of sales reduce on return cart order #{$returnCartOrderId} of Cart Order #{$cartOrder->id}",
                    'type' => 'sale_return',
                    'relatedId' => $cartOrder->id,
                ]);

                // create return due transaction
                Transaction::create([
                    'date' => new Carbon(),
                    'debitId' => 8,
                    'creditId' => 4,
                    'amount' => takeUptoThreeDecimal($returnCartOrder->totalAmount),
                    'particulars' => "Due on return cart order #{$returnCartOrder->id}",
                    'type' => 'sale_return',
                    'relatedId' => $cartOrder->id,
                ]);


                // iterate through all products of this return sale invoice and increase the product quantity
                foreach (collect($returnCartOrder->returnCartOrderProduct) as $item) {
                    Product::where('id', (int)$item['productId'])
                        ->update([
                            'productQuantity' => DB::raw("productQuantity +  {$item['productQuantity']}"),
                        ]);
                }

                ReturnCartOrder::where('id', (int)$returnCartOrderId)
                    ->update([
                        'returnCartOrderStatus' => "RECEIVED"
                    ]);
            }
            if ($request->input('returnCartOrderStatus') === 'REFUNDED') {

                if ($returnCartOrder->returnType === 'PRODUCT') {
                    return response()->json(['error' => 'Customer need this product, not refund'], 400);
                }

                // totalReturnSaleAmount given on return sale transaction create
                Transaction::create([
                    'date' => $date,
                    'debitId' => 8,
                    'creditId' => (int)$subAccountIdForMainTransaction,
                    'amount' => takeUptoThreeDecimal((float)($returnCartOrder->totalAmount)),
                    'particulars' => "Cash paid on Sale return cart order #{$returnCartOrder->id} of sale Invoice #{$cartOrder->id}",
                    'type' => 'sale_return',
                    'relatedId' => $cartOrder->id,
                ]);

                //created for return coupon transaction
                if ($returnCartOrder->couponAmount > 0) {
                    Transaction::create([
                        'date' => $date,
                        'debitId' => 8,
                        'creditId' => 14,
                        'amount' => takeUptoThreeDecimal($returnCartOrder->couponAmount),
                        'particulars' => "Coupon Code on cart order #{$returnCartOrder->id}",
                        'type' => 'sale_return',
                        'relatedId' => $cartOrder->id,
                    ]);
                }

                // created return vat into transaction
                if ($returnCartOrder->totalVatAmount > 0) {
                    Transaction::create([
                        'date' => $date,
                        'debitId' => 15,
                        'creditId' => (int)$subAccountIdForMainTransaction,
                        'amount' => takeUptoThreeDecimal((float)$returnCartOrder->totalVatAmount),
                        'particulars' => "Vat Collected on return cart order #{$returnCartOrder->id}",
                        'type' => 'vat_return',
                        'relatedId' => $cartOrder->id
                    ]);
                }

                //created return discount into transaction
                if ($returnCartOrder->totalDiscountAmount > 0) {
                    Transaction::create([
                        'date' => $date,
                        'debitId' => (int)$subAccountIdForMainTransaction,
                        'creditId' => 14,
                        'amount' => takeUptoThreeDecimal((float)$returnCartOrder->totalDiscountAmount),
                        'particulars' => "Discount on return cart order #{$returnCartOrder->id}",
                        'type' => 'sale_return',
                        'relatedId' => $cartOrder->id
                    ]);
                }

                // decrease return cart order profit by return cart order calculated profit
                $returnCartOrderProfit = takeUptoThreeDecimal($returnCartOrder->totalAmount - $totalPurchasePriceOfReturnProduct);

                CartOrder::where('id', $returnCartOrder->cartOrderId)
                    ->update([
                        'profit' => DB::raw("profit - $returnCartOrderProfit"),
                    ]);

                ReturnCartOrder::where('id', (int)$returnCartOrderId)
                    ->update([
                        'returnCartOrderStatus' => "REFUNDED"
                    ]);
            }
            if ($request->input('returnCartOrderStatus') === 'RESEND') {

                if ($returnCartOrder->returnType === 'REFUND') {
                    return response()->json(['error' => 'Customer need to refund, not this product'], 40);
                }

                // totalReturnSaleAmount given on return sale transaction create
                Transaction::create([
                    'date' => $date,
                    'debitId' => 8,
                    'creditId' => (int)$subAccountIdForMainTransaction,
                    'amount' => takeUptoThreeDecimal((float)($returnCartOrder->totalAmount)),
                    'particulars' => "Cash paid on Sale return cart order #{$returnCartOrder->id} of sale Invoice #{$cartOrder->id}",
                    'type' => 'sale_return',
                    'relatedId' => $cartOrder->id,
                ]);

                // created return vat into transaction
                if ($returnCartOrder->totalVatAmount > 0) {
                    Transaction::create([
                        'date' => $date,
                        'debitId' => 15,
                        'creditId' => (int)$subAccountIdForMainTransaction,
                        'amount' => takeUptoThreeDecimal((float)$returnCartOrder->totalVatAmount),
                        'particulars' => "Vat Collected on return cart order #{$returnCartOrder->id}",
                        'type' => 'vat_return',
                        'relatedId' => $cartOrder->id
                    ]);
                }

                //created return discount into transaction
                if ($returnCartOrder->totalDiscountAmount > 0) {
                    Transaction::create([
                        'date' => $date,
                        'debitId' => (int)$subAccountIdForMainTransaction,
                        'creditId' => 14,
                        'amount' => takeUptoThreeDecimal((float)$returnCartOrder->totalDiscountAmount),
                        'particulars' => "Discount on return cart order #{$returnCartOrder->id}",
                        'type' => 'sale_return',
                        'relatedId' => $cartOrder->id
                    ]);
                }

                // decrease return cart order profit by return cart order calculated profit
                $returnCartOrderProfit = takeUptoThreeDecimal($returnCartOrder->totalAmount - $totalPurchasePriceOfReturnProduct);

                CartOrder::where('id', $returnCartOrder->cartOrderId)
                    ->update([
                        'profit' => DB::raw("profit - $returnCartOrderProfit"),
                    ]);

                $totalAmount = $returnCartOrder->totalAmount;
                $couponAmount = $returnCartOrder->couponAmount;

                ReturnCartOrder::where('id', (int)$returnCartOrderId)
                    ->update([
                        'returnCartOrderStatus' => "RESEND",
                        'couponAmount' => 0,
                        'totalAmount' => takeUptoThreeDecimal((float)$totalAmount + $couponAmount)
                    ]);
            }
            if ($request->input('returnCartOrderStatus') === 'REJECTED') {
                ReturnCartOrder::where('id', (int)$returnCartOrderId)
                    ->update([
                        'returnCartOrderStatus' => "REJECTED"
                    ]);
            }

            $updatedReturnCartOrder = ReturnCartOrder::where('id', $returnCartOrderId)->first();

            $this->MailStructure->returnCartOrderStatusChange($cartOrder->customer, $updatedReturnCartOrder->toArray());

            $converted = arrayKeysToCamelCase($updatedReturnCartOrder->toArray());
            return response()->json($converted, 201);
        } catch (Exception $err) {
            return response()->json(['error' => 'An error occurred during updating return cart order status. Please try again later.', $err->getMessage()], 500);
        }
    }
}
