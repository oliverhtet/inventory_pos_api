<?php



use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CourierMediumController;


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

Route::middleware('permission:create-courierMedium')->post("/", [CourierMediumController::class, 'createSingleCourierMedium']);

Route::middleware('permission:readAll-courierMedium')->get("/", [CourierMediumController::class, 'getAllCourierMedium']);

Route::middleware('permission:readAll-courierMedium')->get("/{id}", [CourierMediumController::class, 'getSingleCourierMedium']);

Route::middleware('permission:update-courierMedium')->put("/{id}", [CourierMediumController::class, 'updateSingleCourierMedium']);

Route::middleware('permission:delete-courierMedium')->patch("/{id}", [CourierMediumController::class, 'deleteSingleCourierMedium']);
