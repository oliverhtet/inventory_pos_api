<?php

namespace App\Http\Controllers;

use Exception;
use Carbon\Carbon;
use App\Models\Coupon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CouponController extends Controller
{
    //create a single voucher or coupon code
    public function createCoupon(Request $request): JsonResponse
    {
        try {
            $couponCreated = Coupon::create([
                'couponCode' => $request->input('couponCode'),
                'type' => $request->input('type'),
                'value' => $request->input('value'),
                'startDate' => Carbon::parse($request->input('startDate')),
                'endDate' => Carbon::parse($request->input('endDate')),
            ]);

            $converted = arrayKeysToCamelCase($couponCreated->toArray());
            return response()->json($converted, 200);
        } catch (Exception $err) {
            echo $err;
            return response()->json(['error' => 'An error occurred during creating a Coupon'], 500);
        }
    }

    //get all the voucher or coupon code
    public function getAllCoupon(Request $request): JsonResponse
    {
        if ($request->query('query') === 'all') {
            try {

                $getAllCoupon = Coupon::orderBy('id', 'desc')
                    ->get();

                $converted = arrayKeysToCamelCase($getAllCoupon->toArray());
                $aggregation = [
                    'getAllCoupon' => $converted,
                    'totalCoupon' => Coupon::count(),
                ];

                return response()->json($aggregation, 200);
            } catch (Exception $err) {
                return response()->json(['error' => 'An error occurred during getting voucher'], 500);
            }
        } else if ($request->query('query') === 'search') {
            try {
                $pagination = getPagination($request->query());
                $getAllCoupon = Coupon::where('couponCode', 'LIKE', '%' . $request->query('key') . '%')
                    ->orwhere('type', 'LIKE', '%' . $request->query('key') . '%')
                    ->orderBy('id', 'desc')
                    ->skip($pagination['skip'])
                    ->take($pagination['limit'])
                    ->get();

                $totalCoupon = Coupon::where('couponCode', 'LIKE', '%' . $request->query('key') . '%')
                    ->orwhere('type', 'LIKE', '%' . $request->query('key') . '%')
                    ->count();

                $converted = arrayKeysToCamelCase($getAllCoupon->toArray());
                $finalResult = [
                    'getAllCoupon' => $converted,
                    'totalCoupon' => $totalCoupon,
                ];
                return response()->json($finalResult, 200);
            } catch (Exception $err) {
                return response()->json(['error' => 'An error occurred during getting voucher'], 500);
            }

        } else if ($request->query()) {
            try {
                $pagination = getPagination($request->query());
                $getAllCoupon = Coupon::
                when($request->query('status'), function ($query) use ($request) {
                    return $query->whereIn('status', explode(',', $request->query('status')));
                })
                    ->when($request->query('type'), function ($query) use ($request) {
                        return $query->whereIn('type', explode(',', $request->query('type')));

                    })
                    ->when($request->query('startDate'), function ($query) use ($request) {
                        $dates = explode(',', $request->query('startDate'));
                        return $query->whereIn(DB::raw('DATE(startDate)'), $dates);
                    })
                    ->when($request->query('endDate'), function ($query) use ($request) {
                        $dates = explode(',', $request->query('endDate'));
                        return $query->whereIn(DB::raw('DATE(endDate)'), $dates);
                    })
                    ->orderBy('id', 'desc')
                    ->skip($pagination['skip'])
                    ->take($pagination['limit'])
                    ->get();

                $totalCoupon = Coupon::
                when($request->query('status'), function ($query) use ($request) {
                    return $query->whereIn('status', explode(',', $request->query('status')));
                })
                    ->when($request->query('type'), function ($query) use ($request) {
                        return $query->whereIn('type', explode(',', $request->query('type')));

                    })
                    ->when($request->query('startDate'), function ($query) use ($request) {
                        $dates = explode(',', $request->query('startDate'));
                        return $query->whereIn(DB::raw('DATE(startDate)'), $dates);
                    })
                    ->when($request->query('endDate'), function ($query) use ($request) {
                        $dates = explode(',', $request->query('endDate'));
                        return $query->whereIn(DB::raw('DATE(endDate)'), $dates);
                    })
                    ->count();
                $converted = arrayKeysToCamelCase($getAllCoupon->toArray());
                $finalResult = [
                    'getAllCoupon' => $converted,
                    'totalCoupon' => $totalCoupon,
                ];
                return response()->json($finalResult, 200);
            } catch (Exception $err) {
                return response()->json(['error' => 'An error occurred during getting voucher'], 500);
            }

        } else {
            return response()->json(['error' => 'Invalid query'], 500);
        }
    }

    public function getAllValidCoupon(): JsonResponse
    {
        try {
            $getAllCoupon = Coupon::where('status', 'true')->where('endDate', '>=', Carbon::now())->get();
            $converted = arrayKeysToCamelCase($getAllCoupon->toArray());
            return response()->json($converted, 200);
        } catch (Exception $err) {
            return response()->json(['error' => 'An error occurred during getting voucher'], 500);
        }
    }

    //get single valid coupon
    public function getSingleValidCoupon($coupon): JsonResponse
    {
        try {
            $getSingleCoupon = Coupon::where('couponCode', $coupon)->where('status', 'true')->where('endDate', '>=', Carbon::now())->first();

            if (!$getSingleCoupon) {
                return response()->json(['error' => 'Invalid Coupon'], 500);
            }

            $converted = arrayKeysToCamelCase($getSingleCoupon->toArray());
            return response()->json($converted, 200);
        } catch (Exception $err) {
            return response()->json(['error' => 'An error occurred during getting voucher'], 500);
        }
    }

    //get a single voucher or coupon code
    public function getSingleCoupon($id): JsonResponse
    {
        try {
            $getSingleCoupon = Coupon::where('id', $id)->with('userCouponUsage.user:id,firstName,lastName,username', 'userCouponUsage.saleInvoice')->first();

            $converted = arrayKeysToCamelCase($getSingleCoupon->toArray());
            return response()->json($converted, 200);
        } catch (Exception $err) {
            echo $err;
            return response()->json(['error' => 'An error occurred during getting voucher'], 500);
        }
    }


    //update a single voucher or coupon code
    public function updateSingleCoupon(Request $request, $id): JsonResponse
    {
        try {
            $updatedCoupon = Coupon::where('id', (int)$id)
                ->update($request->all());

            if (!$updatedCoupon) {
                return response()->json(['error' => 'Failed to update the Coupon'], 404);
            }
            return response()->json(['message' => 'Coupon Updated Successfully'], 200);
        } catch (Exception $err) {
            return response()->json(['error' => 'An error occurred during updating Coupon'], 500);
        }
    }

    //update a single voucher or coupon code status
    public function deleteCoupon(Request $request, $id): JsonResponse
    {
        try {
            $deleteCoupon = Coupon::where('id', (int)$id)
                ->update([
                    'status' => $request->input('status'),
                ]);

            if (!$deleteCoupon) {
                return response()->json(['error' => 'Failed to delete Coupon'], 404);
            }
            return response()->json(['message' => 'Coupon Deleted Successfully'], 200);
        } catch (Exception $err) {
            return response()->json(['error' => 'An error occurred during deleting Coupon status'], 500);
        }
    }
}
