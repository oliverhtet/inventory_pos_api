<?php

use App\Http\Controllers\DeliveryChallanController;
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

Route::middleware('permission:create-deliveryChallan')->post('/', [DeliveryChallanController::class, 'createDeliveryChallan']);
Route::middleware('permission:readAll-deliveryChallan')->get('/',[DeliveryChallanController::class,'getAllDeliveryChallan']);
Route::middleware('permission:readSingle-deliveryChallan')->get('/{id}',[DeliveryChallanController::class,'getSingleDeliveryChallan']);
Route::middleware('permission:delete-deliveryChallan')->patch('/{id}',[DeliveryChallanController::class,'deleteDeliveryChallan']);
