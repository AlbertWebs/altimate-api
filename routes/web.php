<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
Route::get('/make-stk-request-get', [App\Http\Controllers\KcbController::class, 'stkRequestMakeGet'])->name('make-stk-request-get');
Route::get('/stkrequest-get/{number}/{price}/{order_id}', [App\Http\Controllers\KcbController::class, 'stkRequestMakeGetRemote'])->name('stkrequest-get');

Route::post('/make-stk-request', [App\Http\Controllers\KcbController::class, 'stkRequestMake'])->name('make-stk-request');
Route::post('/stk-callback', [App\Http\Controllers\KcbController::class, 'stkCallback'])->name('stk-callback');

Route::get('/clear-cache', function () {
    Artisan::call('route:cache');
    Artisan::call('config:cache');
    Artisan::call('cache:clear');
    Artisan::call('view:clear');
  	return 'clear';
});
