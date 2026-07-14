<?php

use App\Http\Controllers\Admin\AttendanceReportDownloadController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\ProfileController;
use App\Http\Middleware\RequireSuperadmin;
use Illuminate\Support\Facades\Route;

// Route::view('/', 'home');

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', RequireSuperadmin::class])
    ->name('dashboard');

Route::get('/attendance-imports/{attendanceImport}/download', [AttendanceReportDownloadController::class, 'download'])
    ->middleware(['auth'])
    ->name('attendance-imports.download');

Route::middleware(['auth', 'verified'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::resource('departments', DepartmentController::class)->except(['show']);
    });

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
