<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\ShippingCharge;

class ShippingChargeController extends Controller
{
    public function createSingleShippingCharge(Request $request): JsonResponse
    {
        if ($request->query('query') === 'createmany') {
            try {
                $shippingChargeData = $request->json()->all();
                $shippingCharge = collect($shippingChargeData)->map(function ($item) {
                    $shipping = ShippingCharge::where('Destination', $item['Destination'])->first();
                    if ($shipping) {
                        return null;
                    }
                    return $item;
                })->filter(function ($item) {
                    return $item !== null;
                })->toArray();

                //if all shippingCharge already exists
                if (count($shippingCharge) === 0) {
                    return response()->json(['error' => 'All products already exists.'], 500);
                }

                $createdShippingCharge = collect($shippingCharge)->map(function ($item) {
                    return ShippingCharge::firstOrCreate($item);
                });

                $result = [
                    'count' => count($createdShippingCharge),
                ];

                return response()->json($result, 201);
            } catch (Exception $err) {
                return response()->json(['error' => 'An error occurred during creating many ShippingCharge. Please try again later.'], 500);
            }
        } else if ($request->query('query') === 'deletemany') {
            try {
                $data = json_decode($request->getContent(), true);
                $deletedShippingCharge = ShippingCharge::destroy($data);

                $deletedCounted = [
                    'count' => $deletedShippingCharge,
                ];
                return response()->json($deletedCounted, 200);
            } catch (Exception $err) {
                return response()->json(['error' => 'An error occurred during deleting many ShippingCharge. Please try again later.'], 500);
            }
        } else {
            try {
                $createdShippingCharge = ShippingCharge::create([
                    'Destination' => $request->input('Destination'),
                    'charge' => $request->input('charge'),
                ]);

                $converted = arrayKeysToCamelCase($createdShippingCharge->toArray());
                return response()->json($converted, 201);
            } catch (Exception $err) {
                return response()->json(['error' => 'An error occurred during creating ShippingCharge. Please try again later.'], 500);
            }
        }
    }

    public function getAllShippingCharge(Request $request): JsonResponse
    {
        if ($request->query('query') === 'all') {
            try {
                $shippingCharge = ShippingCharge::where('status', 'true')->orderBy('id', 'desc')->get();
                $converted = arrayKeysToCamelCase($shippingCharge->toArray());
                return response()->json($converted, 200);
            } catch (Exception $err) {
                return response()->json(['error' => 'An error occurred during getting all ShippingCharge. Please try again later.'], 500);
            }
        } else {
            try {
                $pagination = getPagination($request->query());
                $shippingCharge = ShippingCharge::where('status', $request->query('status'))
                    ->orderBy('id', 'desc')
                    ->skip($pagination['skip'])
                    ->take($pagination['limit'])
                    ->get();
                $converted = arrayKeysToCamelCase($shippingCharge->toArray());
                return response()->json($converted, 200);
            } catch (Exception $err) {
                return response()->json(['error' => 'An error occurred during getting all ShippingCharge. Please try again later.'], 500);
            }
        }
    }

    public function getSingleShippingCharge($id): JsonResponse
    {
        try {
            $shippingCharge = ShippingCharge::with('product')->findOrFail($id);
            $converted = arrayKeysToCamelCase($shippingCharge->toArray());
            return response()->json($converted, 200);
        } catch (Exception $err) {
            return response()->json(['error' => 'An error occurred during getting single ShippingCharge. Please try again later.'], 500);
        }
    }

    public function updateSingleShippingCharge(Request $request, $id): JsonResponse
    {
        try {
            $shippingCharge = ShippingCharge::findOrFail($id);
            $shippingCharge->update([
                'Destination' => $request->input('Destination') ?? null,
                'charge' => $request->input('charge') ?? null,
            ]);

            $converted = arrayKeysToCamelCase($shippingCharge->toArray());
            return response()->json($converted, 200);
        } catch (Exception $err) {
            return response()->json(['error' => 'An error occurred during updating single ShippingCharge. Please try again later.'], 500);
        }
    }

    public function deleteSingleShippingCharge(Request $request, $id): JsonResponse
    {
        try {
            $shippingCharge = ShippingCharge::where('id', (int)$id)
                ->update([
                    'status' => $request->input('status'),
                ]);

            if (!$shippingCharge) {
                return response()->json(['error' => 'ShippingCharge not found.'], 404);
            }

            return response()->json(['message' => 'ShippingCharge has been deleted successfully.'], 200);
        } catch (Exception $err) {
            return response()->json(['error' => 'An error occurred during deleting single ShippingCharge. Please try again later.'], 500);
        }
    }
}
