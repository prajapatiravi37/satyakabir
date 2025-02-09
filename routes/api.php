<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductDealerController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\OrderController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);


// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     Route::post('/logout', [AuthController::class, 'logout']);
//     // return $request->user();
// });
Route::middleware('auth:sanctum')->group(function () {
    // User Profile Route (Authenticated user only)
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    // Logout Route
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/products', [ProductDealerController::class, 'getProducts']);
    Route::get('/dealers', [ProductDealerController::class, 'getDealers']);
    Route::get('/products-by-type', [ProductDealerController::class, 'getProductsByType']);

    /* User Profile Routes */
    Route::get('/user-profile', [UserController::class, 'getUserProfile']);
    Route::post('/update-profile', [UserController::class, 'updateUserProfile']);
    Route::post('/change-password', [UserController::class, 'changePassword']);

    /* Bank Details Routes */
    Route::post('/add-bank-details', [UserController::class, 'addBankDetails']);
    Route::get('/get-bank-details', [UserController::class, 'getBankDetails']);
    Route::post('/update-bank-details', [UserController::class, 'updateBankDetails']);

    /* Product Order Routes */
    Route::post('/place-order', [OrderController::class, 'placeOrder']);

});
