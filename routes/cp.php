<?php

use Illuminate\Support\Facades\Route;
use Kreatif\StatamicForms\Http\Controllers\EmailPreviewController;

Route::prefix('kreatif-forms')->name('kreatif-forms.')->group(function () {
    // Email preview routes
    Route::get('preview', [EmailPreviewController::class, 'index'])->name('preview.index');
    Route::get('preview/{formHandle}/action/{actionClass}', [EmailPreviewController::class, 'previewActionTemplate'])->name('preview.action');
    Route::get('preview/{formHandle}/template/{submissionId?}', [EmailPreviewController::class, 'templatePreview'])->name('preview.template');
});
