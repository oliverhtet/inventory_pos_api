<?php

use App\Http\Controllers\DeliveryFeeController;
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

Route::middleware('permission:create-deliveryFee')->post('/',[DeliveryFeeController::class,'createDeliveryFee']);
Route::middleware('permission:readAll-deliveryFee')->get('/',[DeliveryFeeController::class,'getAllDeliveryFees']);
Route::middleware('permission:update-deliveryFee')->put('/{id}',[DeliveryFeeController::class,'updateDeliveryFee']);
Route::middleware('permission:delete-deliveryFee')->patch('/{id}',[DeliveryFeeController::class,'deleteDeliveryFee']);
