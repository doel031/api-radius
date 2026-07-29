<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ApiKeyManagerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LandingController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('landing');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth'])->prefix('admin')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');
    Route::get('/api-keys', [ApiKeyManagerController::class, 'index'])->name('admin.apikeys.index');
    Route::post('/api-keys', [ApiKeyManagerController::class, 'store'])->name('admin.apikeys.store');
    Route::patch('/api-keys/{id}/toggle', [ApiKeyManagerController::class, 'toggle'])->name('admin.apikeys.toggle');
    Route::post('/api-keys/{id}/regenerate', [ApiKeyManagerController::class, 'regenerate'])->name('admin.apikeys.regenerate');
    Route::delete('/api-keys/{id}', [ApiKeyManagerController::class, 'destroy'])->name('admin.apikeys.destroy');
});

require __DIR__.'/auth.php';
