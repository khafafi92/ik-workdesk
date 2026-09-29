<?php

use App\Http\Controllers\Admin\AttendanceReportDownloadController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\FindingAttachmentDownloadController;
use App\Http\Controllers\LtroMttrImportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TicketAttachmentDownloadController;
use App\Http\Controllers\TicketCommentAttachmentDownloadController;
use App\Http\Controllers\WorkTaskPermitResultDownloadController;
use App\Http\Middleware\RequireLtroAccess;
use App\Http\Middleware\RequireSuperadmin;
use App\Livewire\AvailabilityLtro1b;
use App\Livewire\DailyInput;
use App\Livewire\MttrForm;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::redirect('/ltro', '/ltro/monitoring')->name('home');
Route::redirect('/ltro/monitoring-legacy', '/ltro/monitoring')->name('monitoring');
Route::redirect('/ltro/daily-reports', '/panel/ltro-daily-reports')->name('daily.reports.index');
Route::get('/ltro/daily-input', DailyInput::class)
    ->middleware(['auth', RequireLtroAccess::class])
    ->name('daily.input');
Route::get('/ltro/availability', AvailabilityLtro1b::class)
    ->middleware(['auth', RequireLtroAccess::class])
    ->name('availability.ltro-1b');
Route::redirect('/ltro/records', '/panel/ltro-mttr-records')->name('mttr-records.index');
Route::post('/ltro/import', LtroMttrImportController::class)
    ->middleware(['auth', RequireLtroAccess::class])
    ->name('mttr-records.import');

Route::get('/ltro/monitoring', MttrForm::class)
    ->middleware(['auth', RequireLtroAccess::class])
    ->name('ltro.monitoring');

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
