<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/make-stk-request', [App\Http\Controllers\KcbController::class, 'stkRequestMake'])->name('make-stk-request');
Route::post('/stk-callback', [App\Http\Controllers\KcbController::class, 'stkCallback'])->name('stk-callback');
