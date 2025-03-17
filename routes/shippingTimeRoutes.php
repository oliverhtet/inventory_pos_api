<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ShippingTimeController;


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


Route::middleware('permission:create-shippingTime')->post("/", [ShippingTimeController::class, 'createSingleShippingTime']);

Route::middleware('permission:readAll-shippingTime')->get("/", [ShippingTimeController::class, 'getAllShippingTime']);

Route::middleware('permission:readAll-shippingTime')->get("/{id}", [ShippingTimeController::class, 'getSingleShippingTime']);

Route::middleware('permission:update-shippingTime')->put("/{id}", [ShippingTimeController::class, 'updateSingleShippingTime']);

Route::middleware('permission:update-shippingTime')->put("/product/{id}", [ShippingTimeController::class, 'updateWithProduct']);

Route::middleware('permission:delete-shippingTime')->patch("/{id}", [ShippingTimeController::class, 'deleteSingleShippingTime']);
