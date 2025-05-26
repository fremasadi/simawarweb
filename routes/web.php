<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OrderPrintController;

Route::get('/orders/print', [OrderPrintController::class, 'print'])->name('orders.print');


Route::get('/', function () {
    return view('welcome');
});
