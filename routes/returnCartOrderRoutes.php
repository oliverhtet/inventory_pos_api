<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ReturnCartOrderController;

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

Route::middleware('permission:create-returnCartOrder')->post("/", [ReturnCartOrderController::class, 'createSingleReturnCartOrder']);

Route::middleware('permission:readAll-returnCartOrder')->get("/", [ReturnCartOrderController::class, 'getAllReturnCartOrder']);

Route::middleware('permission:readAll-returnCartOrder')->get("/resend", [ReturnCartOrderController::class, 'getResendCartOrderList']);

Route::middleware('permission:readSingle-returnCartOrder')->get("/{id}", [ReturnCartOrderController::class, 'getSingleReturnCartOrder']);

Route::middleware('permission:update-returnCartOrder')->patch("/{id}", [ReturnCartOrderController::class, 'updateReturnCartOrderStatus']);
