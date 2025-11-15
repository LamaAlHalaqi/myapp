<?php

use App\Http\Controllers\ComplaintController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/test', function () {
    return response()->json(['message' => 'API is working in Laravel 12!']);
});


    Route::post('register', [UserController::class, 'register']);
    Route::post('verify_otp', [UserController::class, 'verify']);
    Route::post('login', [UserController::class, 'login']);
    // Route::post('logout', [UserController::class, 'logout']);
Route::middleware('auth:sanctum')->post('/logout', [UserController::class, 'logout']);
Route::middleware('auth:sanctum')->group(function(){
    Route::post('complaints', [ComplaintController::class,'submit']);
    Route::post('complaints/{id}/lock', [ComplaintController::class,'lock']);
    Route::post('complaints/{id}/unlock', [ComplaintController::class,'unlock']);
    Route::post('complaints/{id}/status', [ComplaintController::class,'updateStatus']);
    Route::get('complaints', [ComplaintController::class,'allComplaints']);
});
