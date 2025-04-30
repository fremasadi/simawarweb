<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\OrdersController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\SalaryHistoryController;


Route::post('login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/orders', [OrdersController::class, 'index']);
    Route::post('/orders/{id}/take', [OrdersController::class, 'takeOrder']); // ✅ Route untuk mengambil order
    Route::get('/orders/ongoing', [OrdersController::class, 'getOngoingOrders']); // ✅ Pastikan pakai OrdersController
    Route::get('/orders/completed/count', [OrdersController::class, 'countCompletedOrders']);
    Route::get('/orders/completed', [OrdersController::class, 'getCompletedOrders']);

    Route::get('/user/profile', [UserController::class, 'getUserProfile']);

    Route::post('/attendance/check-in', [AttendanceController::class, 'checkIn']);
    Route::get('/attendance/history', [AttendanceController::class, 'history']);

    Route::get('/salary-history', [SalaryHistoryController::class, 'index']);

});
