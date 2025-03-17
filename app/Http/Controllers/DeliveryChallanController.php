<?php

namespace App\Http\Controllers;

use App\Models\DeliveryChallan;
use App\Models\DeliveryChallanProduct;
use App\Models\Product;
use App\Models\SaleInvoice;
use App\Models\SaleInvoiceProduct;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeliveryChallanController extends Controller
{

    //generate delivery challan no start like ch-001
    public function createDeliveryChallan(Request $request): JsonResponse
    {
        DB::beginTransaction();
        try {

            // Check if the sale invoice exists
            $saleInvoice = SaleInvoice::find($request->input('saleInvoiceId'));
            if (!$saleInvoice) {
                return response()->json(['error' => 'Sale invoice not found'], 404);
            }

            // Generate and find the delivery challan
            $deliveryChallan = DeliveryChallan::where('saleInvoiceId', $request->input('saleInvoiceId'))->get();

            if ($deliveryChallan) {
                //delivery chalan product
                $totalQuantity = 0;
                foreach ($deliveryChallan as $challan) {
                    $deliveryChallanProduct = DeliveryChallanProduct::where('deliveryChallanId', $challan->challanNo)->get();
                    //calculate the total quantity of products in delivery challan
                    foreach ($deliveryChallanProduct as $product) {
                        //sum the quantity of products in delivery challan and given input quantity
                        foreach ($request->input('challanProduct') as $inputProduct) {
                            if ($product->productId == $inputProduct['productId']) {
                                $totalQuantity += (int)$product->quantity + (int)$inputProduct['productQty'];
                            }
                        }
                    }
                }

                //check the sale invoice product quantity
                $saleInvoiceProduct = SaleInvoiceProduct::where('invoiceId', $request->input('saleInvoiceId'))->get();
                if (!$saleInvoiceProduct || (int)$totalQuantity > (int)$saleInvoiceProduct[0]->productQuantity) {
                    return response()->json(['error' => 'Delivery challan quantity is greater than sale invoice quantity'], 404);
                }
            }
            // Create the delivery challan
            $createDeliveryChallan = DeliveryChallan::create([
                'saleInvoiceId' => $request->input('saleInvoiceId'),
                'challanNo' => $this->generateDeliveryChallanNo()->original['challanNo'],
                'challanDate' => $request->input('challanDate'),
                'challanNote' => $request->input('challanNote'),
                'vehicleNo' => $request->input('vehicleNo'),
            ]);

            foreach ($request->input('challanProduct') as $product) {
                DeliveryChallanProduct::create([
                    'deliveryChallanId' => $createDeliveryChallan->challanNo,
                    'productId' => $product['productId'],
                    'quantity' => $product['productQty'],
                ]);
            }

            $converted = arrayKeysToCamelCase($createDeliveryChallan->toArray());
            DB::commit();
            return response()->json($converted, 200);
        } catch (Exception $e) {
            echo $e;
            DB::rollBack();
            return response()->json(['error' => $e], 500);
        }
    }

    //create delivery challan

    public function generateDeliveryChallanNo(): JsonResponse
    {
        $lastChallan = DeliveryChallan::latest()->first();
        if (!$lastChallan) {
            $challanNo = 'ch-001';
        } else {
            // Extract the numeric part of the last challanNo and increment
            $lastChallanNumber = (int)substr($lastChallan->challanNo, 3);
            $nextChallanNumber = $lastChallanNumber + 1;

            // Format the next challanNo with leading zeros
            $challanNo = 'ch-' . sprintf('%03d', $nextChallanNumber);
        }

        return response()->json(['challanNo' => $challanNo]);
    }

    //get all delivery challan
    public function getAllDeliveryChallan(Request $request): JsonResponse
    {
        try {
            if ($request->query('query') === 'all') {
                $deliveryChallan = DeliveryChallan::with('saleInvoice', 'deliveryChallanProduct', 'deliveryChallanProduct.product',)
                    ->where('status', 'true')
                    ->orderBy('id', 'desc')
                    ->get();

                $converted = arrayKeysToCamelCase($deliveryChallan->toArray());
                return response()->json($converted, 200);
            } else if ($request->query('query') === 'search') {
                $pagination = getPagination($request->query());

                $allDeliveryChallan = DeliveryChallan::with('saleInvoice', 'deliveryChallanProduct', 'deliveryChallanProduct.product',)
                    ->where('status', 'true')
                    ->orWhere('challanNo', 'like', '%' . $request->query('key') . '%')
                    ->orWhere('vehicleNo', 'like', '%' . $request->query('key') . '%')
                    ->orWhere('saleInvoiceId', 'like', '%' . $request->query('key') . '%')
                    ->orderBy('id', 'desc')
                    ->skip($pagination['skip'])
                    ->take($pagination['limit'])
                    ->get();

                $totalDeliveryChallan = DeliveryChallan::where('status', 'true')
                    ->orWhere('challanNo', 'like', '%' . $request->query('key') . '%')
                    ->orWhere('vehicleNo', 'like', '%' . $request->query('key') . '%')
                    ->orWhere('saleInvoiceId', 'like', '%' . $request->query('key')
                        . '%')->count();

                $converted = arrayKeysToCamelCase($allDeliveryChallan->toArray());
                $result = [
                    'getAllDeliveryChallan' => $converted,
                    'totalDeliveryChallan' => $totalDeliveryChallan
                ];
                return response()->json($result, 200);
            } else if ($request->query()) {
                $pagination = getPagination($request->query());

                $allDeliveryChallan = DeliveryChallan::with('saleInvoice', 'deliveryChallanProduct', 'deliveryChallanProduct.product',)
                    ->when($request->query('status'), function ($query) use ($request) {
                        return $query->where('status', $request->query('status'));
                    })

                    ->orderBy('id', 'desc')
                    ->skip($pagination['skip'])
                    ->take($pagination['limit'])
                    ->get();

                $totalDeliveryChallan = DeliveryChallan::when($request->query('status'), function ($query) use ($request) {
                    return $query->where('status', $request->query('status'));
                })->count();

                $converted = arrayKeysToCamelCase($allDeliveryChallan->toArray());
                $result = [
                    'getAllDeliveryChallan' => $converted,
                    'totalDeliveryChallan' => $totalDeliveryChallan
                ];
                return response()->json($result, 200);
            }
        } catch (Exception $e) {
            return response()->json(['error' => 'An error occurred while fetching data from the database'], 500);
        }
    }

    public function getSingleDeliveryChallan($id): JsonResponse
    {
        try {
            $deliveryChallan = DeliveryChallan::with('saleInvoice', 'deliveryChallanProduct', 'deliveryChallanProduct.product',)
                ->where('id', $id)
                ->first();

            if (!$deliveryChallan) {
                return response()->json(['error' => 'Delivery challan not found'], 404);
            }

            $converted = arrayKeysToCamelCase($deliveryChallan->toArray());
            return response()->json($converted, 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'An error occurred while fetching data from the database'], 500);
        }
    }

    public function deleteDeliveryChallan(Request $request ,$id): JsonResponse
    {
        try {
            $deliveryChallan = DeliveryChallan::find($id);
            if (!$deliveryChallan) {
                return response()->json(['error' => 'Delivery challan not found'], 404);
            }

            $deliveryChallan->status = $request->input('status');
            $deliveryChallan->save();

            return response()->json(['message' => 'Delivery challan deleted successfully'], 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'An error occurred while deleting delivery challan'], 500);
        }
    }
}
