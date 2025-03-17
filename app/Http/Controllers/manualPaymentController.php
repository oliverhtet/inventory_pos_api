<?php

namespace App\Http\Controllers;

use Exception;
use Carbon\Carbon;
use App\Models\Product;
use App\Models\Discount;
use App\Models\CartOrder;
use App\Models\ProductVat;
use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Models\ManualPayment;
use App\Models\PaymentMethod;
use App\Models\CartOrderProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class manualPaymentController extends Controller
{
    public function createManualPayment(Request $request): JsonResponse
    {
        $request->validate([
            'customerId' => 'required',
            'cartOrderId' => 'required',
            'amount' => 'required',
            'CustomerAccount' => 'required',
            'CustomerTransactionId' => 'required',
        ]);

        $createManualPayment = ManualPayment::create([
            'paymentMethodId' => $request->input('paymentMethodId'),
            'customerId' => $request->input('customerId'),
            'cartOrderId' => $request->input('cartOrderId'),
            'amount' => $request->input('amount'),
            'manualTransactionId' => $this->manualTransaction(10),
            'CustomerAccount' => $request->input('CustomerAccount'),
            'CustomerTransactionId' => $request->input('CustomerTransactionId'),
        ]);

        $converted = arrayKeysToCamelCase($createManualPayment->toArray());
        return response()->json($converted, 201);
    }

    //get all manual payment
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

    //get manual payment by id
    public function getAllManualPayment(Request $request): JsonResponse
    {
        if ($request->query('query') === "all") {
            try {
                $manualPayment = ManualPayment::with('customer:id,username', 'cartOrder:id,paidAmount,isPaid', 'paymentMethod')
                    ->orderBy('id', 'desc')
                    ->where('status', 'true')
                    ->get();

                $converted = arrayKeysToCamelCase($manualPayment->toArray());
                return response()->json($converted, 200);
            } catch (Exception $err) {
                return response()->json(['error' => 'An error occurred during getting Manual Payment!'], 409);
            }
        } else if ($request->query('query') === "info") {
            try {
                //calculate total amount
                $totalAmount = ManualPayment::where('status', 'true')->sum('amount');
                //calculate total manual payment
                $totalManualPayment = ManualPayment::where('status', $request->query('status'))->count();

                return response()->json([
                    'totalAmount' => takeUptoThreeDecimal($totalAmount),
                    'totalManualPayment' => $totalManualPayment,
                ], 200);
            } catch (Exception $err) {
                return response()->json(['error' => 'An error occurred during getting Manual Payment!'], 409);
            }
        } else if ($request->query('query') === "search") {
            try {
                $pagination = getPagination($request->query());

                $searchManualPayment = ManualPayment::with('customer:id,username', 'cartOrder:id,paidAmount,isPaid', 'paymentMethod')
                    ->where('CustomerAccount', 'LIKE', '%' . $request->query('key') . '%')
                    ->orWhere('CustomerTransactionId', 'LIKE', '%' . $request->query('key') . '%')
                    ->orWhere('cartOrderId', 'LIKE', '%' . $request->query('key') . '%')
                    ->orWhere('CustomerTransactionId', 'LIKE', '%' . $request->query('key') . '%')
                    ->orWhere('customerId', 'LIKE', '%' . $request->query('key') . '%')
                    ->orWhere('created_at', 'LIKE', '%' . $request->query('key') . '%')
                    ->Where('status', 'true')
                    ->orderBy('id', 'desc')
                    ->skip($pagination['skip'])
                    ->take($pagination['limit'])
                    ->get();

                $total = ManualPayment::where('CustomerAccount', 'LIKE', '%' . $request->query('CustomerAccount') . '%')
                    ->orWhere('CustomerTransactionId', 'LIKE', '%' . $request->query('CustomerTransactionId') . '%')
                    ->orWhere('customerId', 'LIKE', '%' . $request->query('customerId') . '%')
                    ->Where('status', 'true')
                    ->skip($pagination['skip'])
                    ->take($pagination['limit'])
                    ->count();

                $converted = arrayKeysToCamelCase($searchManualPayment->toArray());

                return response()->json([
                    'getAllManualPayment' => $converted,
                    'totalManualPayment' => $total,
                ], 200);
            } catch (Exception $err) {
                return response()->json(['error' => 'An error occurred during getting Manual Payment!'], 409);
            }
        } else if ($request->query('query') === 'report'){
            try{
                $allManualPayment = ManualPayment::with('customer:id,username', 'cartOrder:id,paidAmount,isPaid', 'paymentMethod')
                    ->when($request->query('paymentMethodId'), function ($query) use ($request) {
                        return $query->where('paymentMethodId', $request->query('paymentMethodId'));
                    })
                    ->When($request->query('customerId'), function ($query) use ($request) {
                        return $query->where('customerId', $request->query('customerId'));
                    })
                    ->when($request->query('startDate') && $request->query('endDate'), function ($query) use ($request) {
                        return $query->where('date', '>=', Carbon::createFromFormat('Y-m-d', $request->query('startDate')))
                            ->where('date', '<=', Carbon::createFromFormat('Y-m-d', $request->query('endDate')));
                    })
                    ->orderBy('id', 'desc')
                    ->get();
           
                    $totalAmount = ManualPayment::where('status', 'true')
                        ->when($request->query('paymentMethodId'), function ($query) use ($request) {
                            return $query->where('paymentMethodId', $request->query('paymentMethodId'));
                        })
                        ->When($request->query('customerId'), function ($query) use ($request) {
                            return $query->where('customerId', $request->query('customerId'));
                        })
                        ->when($request->query('fromDate'), function ($query) use ($request) {
                            return $query->whereDate('created_at', '>=', $request->query('fromDate'));
                        })
                        ->sum('amount');

                        $aggregations = [
                            '_count' => [
                                'id' => $allManualPayment->count(),
                            ],
                            '_sum' => [
                                'totalAmount' => takeUptoThreeDecimal($totalAmount),
                            ],
                        ];

                    $converted = arrayKeysToCamelCase($allManualPayment->toArray());
                    return response()->json([
                        'aggregations' => $aggregations,
                        'getAllManualPayment' => $converted,
                        
                    ], 200);

            } catch (Exception $err) {
                echo $err;
                return response()->json(['error' => 'An error occurred during getting Manual Payment!'], 409);
            }
        } else if ($request->query()) {
            try {
                $pagination = getPagination($request->query());

                $searchManualPayment = ManualPayment::with('customer:id,username', 'cartOrder:id,paidAmount,isPaid', 'paymentMethod')
                    ->when($request->query('paymentMethodId'), function ($query) use ($request) {
                        return $query->where('paymentMethodId', explode(',', $request->query('paymentMethodId')));
                    })
                    ->When($request->query('customerId'), function ($query) use ($request) {
                        return $query->where('customerId', explode(',', $request->query('customerId')));
                    })
                    ->When($request->query('status'), function ($query) use ($request) {
                        return $query->where('status', explode(',', $request->query('status')));
                    })
                    ->when($request->query('fromDate'), function ($query) use ($request) {
                        return $query->whereDate('created_at', '>=', $request->query('fromDate'));
                    })
                    ->orderBy('id', 'desc')
                    ->skip($pagination['skip'])
                    ->take($pagination['limit'])
                    ->get();

                $total = ManualPayment::Where('status', $request->query('status'))
                    ->count();

                $converted = arrayKeysToCamelCase($searchManualPayment->toArray());

                return response()->json([
                    'getAllManualPayment' => $converted,
                    'totalManualPayment' => $total,
                ], 200);
            } catch (Exception $err) {
                return response()->json(['error' => 'An error occurred during getting Manual Payment!'], 409);
            }
        } else {
            return response()->json(['error' => 'Invalid Query!'], 409);
        }
    }

    //total amount and total manual payment by paymentMethodId
    public function totalAmountByPaymentMethodId(Request $request, $id): JsonResponse
    {
        try {
            $totalAmount = ManualPayment::where('paymentMethodId', $id)
                ->where('status', 'true')
                ->sum('amount');

            $totalManualPayment = ManualPayment::where('paymentMethodId', $id)
                ->where('status', 'true')
                ->count();

            return response()->json([
                'totalAmount' => $totalAmount,
                'totalManualPayment' => $totalManualPayment,
            ], 200);
        } catch (Exception $err) {
            return response()->json(['error' => 'An error occurred during getting Manual Payment!'], 409);
        }
    }

    //getSingleManualPayment
    public function getSingleManualPayment(Request $request, $id): JsonResponse
    {
        try {
            $manualPayment = ManualPayment::with('customer:id,username', 'cartOrder', 'paymentMethod')
                ->orderBy('id', 'desc')
                ->where('id', $id)
                ->first();

            if (!$manualPayment) {
                return response()->json(['error' => 'Manual Payment not found!'], 404);
            }

            unset($manualPayment->customer->password);
            unset($manualPayment->cartOrder->profit);

            $converted = arrayKeysToCamelCase($manualPayment->toArray());
            return response()->json($converted, 200);
        } catch (Exception $err) {
            return response()->json(['error' => 'An error occurred during getting Manual Payment!'], 409);
        }
    }

    //verified manual payment
    public function verifiedManualPayment(Request $request, $id): JsonResponse
    {
        try {
            //check manual payment is exist
            $manualPayment = ManualPayment::where('id', $id)
                ->with('paymentMethod')
                ->first();

            if (!$manualPayment) {
                return response()->json(['error' => 'Manual Payment not found!'], 404);
            }

            $subAccountIdForMainTransaction = $manualPayment->paymentMethod->subAccountId;

            // validation
            if ($manualPayment->paymentMethodId === 1) {
                return response()->json(['error' => 'No need to accept cash on delivery payment. It will be automatically accepted after delivered'], 400);
            }

            if ($manualPayment->isVerified === 'Accept') {
                return response()->json(['error' => 'already accepted'], 400);
            }

            if ($manualPayment->isVerified === 'Reject') {
                return response()->json(['error' => 'already accepted'], 400);
            }

            // get the cart order
            $cartOrderData = CartOrder::where('id', $manualPayment->cartOrderId)->first();

            if (!$cartOrderData) {
                return response()->json(['error' => 'No cart order Invoice Found!'], 404);
            }

            // Get all the cart order products
            $cartOrderProducts = CartOrderProduct::where('invoiceId', $cartOrderData->id)
                ->get();

            // Get all the sale products
            $allCartProducts = collect($cartOrderProducts)->map(function ($item) {

                $data = Product::where('id', (int)$item['productId'])
                    ->first();

                // vat
                $productVat = 0;
                $productVatId = $data->productVatId ?? null;
                $productVatData = ProductVat::where('id', $productVatId)->where('status', 'true')
                    ->orderBy('id', 'desc')->first();

                if ($productVatData) {
                    $productVat = $productVatData->percentage;
                }

                // discount
                $discountType = null;
                $discount = 0;
                $discountId = $data->discountId ?? null;
                $discountData = Discount::where('id', $discountId)->where('status', 'true')->first();

                if ($discountData) {
                    $discountType = $discountData->type;
                    $discount = $discountData->value;
                }

                $data->productVat = $productVat;
                $data->discountType = $discountType;
                $data->discount = $discount;
                return $data;
            });

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
            $dueAmount = $cartOrderData->due;

            if ($request->input('isVerified') === "Accept") {
                if ($dueAmount > 0) {
                    Transaction::create([
                        'date' => new Carbon(),
                        'debitId' => (int)$subAccountIdForMainTransaction,
                        'creditId' => 8,
                        'amount' => takeUptoThreeDecimal((float)$dueAmount),
                        'particulars' => "Cash receive on cart order #{$cartOrderData->id}",
                        'type' => 'sale',
                        'relatedId' => $cartOrderData->id,
                    ]);
                }

                // created vat into transaction
                if ($totalVat > 0) {
                    Transaction::create([
                        'date' => new Carbon(),
                        'debitId' => (int) $subAccountIdForMainTransaction,
                        'creditId' => 15,
                        'amount' => takeUptoThreeDecimal((float)$totalVat),
                        'particulars' => "Vat Collected on  cart order #{$cartOrderData->id}",
                        'type' => 'vat',
                        'relatedId' => $cartOrderData->id,
                    ]);
                }

                //created discount into transaction
                if ($totalDiscount > 0) {
                    Transaction::create([
                        'date' => new Carbon(),
                        'debitId' => 14,
                        'creditId' => (int)$subAccountIdForMainTransaction,
                        'amount' => takeUptoThreeDecimal((float)$totalDiscount),
                        'particulars' => "Discount on cart order #{$cartOrderData->id}",
                        'type' => 'sale',
                        'relatedId' => $cartOrderData->id,
                    ]);
                }

                cartOrder::where('id', $manualPayment->cartOrderId)->update([
                    'paidAmount' => takeUptoThreeDecimal($dueAmount),
                    'isPaid' => "true",
                    'due' => takeUptoThreeDecimal($cartOrderData->totalAmount - $cartOrderData->due),
                ]);

                ManualPayment::where('id', $id)->update([
                    'isVerified' => $request->input('isVerified'),
                ]);
            }

            ManualPayment::where('id', $id)->update([
                'isVerified' => $request->input('isVerified'),
            ]);

            return response()->json(['success' => 'Manual Payment verified successfully!'], 200);
        } catch (Exception $err) {
            return response()->json(['error' => 'An error occurred during getting Manual Payment!'], 409);
        }
    }
}
