<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Exception;
use Illuminate\Http\Request;
use App\Models\ProductWishlist;

class ProductWishlistController extends Controller
{
    

    public function createSingleproductWishlist(Request $request)
    {
        try {
            $productId = $request->input('productId');
            $customerId = $request->input('customerId');

            $productWishlist = ProductWishlist::where('productId', $productId)->where('customerId', $customerId)->first();
            if($productWishlist){
                return response()->json(['error' => 'Product already in wishlist.'], 404);
            }
            else{
                $productWishlist = ProductWishlist::create([
                    'productId' => $productId,
                    'customerId' => $customerId,
                ]);
            }
                $converted = arrayKeysToCamelCase($productWishlist->toArray());
                return response()->json($converted, 200);
            }

            catch (Exception $err) {
            return response()->json(['error' => 'An error occurred during creating ProductWishlist. Please try again later.'], 500);
            }
    }

    public function getAllProductWishlist(Request $request)
    {
        if($request->query('query')==='all'){
        try {
            $productWishlist = ProductWishlist::with('product','customer:id,username')->where('status', 'true')->orderBy('id', 'desc')->get();

            $converted = arrayKeysToCamelCase($productWishlist->toArray());
            
            return response()->json($converted, 200);
        } catch (Exception $err) {
            return response()->json(['error' => 'An error occurred during getting ProductWishlist. Please try again later.'], 500);
        }
    }else if($request->query()){
        try {
            $pagination = getPagination($request->query());

            $productWishlist = ProductWishlist::with('product','customer:id,username')
            ->orderBy('id', 'desc')
            ->where('status', $request->query('status'))
            ->skip($pagination['skip'])
            ->take($pagination['limit'])
            ->get();
            

            $total= ProductWishlist::with('product','customer')
            ->where('status', $request->query('status'))->count();

            $converted = arrayKeysToCamelCase($productWishlist->toArray());
            $finalresult = [
                'getAllProductWishlist' => $converted,
                'totalProductWishlist' => $total,
            ];
            return response()->json($finalresult, 200);
        } catch (Exception $err) {
            echo $err;
            return response()->json(['error' => 'An error occurred during getting ProductWishlist. Please try again later.'], 500);
        }
    }

    }

    public function getProductWishlistByCustormerId(Request $request, $id)
    {
        try {
            $data = $request->attributes->get('data');
            if($data['sub'] != $id){
                return response()->json(['error' => 'You are not authorized to access this data.'], 401);
            }

            $productWishlist = ProductWishlist::with('product','customer:id,username')->where('customerId', $id)->where('status', 'true')->orderBy('id', 'desc')->get();

            $total = ProductWishlist::with('product','customer:id,username')->where('customerId', $id)->where('status', 'true')->count();
            $converted = arrayKeysToCamelCase($productWishlist->toArray());

            $finalresult = [
                'getAllProductWishlist' => $converted,
                'totalProductWishlist' => $total,
            ];
            return response()->json($finalresult, 200);

        } catch (Exception $err) {
            return response()->json(['error' => 'An error occurred during getting ProductWishlist. Please try again later.'], 500);
        }
    }
    public function updateSingleproductWishlist(Request $request, $id)
    {
        try {
            $data = $request->attributes->get('data');
            if($data['sub'] != $id){
                return response()->json(['error' => 'You are not authorized to access this data.'], 401);
            }
            $productWishlist = ProductWishlist::where('customerId', $id)->get();
            dd($productWishlist);
            $productWishlist->update([
                'productId' => $request->input('productId')??$productWishlist->productId,
                'customerId' => $request->input('customerId')??$productWishlist->customerId,
                "status" => $request->input('status')??$productWishlist->status,
            ]);
            $converted = arrayKeysToCamelCase($productWishlist->toArray());
            return response()->json($converted, 200);
        } catch (Exception $err) {
            return response()->json(['error' => 'An error occurred during updating ProductWishlist. Please try again later.'], 500);
        }
    }

    public function getSingleproductWishlist(Request $request, $id)
    {
        try {
            $productWishlist = ProductWishlist::with('product','customer:id,username')->findorfail($id);
            $converted = arrayKeysToCamelCase($productWishlist->toArray());
            return response()->json($converted, 200);
        } catch (Exception $err) {
            return response()->json(['error' => 'An error occurred during getting ProductWishlist. Please try again later.'], 500);
        }
    }

    

    public function deleteSingleproductWishlist(Request $request, $id)
    {
        try {
            $data = $request->attributes->get('data');
            if($data['sub'] != $id){
                return response()->json(['error' => 'You are not authorized to access this data.'], 401);
            }
            $productWishlist = ProductWishlist::where('customerId', $id)->get();
            $productWishlist->delete();
            if(!$productWishlist){
                return response()->json(['error' => 'ProductWishlist not found.'], 404);
            }
            return response()->json(['message' => 'ProductWishlist deleted successfully.'], 200);
        } catch (Exception $err) {
            return response()->json(['error' => 'An error occurred during deleting ProductWishlist. Please try again later.'], 500);
        }
    }
}
