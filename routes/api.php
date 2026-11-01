<?php

use App\Http\Controllers\AddressController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OverviewController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\WishlistController;
use Illuminate\Support\Facades\Route;


//auth routes

Route::group(["prefix" => "auth"], function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
});

Route::middleware('auth:sanctum')->prefix('auth')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::patch('/profile', [AuthController::class, 'updateProfile']);
    Route::patch('/password', [AuthController::class, 'updatePassword']);
    Route::post('/logout', [AuthController::class, 'logout']);
});


//product routes
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/best-selling', [ProductController::class, 'bestSelling']);
Route::get('/products/{product}', [ProductController::class, 'show'])->whereNumber('product');
Route::apiResource('/products', ProductController::class)
    ->middleware(['auth:sanctum', 'role:admin'])
    ->whereNumber('product')
    ->except(['index', 'show']);

//category routes
Route::get('/categories', [CategoryController::class, 'index']);
Route::apiResource('/categories', CategoryController::class)->middleware(['auth:sanctum', 'role:admin'])->except('index');

// admin overview
Route::get('/overview', [OverviewController::class, 'index'])->middleware(['auth:sanctum', 'role:admin']);

//cart routes
Route::middleware('auth:sanctum')->prefix('cart')->group(function () {
    Route::get('/', [CartController::class, 'index']);
    Route::post('/items', [CartController::class, 'store']);
    Route::patch('/items/{cartItem}', [CartController::class, 'update']);
    Route::delete('/items/{cartItem}', [CartController::class, 'destroy']);
    Route::delete('/items/clear', [CartController::class, 'clear']);
});

// address routes
Route::resource('/addresses', AddressController::class)->middleware('auth:sanctum');


//order routes
Route::middleware('auth:sanctum')->prefix('order')->group(function () {
    Route::get('/', [OrderController::class, 'index']);
    Route::get('/user', [OrderController::class, 'userOrder']);
    Route::post('/', [OrderController::class, 'store']);
    Route::get('/{order}', [OrderController::class, 'show']);
    Route::patch('/{order}', [OrderController::class, 'update']);
    Route::delete('/{order}', [OrderController::class, 'destroy']);
    Route::patch('/{order}/status', [OrderController::class, 'updateStatus'])->middleware('role:admin');
});

//payment routes
Route::middleware('auth:sanctum')->prefix('payment')->group(function () {
    Route::get('/', [PaymentController::class, 'index'])->middleware('role:admin');
    Route::get('/{transactionId}/status', [PaymentController::class, 'check']);
    Route::patch('/{payment}/status', [PaymentController::class, 'updateStatus'])->middleware('role:admin');
    Route::get('/{payment}', [PaymentController::class, 'show']);
    Route::post('/', [PaymentController::class, 'store']);
});

//wishlist routes
Route::resource('/wishlist', WishlistController::class)
    ->middleware('auth:sanctum')
    ->only(['index', 'store', 'destroy']);
