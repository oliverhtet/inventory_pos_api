<?php

namespace App\Http\Controllers;

use App\Models\DeliveryFee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


class DeliveryFeeController extends Controller
{
    public function createDeliveryFee(Request $request): JsonResponse
    {
        try {
            $deliveryFee = DeliveryFee::create([
                'deliveryArea' => $request->deliveryArea,
                'deliveryFee' => $request->deliveryFee,
            ]);
            if (!$deliveryFee) {
                return response()->json([
                    'error' => 'Delivery Fee not created. Please try again later.'
                ], 409);
            }
            $converted = arrayKeysToCamelCase($deliveryFee->toArray());
            return response()->json($converted, 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'An error occurred during creating Delivery Fee. Please try again later.'
            ], 409);
        }
    }

    public function getAllDeliveryFees(): JsonResponse
    {
        try {
            $deliveryFees = DeliveryFee::where('status', 'true')
                ->orderBy('id', 'desc')
                ->get();
            $converted = arrayKeysToCamelCase($deliveryFees->toArray());
            return response()->json($converted, 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'An error occurred during fetching Delivery Fees. Please try again later.'
            ], 409);
        }
    }


    public function updateDeliveryFee(Request $request, $id): JsonResponse
    {
        try {
            $deliveryFee = DeliveryFee::find((int)$id);
            $update = $deliveryFee->update([
                'deliveryArea' => $request->deliveryArea ?? $deliveryFee->deliveryArea,
                'deliveryFee' => $request->deliveryFee ?? $deliveryFee->deliveryFee,
            ]);
            if (!$update) {
                return response()->json([
                    'error' => 'Delivery Fee not updated. Please try again later.'
                ], 409);
            }
            $converted = arrayKeysToCamelCase($deliveryFee->toArray());
            return response()->json($converted, 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred during updating Delivery Fee. Please try again later.'
            ], 409);
        }
    }

    public function deleteDeliveryFee(Request $request, $id): JsonResponse
    {
        try {
            $deliveryFee = DeliveryFee::find($id);
            DeliveryFee::where("id", $id)->update([
                'status' => $request->status,
            ]);
            if (!$deliveryFee) {
                return response()->json([
                    'error' => 'Delivery Fee not deleted. Please try again later.'
                ], 409);
            }
            return response()->json([
                'message' => 'Delivery Fee Deleted Successfully',
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred during deleting Delivery Fee. Please try again later.'
            ], 409);
        }
    }
}
