<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ReviewRatingController;

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

Route::middleware(['permission:create-reviewRating', 'fileUploader:3'])->post("/", [ReviewRatingController::class, 'createReviewRating']);

Route::middleware('permission:readAll-reviewRating')->get("/", [ReviewRatingController::class, 'getReviewRating']);

Route::get("/product/{id}", [ReviewRatingController::class, 'getSingleReviewByProductId']);
Route::middleware(['permission:create-reviewReply'])->post("/reply", [ReviewRatingController::class, 'createReviewReply']);

Route::middleware('permission:readSingle-reviewRating')->get("/{id}", [ReviewRatingController::class, 'getSingleReviewRating']);

Route::middleware('permission:delete-reviewRating')->patch("/{id}", [ReviewRatingController::class, 'deleteReviewRating']);
