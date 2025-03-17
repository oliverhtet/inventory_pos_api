<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVat;
use App\Models\ProductWishlist;
use Exception;
use App\Models\Wishlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WishlistController extends Controller
{

    public function createSingleWishlist(Request $request): JsonResponse
    {
        try {
            
            $data = $request->attributes->get('data');
            $customerId = $data['sub'];
            $Wishlist = Wishlist::where('customerId', $customerId)->first();
            if ($Wishlist) {
                $productWishlist = ProductWishlist::where('productId', $request->input('productId'))
                    ->where('wishlistId', $Wishlist->id)
                    ->first();
                if ($productWishlist) {
                    return response()->json(['error' => 'Product already added to wishlist.'], 400);
                }
                $productWishlist = ProductWishlist::create([
                    'productId' => $request->input('productId'),
                    'wishlistId' => $Wishlist->id,
                ]);
                $converted = arrayKeysToCamelCase($productWishlist->toArray());
                return response()->json($converted, 200);
            }

            $Wishlist = Wishlist::create([
                'customerId' => $customerId,
            ]);

            $productWishlist = ProductWishlist::create([
                'productId' => $request->input('productId'),
                'wishlistId' => $Wishlist->id,
            ]);

            $converted = arrayKeysToCamelCase($productWishlist->toArray());
            return response()->json($converted, 200);
        } catch (Exception $err) {
            return response()->json(['error' => 'An error occurred during creating ProductWishlist. Please try again later.'], 500);
        }
    }

    public function getAllProductWishlist(Request $request): JsonResponse
    {
        if ($request->query('query') === 'all') {
            try {
                $productWishlist = Wishlist::with('customer:id,username')->where('status', 'true')->orderBy('id', 'desc')->get();

                $converted = arrayKeysToCamelCase($productWishlist->toArray());

                return response()->json($converted, 200);
            } catch (Exception $err) {
                return response()->json(['error' => 'An error occurred during getting ProductWishlist. Please try again later.'], 500);
            }
        } else if ($request->query()) {
            try {
                $pagination = getPagination($request->query());

                $productWishlist = Wishlist::with('customer:id,username')
                    ->orderBy('id', 'desc')
                    ->where('status', $request->query('status'))
                    ->skip($pagination['skip'])
                    ->take($pagination['limit'])
                    ->get();


                $total = Wishlist::where('status', $request->query('status'))->count();

                $converted = arrayKeysToCamelCase($productWishlist->toArray());
                $finalResult = [
                    'getAllWishlist' => $converted,
                    'totalWishlist' => $total,
                ];
                return response()->json($finalResult, 200);
            } catch (Exception $err) {
                echo $err;
                return response()->json(['error' => 'An error occurred during getting ProductWishlist. Please try again later.'], 500);
            }
        } else {
            return response()->json(['error' => 'Invalid query.'], 400);
        }
    }

    public function getProductWishlistByCustomerId(Request $request, $id): JsonResponse
    {
        try {
            $data = $request->attributes->get('data');
            
            if ($data['sub'] != $id && $data['role'] != 'admin') {
                return response()->json(['error' => 'You are not authorized to access this data.'], 401);
            }
            $pagination = getPagination($request->query());
            $Wishlist = Wishlist::where('customerId', $id)->first();

            if (!$Wishlist) {
                return response()->json(['error' => 'Wishlist not found.'], 404);
            }

            $productWishlist = ProductWishlist::with('product', 'product.discount', 'product.productBrand:id,name','product.productProductAttributeValue.productAttributeValue','product.productProductAttributeValue.productAttributeValue.productAttribute', 'product.productColor.color',)
                ->where('wishlistId', $Wishlist->id)
                ->skip($pagination['skip'])
                ->take($pagination['limit'])
                ->get();

            $total = ProductWishlist::where('wishlistId', $Wishlist->id)->count();

            $products = [];
            foreach ($productWishlist as $key => $value) {
            
                $products[] = $value->product->toArray();
            }

            foreach ($products as $key => $value) {
                unset($products[$key]['productPurchasePrice']);
                unset($products[$key]['uomValue']);
                unset($products[$key]['purchaseInvoiceId']);
                unset($products[$key]['reorderQuantity']);
            }
            $converted = arrayKeysToCamelCase($products);

            // concat the productSalePrice and vat amount
            foreach ($converted as $key => $value) {
                $productVat = ProductVat::where('id', $value['productVatId'])->first();
                $converted[$key]['productSalePriceWithVat'] = $value['productSalePrice'] + ($value['productSalePrice'] * $productVat->percentage) / 100;
            }

            $finalResult = [
                'getAllProductWishlist' => $converted,
                'totalProductWishlist' => $total,
            ];
            return response()->json($finalResult, 200);
        } catch (Exception $err) {
            echo $err;
            return response()->json(['error' => 'An error occurred during getting ProductWishlist. Please try again later.'], 500);
        }
    }


    public function deleteSingleProductWishlist(Request $request, $id): JsonResponse
    {
        try {
            $data = $request->attributes->get('data');
            if ($data['sub'] != $id) {
                return response()->json(['error' => 'You are not authorized to access this data.'], 401);
            }
            $Wishlist = Wishlist::where('customerId', $id)->first();
            $productWishlist = ProductWishlist::where('productId', $request->input('productId'))->where('wishlistId', $Wishlist->id)->first();
            if (!$productWishlist) {
                return response()->json(['error' => 'ProductWishlist not found.'], 404);
            }
            $productWishlist = ProductWishlist::where('productId', $request->input('productId'))->where('wishlistId', $Wishlist->id)->delete();

            return response()->json(['success' => 'Product Delete Success!'], 200);
        } catch (Exception $err) {
            return response()->json(['error' => 'An error occurred during deleting ProductWishlist. Please try again later.'], 500);
        }
    }
}
