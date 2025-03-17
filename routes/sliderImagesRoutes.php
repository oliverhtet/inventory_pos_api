<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SliderImagesController;



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


Route::middleware(['permission:create-sliderImages',  'fileUploader:1'])->post("/", [SliderImagesController::class, 'createSingleSliderImages'])->name('sliderImages.create');

Route::middleware('permission:readAll-sliderImages')->get("/", [SliderImagesController::class, 'getAllSliderImages']);

Route::get("/public", [SliderImagesController::class, 'publicGetAllSliderImages']);

Route::middleware(['permission:update-sliderImages', 'fileUploader:1'])->put("/{id}", [SliderImagesController::class, 'updateSingleSliderImages'])->name('sliderImages.update');

Route::middleware('permission:delete-sliderImages')->delete("/{id}", [SliderImagesController::class, 'deleteSlider']);
