<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WishlistController;
use App\Http\Controllers\ProductWishlistController;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware(['permission:create-productWishlist'])->post("/", [WishlistController::class, 'createSingleWishlist']);

Route::middleware('permission:readAll-productWishlist')->get("/", [WishlistController::class, 'getAllProductWishlist']);

Route::middleware('permission:readAll-productWishlist')->get("customer/{id}", [WishlistController::class, 'getProductWishlistByCustomerId']);

Route::middleware('permission:delete-productWishlist')->delete("customer/{id}", [WishlistController::class, 'deleteSingleProductWishlist']);

