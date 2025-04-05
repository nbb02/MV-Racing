<?php

use App\Http\Controllers\AddressesController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartsController;
use App\Http\Controllers\OrdersController;
use App\Http\Controllers\ProductsController;
use App\Http\Controllers\QRController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',  [AuthController::class, 'login'])->name('login');
Route::get('/login', function () {
    return redirect(env('FRONTEND_URL'));
})->name('login');

Route::post('/reset-password', [AuthController::class, 'resetPasswordEmail']);
Route::patch('/reset-password', [AuthController::class, 'resetPassword']);

Route::get('/products', [ProductsController::class, 'index']);
Route::get('/products/{id}', [ProductsController::class, 'show']);


Route::group(['middleware' => ['auth:sanctum']], function () {
    Route::patch('/change-password', [AuthController::class, 'changePassword']);

    Route::get('/orders', [OrdersController::class, 'index']);
    Route::get('/orders/{id}', [OrdersController::class, 'show']);
    Route::patch('/orders/{id}/cancel', [OrdersController::class, 'cancel']);

    //BUYER ROUTES
    Route::group(['middleware'  => function (Request $request, $next) {
        if ($request->user()->role !== 'user') {
            abort(403, 'Unauthorized');
        }
        return $next($request);
    }], function () {
        Route::get('/address', [AddressesController::class, 'index']);
        Route::post('/address', [AddressesController::class, 'store']);
        Route::get('/address/{id}', [AddressesController::class, 'show']);
        Route::patch('/address/{id}', [AddressesController::class, 'update']);
        Route::delete('/address/{id}', [AddressesController::class, 'destroy']);

        Route::get('/cart', [CartsController::class, 'index']);
        Route::post('/cart', [CartsController::class, 'store']);
        Route::patch('/cart/{id}', [CartsController::class, 'update']);
        Route::delete('/cart/{id}', [CartsController::class, 'destroy']);

        Route::post('/orders', [OrdersController::class, 'store']);
        Route::post('/orders/payment-proof', [OrdersController::class, 'sendProofOfPayment']);

        Route::get('/qr', [QRController::class, 'index']);
    });

    //SELLER ROUTES
    Route::group(['middleware' =>   function (Request $request, $next) {
        if ($request->user()->role !== 'admin') {
            abort(403, 'Unauthorized');
        }
        return $next($request);
    }], function () {
        Route::post('/products', [ProductsController::class, 'store']);
        Route::patch('/products/{id}', [ProductsController::class, 'update']);
        Route::delete('/products/{id}', [ProductsController::class, 'destroy']);

        Route::post('/products', [ProductsController::class, 'store']);
        Route::patch('/products', [ProductsController::class, 'update']);
        Route::delete('/products', [ProductsController::class, 'destroy']);
        Route::post('/products/{productId}', [ProductsController::class, 'addImages']);
        Route::delete('/products/images/{imageId}', [ProductsController::class, 'deleteImage']);

        Route::post('/product-stocks', [ProductsController::class, 'stocks']);

        Route::post('/orders/{id}/decline', [OrdersController::class, 'decline']);
        Route::get('/orders/{id}/accept-payment', [OrdersController::class, 'acceptPayment']);
        Route::post('/orders/{id}/ship', [OrdersController::class, 'ship']);
        Route::post('/orders/{id}/tracking', [OrdersController::class, 'addTracking']);
        Route::post('/orders/{id}/delivered', [OrdersController::class, 'delivered']);


        Route::post('/qr', [QRController::class, 'store']);
        Route::delete('/qr/{id}', [QRController::class, 'destroy']);

        Route::post('/product-selections', [ProductsController::class, 'addSelection']);
        Route::delete('/product-selections', [ProductsController::class, 'deleteSelection']);
    });
});
