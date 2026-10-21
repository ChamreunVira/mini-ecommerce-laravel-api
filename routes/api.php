<?php

use App\Http\Controllers\AddressController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\WishlistController;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;


//auth routes

Route::group(["prefix" => "auth"], function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
});

Route::middleware('auth:sanctum')->prefix('auth')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
});


//product routes
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{product}', [ProductController::class, 'show']);
Route::apiResource('/products', ProductController::class)
    ->middleware('auth:sanctum')
    ->except(['index', 'show']);

//category routes
Route::get('/categories', [CategoryController::class, 'index']);
Route::apiResource('/categories', CategoryController::class);

//cart routes
Route::middleware('auth:sanctum')->prefix('cart')->group(function () {
    Route::get('/', [CartController::class, 'index']);
    Route::post('/items', [CartController::class, 'store']);
    Route::patch('/items/{cartItem}', [CartController::class, 'update']);
    Route::delete('/items/{cartItem}', [CartController::class, 'destroy']);
    Route::delete('/clear', [CartController::class, 'clear']);
});

Route::resource('/addresses', AddressController::class)->middleware('auth:sanctum');


//order routes
Route::middleware('auth:sanctum')->prefix('order')->group(function () {
    Route::get('/', [OrderController::class, 'index']);
    Route::post('/', [OrderController::class, 'store']);
    Route::delete('/{order}', [OrderController::class,'destroy']);
});

//wishlist routes
Route::resource('/wishlist', WishlistController::class);
