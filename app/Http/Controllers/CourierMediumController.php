<?php

namespace App\Http\Controllers;

use App\Models\CourierMedium;
use App\Models\SubAccount;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourierMediumController extends Controller
{
    public function createSingleCourierMedium(Request $request): JsonResponse
    {
        if ($request->query('query') === 'deletemany') {
            try {

                $data = json_decode($request->getContent(), true);
                $deletedCourierMedium = CourierMedium::destroy($data);

                $deletedCounted = [
                    'count' => $deletedCourierMedium,
                ];
                return response()->json($deletedCounted, 200);
            } catch (Exception $err) {
                return response()->json(['error' => 'An error occurred during deleting many Courier Medium. Please try again later.'], 500);
            }
        } else if ($request->query('query') === 'createmany') {
            try {
                $courierData = $request->json()->all();

                $createdCourierMedium = collect($courierData)->map(function ($courier) {

                    $createdSubAccount = SubAccount::create([
                        'name' => $courier['courierMediumName'],
                        'accountId' => 1,
                    ]);

                    if ($createdSubAccount) {
                        return CourierMedium::create([
                            'courierMediumName' => $courier['courierMediumName'],
                            'address' => $courier['address'],
                            'phone' => $courier['phone'],
                            'email' => $courier['email'],
                            'type' => $courier['type'],
                            'subAccountId' => $createdSubAccount->id
                        ]);
                    }
                });

                $converted = arrayKeysToCamelCase($createdCourierMedium->toArray());
                return response()->json($converted, 201);
            } catch (Exception $err) {
                return response()->json(['error' => 'An error occurred during creating many Courier medium. Please try again later.'], 500);
            }
        } else {
            try {

                $createdSubAccount = SubAccount::create([
                    'name' => $request->input('courierMediumName'),
                    'accountId' => 1,
                ]);

                if ($createdSubAccount) {
                    $createdCourierMedium = CourierMedium::create([
                        'courierMediumName' => $request->input('courierMediumName'),
                        'address' => $request->input('address'),
                        'phone' => $request->input('phone'),
                        'email' => $request->input('email'),
                        'type' => $request->input('type'),
                        'subAccountId' => $createdSubAccount->id
                    ]);
                }

                $converted = arrayKeysToCamelCase($createdCourierMedium->toArray());
                return response()->json($converted, 201);
            } catch (Exception $err) {
                return response()->json(['error' => 'An error occurred during creating Courier medium. Please try again later.'], 500);
            }
        }
    }

    public function getAllCourierMedium(Request $request): JsonResponse
    {
        if ($request->query('query') === 'all') {
            try {
                $courier = CourierMedium::orderBy('id', 'desc')
                    ->where('status', 'true')
                    ->get();

                $converted = arrayKeysToCamelCase($courier->toArray());
                return response()->json($converted, 200);
            } catch (Exception $err) {
                return response()->json(['error' => 'An error occurred during getting all Courier medium. Please try again later.'], 500);
            }
        } else if ($request->query('query') === 'search') {
            try {
                $pagination = getPagination($request->query());
                $courier = CourierMedium::where('courierMediumName', 'LIKE', '%' . $request->query('key') . '%')
                    ->orWhere('address', 'LIKE', '%' . $request->query('key') . '%')
                    ->orWhere('phone', 'LIKE', '%' . $request->query('key') . '%')
                    ->orWhere('email', 'LIKE', '%' . $request->query('key') . '%')
                    ->orderBy('id', 'desc')
                    ->skip($pagination['skip'])
                    ->take($pagination['limit'])
                    ->get();

                $totalCourierMedium = CourierMedium::where('courierMediumName', 'LIKE', '%' . $request->query('key') . '%')
                    ->orWhere('address', 'LIKE', '%' . $request->query('key') . '%')
                    ->orWhere('phone', 'LIKE', '%' . $request->query('key') . '%')
                    ->orWhere('email', 'LIKE', '%' . $request->query('key') . '%')
                    ->count();

                $converted = arrayKeysToCamelCase($courier->toArray());
                $finalResult = [
                    'getAllCourierMedium' => $converted,
                    'totalCourierMedium' => $totalCourierMedium,
                ];
                return response()->json($finalResult, 200);
            } catch (Exception $err) {
                return response()->json(['error' => 'An error occurred during getting all Courier medium. Please try again later.'], 500);
            }
        } else if ($request->query()) {
            try {
                $pagination = getPagination($request->query());
                $courierMedium = CourierMedium::where('status', $request->query('status'))
                    ->orderBy('id', 'desc')
                    ->skip($pagination['skip'])
                    ->take($pagination['limit'])
                    ->get();

                $totalCourierMedium = CourierMedium::where('status', $request->query('status'))
                    ->count();

                $converted = arrayKeysToCamelCase($courierMedium->toArray());
                $finalResult = [
                    'getAllCourierMedium' => $converted,
                    'totalCourierMedium' => $totalCourierMedium,
                ];
                return response()->json($finalResult, 200);
            } catch (Exception $err) {
                return response()->json(['error' => 'An error occurred during getting all Courier medium. Please try again later.'], 500);
            }
        } else {
            return response()->json(['error' => 'Invalid query!'], 400);;
        }
    }

    public function getSingleCourierMedium(Request $request, $id): JsonResponse
    {
        if ($request->query()) {
            $pagination = getPagination($request->query());
            try {
                $courierMedium = CourierMedium::where('id', $id)
                    ->with(['cartOrder' => function ($query) use ($pagination) {
                        $query->skip($pagination['skip'])
                            ->take($pagination['limit'])
                            ->orderBy('id', 'desc');
                    }, 'cartOrder.cartOrderProduct'])
                    ->withCount('cartOrder as totalCartOrder')
                    ->first();

                // remove profit 
                collect($courierMedium->cartOrder)->each(function ($item) {
                    unset($item->profit);
                });

                $converted = arrayKeysToCamelCase($courierMedium->toArray());
                return response()->json($converted, 200);
            } catch (Exception $err) {
                return response()->json(['error' => 'An error occurred during getting single Courier Medium. Please try again later.', $err->getMessage()], 500);
            }
        } else {
            try {
                $courierMedium = CourierMedium::where('id', $id)
                    ->with('subAccount')
                    ->first();
                $converted = arrayKeysToCamelCase($courierMedium->toArray());
                return response()->json($converted, 200);
            } catch (Exception $err) {
                return response()->json(['error' => 'An error occurred during getting single Courier Medium. Please try again later.', $err->getMessage()], 500);
            }
        }
    }

    public function updateSingleCourierMedium(Request $request, $id): JsonResponse
    {
        try {
            $courierMedium = CourierMedium::findOrFail($id);
            $courierMedium->update([
                'courierMediumName' => $request->input('courierMediumName') ?? $courierMedium->courierMediumName,
                'address' => $request->input('address') ?? $courierMedium->address,
                'phone' => $request->input('phone') ?? $courierMedium->phone,
                'email' => $request->input('email') ?? $courierMedium->email,
                'status' => $request->input('status') ?? $courierMedium->status,
            ]);

            $converted = arrayKeysToCamelCase($courierMedium->toArray());
            return response()->json($converted, 200);
        } catch (Exception $err) {
            return response()->json(['error' => 'An error occurred during updating single Courier medium. Please try again later.'], 500);
        }
    }

    public function deleteSingleCourierMedium(Request $request, $id): JsonResponse
    {
        try {
            $courierMedium = CourierMedium::where('id', $id)->update([
                'status' => $request->input('status'),
            ]);


            if (!$courierMedium) {
                return response()->json(['message' => 'Failed to delete courier medium'], 404);
            }
            return response()->json(['message' => 'Courier deleted successfully.'], 200);
        } catch (Exception $err) {
            return response()->json(['error' => 'An error occurred during deleting single Courier medium. Please try again later.'], 500);
        }
    }
}
