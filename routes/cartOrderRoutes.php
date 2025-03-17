<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CartOrderController;

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

Route::middleware('permission:create-cartOrder')->post("/", [CartOrderController::class, 'createSingleCartOrder']);

Route::middleware('permission:create-cartReOrder')->post("/reOrder", [CartOrderController::class, 'createReOrderForReturn']);

Route::middleware('permission:readAll-cartOrder')->get("/", [CartOrderController::class, 'getAllCartOrder']);

Route::middleware('permission:readSingle-cartOrder')->get("/{id}", [CartOrderController::class, 'getSingleCartOrder']);

Route::middleware('permission:update-cartOrder')->patch("/order", [CartOrderController::class, 'updateCartOrderStatus']);
