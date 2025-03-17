<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\ShippingTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShippingTimeController extends Controller
{
    //

    public function createSingleShippingTime(Request $request): JsonResponse
    {
        if ($request->query('query') === 'createmany') {
            try {
                $shippingTimeData = $request->json()->all();
                $shippingTime = collect($shippingTimeData)->map(function ($item) {
                    $shipping = ShippingTime::where('Destination', $item['Destination'])->first();
                    if ($shipping) {
                        return null;
                    }
                    return $item;
                })->filter(function ($item) {
                    return $item !== null;
                })->toArray();

                //if all shippingCharge already exists
                if (count($shippingTime) === 0) {
                    return response()->json(['error' => 'All products already exists.'], 500);
                }

                $createdShippingTime = collect($shippingTime)->map(function ($item) {
                    return ShippingTime::firstOrCreate($item);
                });

                $result = [
                    'count' => count($createdShippingTime),
                ];

                return response()->json($result, 201);
            } catch (Exception $err) {
                return response()->json(['error' => 'An error occurred during creating many ShippingTime. Please try again later.'], 500);
            }
        } else if ($request->query('query') === 'deletemany') {
            try {

                $data = json_decode($request->getContent(), true);
                $deletedShippingTime = ShippingTime::destroy($data);

                $deletedCounted = [
                    'count' => $deletedShippingTime,
                ];
                return response()->json($deletedCounted, 200);
            } catch (Exception $err) {
                return response()->json(['error' => 'An error occurred during deleting many ShippingTime. Please try again later.'], 500);
            }
        } else {
            try {
                $createdShippingTime = ShippingTime::create([
                    'Destination' => $request->input('Destination'),
                    'EstimatedTimeDays' => $request->input('EstimatedTimeDays'),
                ]);

                $converted = arrayKeysToCamelCase($createdShippingTime->toArray());
                return response()->json($converted, 201);
            } catch (Exception $err) {
                return response()->json(['error' => 'An error occurred during creating ShippingTime. Please try again later.'], 500);
            }
        }
    }

    public function getAllShippingTime(Request $request): JsonResponse
    {
        if ($request->query('query') === 'all') {
            try {
                $shippingTime = ShippingTime::where('status', 'true')->orderBy('id', 'desc')->get();
                $converted = arrayKeysToCamelCase($shippingTime->toArray());
                return response()->json($converted, 200);
            } catch (Exception $err) {
                return response()->json(['error' => 'An error occurred during getting all ShippingTime. Please try again later.'], 500);
            }
        } else {
            try {
                $pagination = getPagination($request->query());
                $shippingTime = ShippingTime::where('status', $request->query('status'))
                    ->orderBy('id', 'desc')
                    ->skip($pagination['skip'])
                    ->take($pagination['limit'])
                    ->get();
                $converted = arrayKeysToCamelCase($shippingTime->toArray());
                return response()->json($converted, 200);
            } catch (Exception $err) {
                return response()->json(['error' => 'An error occurred during getting all ShippingTime. Please try again later.'], 500);
            }
        }
    }

    public function getSingleShippingTime($id): JsonResponse
    {
        try {
            $shippingTime = ShippingTime::with('product')->findOrFail($id);
            $converted = arrayKeysToCamelCase($shippingTime->toArray());
            return response()->json($converted, 200);
        } catch (Exception $err) {
            echo $err;
            return response()->json(['error' => 'An error occurred during getting a single ShippingTime. Please try again later.'], 500);
        }
    }

    public function updateSingleShippingTime(Request $request, $id): JsonResponse
    {
        try {
            $shippingTime = ShippingTime::findOrFail($id);
            $shippingTime->update([
                'Destination' => $request->input('Destination') ?? $shippingTime->Destination,
                'EstimatedTimeDays' => $request->input('EstimatedTimeDays') ?? $shippingTime->EstimatedTimeDays,
            ]);

            $converted = arrayKeysToCamelCase($shippingTime->toArray());
            return response()->json($converted, 200);
        } catch (Exception $err) {
            return response()->json(['error' => 'An error occurred during updating a single ShippingTime. Please try again later.'], 500);
        }
    }

    public function deleteSingleShippingTime(Request $request, $id): JsonResponse
    {
        try {
            $shippingTime = ShippingTime::where('id', (int)$id)
                ->update([
                    'status' => $request->input('status'),
                ]);

            if (!$shippingTime) {
                return response()->json(['error' => 'ShippingTime not found.'], 404);
            }

            return response()->json(['success' => 'ShippingTime deleted successfully.'], 200);
        } catch (Exception $err) {
            return response()->json(['error' => 'An error occurred during deleting a single ShippingTime. Please try again later.'], 500);
        }
    }
}
