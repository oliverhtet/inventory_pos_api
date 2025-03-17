<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CartController;


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


Route::middleware('permission:create-cart')->post("/", [CartController::class, 'createCart']);

Route::middleware('permission:readAll-cart')->get("/", [CartController::class, 'getAllCart']);

Route::middleware('permission:readSingle-cart')->get("/customer/{id}", [CartController::class, 'getCartByUserId']);

Route::middleware('permission:readSingle-cart')->get("/{id}", [CartController::class, 'getSingleCart']);

Route::middleware('permission:readSingle-cart')->put("/cart-product/{id}", [CartController::class, 'updateSingleCartProduct']);




