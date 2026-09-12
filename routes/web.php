<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
});

require __DIR__.'/admin.php';
require __DIR__.'/operasi.php';
require __DIR__.'/har.php';
require __DIR__.'/k3.php';
require __DIR__.'/settings.php';
