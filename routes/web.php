<?php

use App\Http\Controllers\Admin\AttendanceReportDownloadController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\FindingAttachmentDownloadController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TicketAttachmentDownloadController;
use App\Http\Controllers\TicketCommentAttachmentDownloadController;
use App\Http\Controllers\WorkTaskPermitResultDownloadController;
use App\Http\Middleware\RequireSuperadmin;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', RequireSuperadmin::class])
    ->name('dashboard');

Route::get('/attendance-imports/{attendanceImport}/download', [AttendanceReportDownloadController::class, 'download'])
    ->middleware(['auth'])
    ->name('attendance-imports.download');

Route::get(
    '/ticket-comments/{ticketComment}/attachments/{attachmentIndex}',
    TicketCommentAttachmentDownloadController::class
)
    ->whereNumber('attachmentIndex')
    ->middleware(['auth'])
    ->name('ticket-comments.attachments.download');

Route::get(
    '/tickets/{ticket}/attachments/{attachmentIndex}',
    TicketAttachmentDownloadController::class
)
    ->whereNumber('attachmentIndex')
    ->middleware(['auth'])
    ->name('tickets.attachments.download');

Route::get(
    '/findings/{workTaskFinding}/attachments/{attachmentType}/{attachmentIndex}',
    FindingAttachmentDownloadController::class
)
    ->whereIn('attachmentType', ['reviewer', 'response'])
    ->whereNumber('attachmentIndex')
    ->middleware(['auth'])
    ->name('findings.attachments.download');

Route::get(
    '/work-tasks/{workTask}/permit-results/{attachmentIndex}',
    WorkTaskPermitResultDownloadController::class
)
    ->whereNumber('attachmentIndex')
    ->middleware(['auth'])
    ->name('work-tasks.permit-results.download');

Route::middleware(['auth', 'verified', RequireSuperadmin::class])
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
