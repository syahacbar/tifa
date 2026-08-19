<?php

use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminComplaintController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminPublicDocumentController;
use App\Http\Controllers\ComplaintController;
use App\Services\PublicDocumentService;
use App\Services\TifaDataService;
use Illuminate\Support\Facades\Route;

Route::get('/', function (TifaDataService $dataService, PublicDocumentService $documentService) {
    $publicDocuments = $documentService->getHomepageDocuments(6);
    $publicDocumentsCount = \Illuminate\Support\Facades\Schema::hasTable('public_documents')
        ? \App\Models\PublicDocument::active()->count()
        : count($publicDocuments);

    if ($publicDocumentsCount === 0 && !empty($publicDocuments)) {
        $publicDocumentsCount = count($publicDocuments);
    }

    return view('welcome', [
        'educationSummary' => $dataService->homepageSummary(),
        'districtSummary' => $dataService->homepageDistrictSummary(),
        'publicDocuments' => $publicDocuments,
        'publicDocumentsCount' => $publicDocumentsCount,
    ]);
})->name('home');

Route::post('/pengaduan', [ComplaintController::class, 'store'])->name('complaints.store');

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', [AdminAuthController::class, 'showLoginForm'])->name('login');
        Route::post('/login', [AdminAuthController::class, 'login'])->middleware('throttle:6,1')->name('login.store');
    });

    Route::middleware('auth')->group(function () {
        Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

        // Ruang Informasi Management
        Route::resource('dokumen', AdminPublicDocumentController::class)->parameters(['dokumen' => 'publicDocument'])->names('documents');

        // Pengaduan Layanan Management
        Route::get('/pengaduan', [AdminComplaintController::class, 'index'])->name('complaints.index');
        Route::get('/pengaduan/{complaint}', [AdminComplaintController::class, 'show'])->name('complaints.show');
        Route::patch('/pengaduan/{complaint}/status', [AdminComplaintController::class, 'updateStatus'])->name('complaints.status');
        Route::get('/pengaduan/{complaint}/attachment', [AdminComplaintController::class, 'downloadAttachment'])->name('complaints.attachment');
    });
});
