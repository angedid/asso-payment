<?php

use App\Http\Controllers\ApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/test', function (Request $request) {
    return response()->json(['test' => true]);
});


Route::post('tenant-provisions', [ApiController::class, 'provisionTenant'])->middleware('rabbitmq.client');

Route::post('access-tokens', [ApiController::class, 'saveAccessToken'])->middleware('rabbitmq.client');

Route::post('logout', [ApiController::class, 'logout'])->middleware('rabbitmq.client');

Route::post('/payments', [ApiController::class, 'makeServicePayment'])->middleware('iam.auth.api');

/*Route::put('/payments/mtn-cameroon', [ApiController::class, 'makeServicePaymentMtnCameroon'])->middleware('iam.auth.api');
Route::put('/payments/orange-cameroon', [ApiController::class, 'makeServicePaymentOrangeCameroon'])->middleware('iam.auth.api');*/

