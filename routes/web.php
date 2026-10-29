<?php

use App\Http\Controllers\Admin\AttendanceReportDownloadController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\FindingAttachmentDownloadController;
use App\Http\Controllers\GlobalChatAttachmentDownloadController;
use App\Http\Controllers\LtroMttrImportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ScmDocumentController;
use App\Http\Controllers\TicketAttachmentDownloadController;
use App\Http\Controllers\TicketCommentAttachmentDownloadController;
use App\Http\Controllers\VendorPortalController;
use App\Http\Controllers\VendorPublicPortalController;
use App\Http\Controllers\WorkTaskPermitResultDownloadController;
use App\Http\Middleware\RequireLtroAccess;
use App\Http\Middleware\RequireSuperadmin;
use App\Http\Middleware\VendorPortalResponse;
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
    '/global-chat/messages/{message}/attachments/{attachment}',
    GlobalChatAttachmentDownloadController::class
)
    ->whereNumber(['message', 'attachment'])
    ->middleware(['auth'])
    ->name('global-chat.attachments.download');

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

Route::prefix('vendor')->middleware([VendorPortalResponse::class, 'throttle:60,1'])->group(function (): void {
    Route::get('/wilayah/{city}', [VendorPortalController::class, 'address'])->where('city', '[0-9]{2}\.[0-9]{2}')->name('vendor.address');
    Route::get('/', [VendorPublicPortalController::class, 'index'])->name('vendor.index');
    Route::post('/daftar', [VendorPublicPortalController::class, 'register'])->middleware('throttle:5,1')->name('vendor.register');
    Route::post('/buka', [VendorPublicPortalController::class, 'resume'])->middleware('throttle:10,1')->name('vendor.resume');
});

Route::prefix('vendor/form-005/{token}')->where(['token' => '[a-f0-9]{64}'])->middleware([VendorPortalResponse::class, 'throttle:60,1'])->group(function (): void {
    Route::get('/bagian/{step}', [VendorPortalController::class, 'show'])->where('step', '[1-5]')->name('vendor.portal');
    Route::post('/bagian/{step}', [VendorPortalController::class, 'save'])->where('step', '[1-5]')->middleware('throttle:30,1')->name('vendor.portal.save');
    Route::post('/dokumen/{code}', [VendorPortalController::class, 'upload'])->middleware('throttle:20,1')->name('vendor.portal.upload');
    Route::get('/file/{file}', [VendorPortalController::class, 'download'])->whereNumber('file')->name('vendor.portal.download');
});

Route::middleware('auth')->prefix('scm')->name('scm.')->group(function (): void {
    Route::get('/vendor-form/{registration}', [VendorPortalController::class, 'review'])->name('vendor-review');
    Route::post('/vendor-form/{registration}', [VendorPortalController::class, 'decide'])->name('vendor-decide');
    Route::get('/vendor-form/{registration}/file/{file}', [VendorPortalController::class, 'reviewDownload'])->whereNumber('file')->name('vendor-review-download');
    Route::get('/ttd-profil/{signature}', [ScmDocumentController::class, 'profileSignature'])->name('signature-profile');
    Route::get('/permintaan/{procurement}/ttd', [ScmDocumentController::class, 'preparedSignature'])->name('signature-prepared');
    Route::get('/persetujuan/{approval}/ttd', [ScmDocumentController::class, 'approvalSignature'])->name('signature-approval');
    Route::get('/lampiran/{attachment}', [ScmDocumentController::class, 'download'])->name('download');
    Route::get('/cbe/{cbe}/cetak', [ScmDocumentController::class, 'print'])->name('print');
    Route::get('/cbe-persetujuan/{cbeApproval}/ttd', [ScmDocumentController::class, 'cbeSignature'])->name('signature-cbe');
    Route::get('/cbe/{cbe}/penawaran-terpilih', [ScmDocumentController::class, 'selectedQuotation'])->name('selected-quotation');
});
