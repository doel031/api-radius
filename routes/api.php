<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\NasController;
use App\Http\Controllers\Api\V1\GroupController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\IsolateController;
use App\Http\Controllers\Api\V1\StatusController;
use App\Http\Middleware\HmacAuthenticate;

Route::prefix('v1')->group(function () {
    Route::middleware([HmacAuthenticate::class])->group(function () {
        // 1. NAS API (CRUD)
        Route::apiResource('nas', NasController::class);

        // 2. GROUP API (CRUD)
        Route::prefix('groups')->group(function () {
            Route::get('/', [GroupController::class, 'index']);          // Read
            Route::post('/', [GroupController::class, 'store']);         // Create
            Route::put('/{groupname}', [GroupController::class, 'update']); // Update attribute
            Route::delete('/{groupname}', [GroupController::class, 'destroy']); // Delete group
        });

        // 3. USER API (CRUD)
        Route::apiResource('users', UserController::class);

        // 4. ISOLATE API
        Route::post('/isolate', [IsolateController::class, 'isolate']);
        Route::post('/isolate/restore', [IsolateController::class, 'restore']);

        // 5. STATUS API
        Route::get('/status/nas', [StatusController::class, 'nasStatus']);
        Route::get('/status/users-online', [StatusController::class, 'onlineUsers']);
    });
});