<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartAttributeValue;
use App\Models\CartProduct;
use App\Models\Discount;
use App\Models\Product;
use App\Models\ProductVat;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class CartController extends Controller
{
    //create cart
    public function createCart(Request $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $customerId = $request->customerId;
            $cartProducts = $request->cartProduct;
            $totalAmount = 0;

            //is product exist or not
            foreach ($cartProducts as $cartProductData) {
                $product = Product::find($cartProductData['productId']);

                if (!$product) {
                    return response()->json([
                        "error" => "Product not found",
                    ], 404);
                }
            }

            foreach ($cartProducts as $cartProductData) {
                $product = Product::find($cartProductData['productId']);

                if ($product->productQuantity < $cartProductData['productQuantity']) {
                    return response()->json([
                        "error" => "Not enough product quantity",
                    ], 400);
                }
                if ($product) {
                    $discount = Discount::find($product->discountId);
                    $productVat = ProductVat::find($product->productVatId);

                    if ($product->discountId && $product->productVatId) {
                        if ($discount->type === "percentage") {
                            $totalAmount += (($product->productSalePrice + (($product->productSalePrice * $productVat->percentage) / 100)) - (($product->productSalePrice * $discount->value) / 100)) * $cartProductData['productQuantity'];
                        } else if ($discount->type === "flat") {
                            $totalAmount += (($product->productSalePrice + ($product->productSalePrice * $productVat->percentage / 100)) - $discount->value) * $cartProductData['productQuantity'];
                        }
                    } elseif ($product->discountId && !$product->productVat) {
                        $discount = Discount::find($product->discountId);

                        if ($discount->type === "percentage") {
                            $totalAmount += ($product->productSalePrice - (($product->productSalePrice * $discount->value) / 100)) * $cartProductData['productQuantity'];
                        } else if ($discount->type === "flat") {
                            $totalAmount += ($product->productSalePrice - $discount->value) * $cartProductData['productQuantity'];
                        }
                    } elseif (!$product->discountId && $product->productVat) {
                        $totalAmount += ($product->productSalePrice + (($product->productSalePrice * $productVat->percentage) / 100)) * $cartProductData['productQuantity'];
                    } else {
                        $totalAmount += $product->productSalePrice * $cartProductData['productQuantity'];
                    }
                }
            }

            $existingTotalAmount = Cart::where('customerId', $customerId)->value('totalAmount') ?? 0;

            Cart::updateOrInsert(
                ['customerId' => $customerId],
                ['totalAmount' => takeUptoThreeDecimal($existingTotalAmount + $totalAmount), 'created_at' => now(), 'updated_at' => now()]
            );

            $newCart = Cart::where('customerId', $customerId)->first();

            //if the product is already in the cart then update the quantity and attribute value id and color id if match otherwise create new cart product

            //if attribute value id and color id is match and attribute value id and color id can be null then update the quantity,attribute,color if miss match any single one then create new cart product

            foreach ($cartProducts as $cartProductData) {
                // === === === main cart adding business logic started === === ===
                $cartProductGetFromDb = null;
                if ($cartProductData['colorId'] && count($cartProductData['productAttributeValueId']) === 0) {
                    $getData = CartProduct::where('cartId', $newCart->id)
                        ->where('productId', $cartProductData['productId'])
                        ->where('colorId', $cartProductData['colorId'])
                        ->get();

                    foreach ($getData as $item) {
                        $cartAttributeValueIdDB = CartAttributeValue::where('cartProductId', $item->id)
                            ->pluck('productAttributeValueId')
                            ->toArray();

                        if (count($cartAttributeValueIdDB) === 0) {
                            $cartProductGetFromDb = $item->toArray();
                        }
                    }
                } else if ($cartProductData['colorId'] && count($cartProductData['productAttributeValueId']) !== 0) {
                    $getData = CartProduct::where('cartId', $newCart->id)
                        ->where('productId', $cartProductData['productId'])
                        ->where('colorId', $cartProductData['colorId'])
                        ->get();

                    foreach ($getData as $item) {
                        $cartAttributeValueIdDB = CartAttributeValue::where('cartProductId', $item->id)
                            ->pluck('productAttributeValueId')
                            ->toArray();

                        if (count($cartAttributeValueIdDB) === count($cartProductData['productAttributeValueId']) && count(array_diff($cartAttributeValueIdDB, $cartProductData['productAttributeValueId'])) === 0) {
                            $cartProductGetFromDb = $item->toArray();
                        }
                    }
                } else if (!$cartProductData['colorId'] && count($cartProductData['productAttributeValueId']) !== 0) {
                    $getData = CartProduct::where('cartId', $newCart->id)
                        ->where('productId', $cartProductData['productId'])
                        ->get();

                    foreach ($getData as $item) {
                        $cartAttributeValueIdDB = CartAttributeValue::where('cartProductId', $item->id)
                            ->pluck('productAttributeValueId')
                            ->toArray();

                        if (count($cartAttributeValueIdDB) === count($cartProductData['productAttributeValueId']) && count(array_diff($cartAttributeValueIdDB, $cartProductData['productAttributeValueId'])) === 0) {
                            if (!$item->colorId) {
                                $cartProductGetFromDb = $item->toArray();
                            }
                        }
                    }
                } else if (!$cartProductData['colorId'] && count($cartProductData['productAttributeValueId']) === 0) {
                    $getData = CartProduct::where('cartId', $newCart->id)
                        ->where('productId', $cartProductData['productId'])
                        ->get();

                    foreach ($getData as $item) {
                        $cartAttributeValueIdDB = CartAttributeValue::where('cartProductId', $item->id)
                            ->pluck('productAttributeValueId')
                            ->toArray();

                        if (count($cartAttributeValueIdDB) === 0 && !$item->colorId) {
                            $cartProductGetFromDb = $item->toArray();
                        }
                    }
                }
                // === === === main cart adding business logic ended === === ===


                if ($cartProductGetFromDb) {
                    CartProduct::where('id', $cartProductGetFromDb['id'])
                        ->update([
                            'productQuantity' => $cartProductGetFromDb['productQuantity'] + $cartProductData['productQuantity']
                        ]);
                } else {
                    $newCartProduct = CartProduct::create([
                        'cartId' => $newCart->id,
                        'productId' => $cartProductData['productId'],
                        'productQuantity' => $cartProductData['productQuantity'],
                        'colorId' => $cartProductData['colorId'] ?? null,
                    ]);

                    if ($cartProductData['productAttributeValueId']) {
                        foreach ($cartProductData['productAttributeValueId'] as $productAttributeValueId) {
                            CartAttributeValue::create([
                                'cartProductId' => $newCartProduct->id,
                                'productAttributeValueId' => $productAttributeValueId,
                            ]);
                        }
                    }
                }
            }

            $converted = arrayKeysToCamelCase($newCart->toArray(), 200);
            DB::commit();
            return response()->json($converted);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                "error" => $e->getMessage(),
            ], 500);
        }
    }

    public function getAllCart(Request $request): JsonResponse
    {
        try {
            if ($request->query('query') === "all") {
                $getAllCart = Cart::orderBy('id', 'desc')->get();
                $converted = arrayKeysToCamelCase($getAllCart->toArray());
                return response()->json($converted);
            } else {
                $pagination = getPagination($request->query());

                $getAllCart = Cart::orderBy('id', 'desc')
                    ->where('status', $request->query('status'))
                    ->skip[$pagination['skip']]
                    ->take[$pagination['limit']]
                    ->get();

                $converted = arrayKeysToCamelCase($getAllCart->toArray());
                return response()->json($converted);
            }
        } catch (Exception $e) {
            return response()->json([
                "error" => "An error occurred while getting all carts",
            ], 500);
        }
    }

    public function getCartByUserId(Request $request, $id): JsonResponse
    {
        try {
            $data = $request->attributes->get('data');

            if ($data['role'] !== "admin" && $data['sub'] != (int)$id) {
                return response()->json([
                    "error" => "You are not authorized to access this route",
                ], 401);
            }

            $cart = Cart::where('customerId', $id)->with([
                'cartProducts.cartAttributeValue.productAttributeValue.productAttribute',
                'cartProducts.colors',
                'customer:id,username',
                'cartProducts.product', 'cartProducts.product.galleryImage',
                'cartProducts.product.discount',
                'cartProducts.product.productVat'
            ])->first();

            if (!$cart) {
                return response()->json([
                    "error" => "No Cart Found",
                ], 404);
            }

            foreach ($cart->cartProducts as $cartProduct) {
                unset($cartProduct->product->productPurchasePrice);
                unset($cartProduct->product->uomValue);
                unset($cartProduct->product->purchaseInvoiceId);
                unset($cartProduct->product->reorderQuantity);
            }

            $converted = arrayKeysToCamelCase($cart->toArray());

            foreach ($converted['cartProducts'] as $key => $cartProduct) {
                $productVat = ProductVat::find($cartProduct['product']['productVat']['id']);
                $converted['cartProducts'][$key]['product']['productSalePriceWithVat'] = $cartProduct['product']['productSalePrice'] + (($cartProduct['product']['productSalePrice'] * $productVat->percentage) / 100);
            }

            return response()->json($converted);
        } catch (Exception $e) {
            echo $e;
            return response()->json([
                "error" => "An error occurred while getting cart",
            ], 500);
        }
    }

    //update the single cart product quantity and attribute value id and color id
    public function updateSingleCartProduct(Request $request, $id): JsonResponse
    {
        try {
            $data = $request->attributes->get('data');

            $cart = Cart::with('cartProducts')->where('id', $id)->first();

            if (!$cart) {
                return response()->json([
                    "error" => "No Cart Found",
                ], 404);
            }

            if ($data['role'] !== "admin" && $data['sub'] != (int)$cart->customerId) {
                return response()->json([
                    "error" => "You are not authorized to access this route",
                ], 401);
            }

            $cartProduct = CartProduct::where('id', $request->cartProductId)
                ->where('cartId', $id)
                ->first();

            if (!$cartProduct) {
                return response()->json([
                    "error" => "No Cart Product Found",
                ], 404);
            }

            if ($request->type === "increment" && $request->productQuantity === 0) {
                return response()->json([
                    "error" => "Product Quantity can not be zero",
                ], 400);
            }

            //check enough product quantity
            if ($request->type === "increment") {
                $product = Product::find($cartProduct->productId);
                if ($product->productQuantity < $request->productQuantity) {
                    return response()->json([
                        "error" => "Not enough product quantity",
                    ], 400);
                }
            }

            //when decrement check cart product quantity
            if ($request->type === "decrement") {
                if ($cartProduct->productQuantity < $request->productQuantity) {
                    return response()->json([
                        "error" => "Not enough product quantity",
                    ], 400);
                }
            }

            $product = Product::find($cartProduct->productId);
            $totalAmount = 0;
            $newAmount = 0;
            $newQuantity = 0;

            if ($product) {
                $discount = Discount::find($product->discountId);
                $productVat = ProductVat::find($product->productVatId);

                if ($product->discountId && $product->productVatId) {
                    if ($discount->type === "percentage") {
                        $newAmount += (($product->productSalePrice + (($product->productSalePrice * $productVat->percentage) / 100)) - (($product->productSalePrice * $discount->value) / 100)) * ($request->productQuantity === 0 ? $cartProduct->productQuantity : $request->productQuantity);
                    } else if ($discount->type === "flat") {
                        $newAmount += (($product->productSalePrice + ($product->productSalePrice * $productVat->percentage / 100)) - $discount->value) * ($request->productQuantity === 0 ? $cartProduct->productQuantity : $request->productQuantity);
                    }
                } elseif ($product->discountId && !$product->productVat) {
                    if ($discount->type === "percentage") {
                        $newAmount += ($product->productSalePrice - (($product->productSalePrice * $discount->value) / 100)) * ($request->productQuantity === 0 ? $cartProduct->productQuantity : $request->productQuantity);
                    } else if ($discount->type === "flat") {
                        $newAmount += ($product->productSalePrice - $discount->value) * ($request->productQuantity === 0 ? $cartProduct->productQuantity : $request->productQuantity);
                    }
                } elseif (!$product->discountId && $product->productVat) {
                    $newAmount += ($product->productSalePrice + (($product->productSalePrice * $productVat->percentage) / 100)) * ($request->productQuantity === 0 ? $cartProduct->productQuantity : $request->productQuantity);
                } else {
                    $newAmount += $product->productSalePrice * ($request->productQuantity === 0 ? $cartProduct->productQuantity : $request->productQuantity);
                }
            }

            if ($product) {
                if ($request->type === "increment") {
                    $totalAmount = $cart->totalAmount + $newAmount;
                } else if ($request->type === "decrement") {
                    $totalAmount = $cart->totalAmount - $newAmount;
                }
            }

            //update cartProduct
            if ($request->productQuantity) {
                if ($request->type === "increment") {
                    $newQuantity = $request->productQuantity ? $request->productQuantity + $cartProduct->productQuantity : $cartProduct->productQuantity;
                } else if ($request->type === "decrement") {
                    $newQuantity = $request->productQuantity ? $cartProduct->productQuantity - $request->productQuantity : $cartProduct->productQuantity;
                }
            }

            if ($request->type === "increment" || $request->type === "decrement") {
                if ($newQuantity === 0) {
                    $cartProduct = CartProduct::where('id', $request->cartProductId)
                        ->where('cartId', $id)
                        ->first();

                    $cartAttributeValueIdDB = CartAttributeValue::where('cartProductId', $cartProduct->id)
                        ->pluck('productAttributeValueId')
                        ->toArray();

                    if (count($cartAttributeValueIdDB) !== 0) {
                        CartAttributeValue::where('cartProductId', $cartProduct->id)
                            ->delete();
                    }
                    if ($cartProduct) {
                        CartProduct::where('id', $request->cartProductId)
                            ->where('cartId', $id)
                            ->delete();
                    }

                    Cart::where('id', $id)->update([
                        'totalAmount' => takeUptoThreeDecimal($totalAmount),
                    ]);
                } else {
                    Cart::where('id', $id)->update([
                        'totalAmount' => takeUptoThreeDecimal($totalAmount),
                    ]);

                    CartProduct::where('id', $request->cartProductId)
                        ->where('cartId', $id)
                        ->update([
                            'productQuantity' => $newQuantity,
                        ]);
                }
            }

            $remainCart = Cart::with('cartProducts')->where('id', $id)->first();
            if (count($remainCart->cartProducts) === 0) {
                Cart::with('cartProducts')->where('id', $id)->delete();
            }

            $updatedCart = Cart::with('cartProducts')->where('id', $id)->first();

            $converted = $updatedCart ? arrayKeysToCamelCase($updatedCart->toArray()) : null;
            return response()->json($converted, 200);
        } catch (Exception $e) {
            return response()->json([
                "error" => "An error occurred while updating cart product", $e->getMessage()
            ], 500);
        }
    }

    //get single cart
    public function getSingleCart($id): JsonResponse
    {
        try {
            $cart = Cart::where('id', $id)->with([
                'cartProducts.cartAttributeValue.productAttributeValue.productAttribute',
                'cartProducts.colors',
                'customer:id,username',
                'cartProducts.product', 'cartProducts.product.galleryImage',
                'cartProducts.product.discount',
                'cartProducts.product.productVat'
            ])->first();

            if (!$cart) {
                return response()->json([
                    "error" => "NO Cart Found",
                ], 404);
            }

            $converted = arrayKeysToCamelCase($cart->toArray());
            return response()->json($converted);
        } catch (Exception $e) {
            return response()->json([
                "error" => "An error occurred while getting cart",
            ], 500);
        }
    }
}
