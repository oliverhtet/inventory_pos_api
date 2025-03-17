<?php

use App\Http\Controllers\ShippingChargeController;
use Illuminate\Support\Facades\Route;


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

Route::middleware('permission:create-shippingCharge')->post("/", [ShippingChargeController::class, 'createSingleShippingCharge']);

Route::middleware('permission:readAll-shippingCharge')->get("/", [ShippingChargeController::class, 'getAllShippingCharge']);

Route::middleware('permission:readAll-shippingCharge')->get("/{id}", [ShippingChargeController::class, 'getSingleShippingCharge']);

Route::middleware('permission:update-shippingCharge')->put("/{id}", [ShippingChargeController::class, 'updateSingleShippingCharge']);

Route::middleware('permission:update-shippingCharge')->put("/product/{id}", [ShippingChargeController::class, 'updateWithProduct']);

Route::middleware('permission:delete-shippingCharge')->patch("/{id}", [ShippingChargeController::class, 'deleteSingleShippingCharge']);
