<?php

use App\Http\Controllers\PortalController;
use Illuminate\Support\Facades\Route;

Route::controller(PortalController::class)->middleware('portal.draft.lifecycle')->group(function () {
    Route::get('/login', 'login')->name('login');
    Route::post('/login', 'enter')->middleware('throttle:portal-login')->name('login.enter');
    Route::post('/logout', 'logout')->name('logout');

    Route::get('/dean/reviews/{id}', 'deanReviewConfirmation')
        ->whereNumber('id')
        ->middleware('signed')->name('dean.review.confirm');
    Route::post('/dean/reviews/{id}/confirm', 'deanReviewDecision')
        ->whereNumber('id')
        ->middleware('signed')->name('dean.review.decide');
    Route::get('/dean/reviews/{id}/document', 'deanReviewDocument')
        ->whereNumber('id')
        ->middleware('signed')->name('dean.review.document');
    Route::get('/dean/reviews/{id}/attachments/{document}', 'deanReviewAttachment')
        ->whereNumber('id')->whereNumber('document')
        ->middleware('signed')->name('dean.review.attachment');

    // Keep links from messages sent before the single-detail-link flow was introduced usable.
    Route::get('/dean/reviews/{id}/{decision}', 'deanReviewConfirmation')
        ->whereNumber('id')->whereIn('decision', ['approve', 'reject'])
        ->middleware('signed')->name('dean.review.legacy-confirm');
    Route::post('/dean/reviews/{id}/{decision}/confirm', 'deanReviewDecision')
        ->whereNumber('id')->whereIn('decision', ['approve', 'reject'])
        ->middleware('signed')->name('dean.review.legacy-decide');

    Route::get('/', 'home')
        ->middleware('portal.role:user,officer')
        ->name('requests.index');

    Route::middleware('portal.role:user')->group(function () {
        Route::redirect('/request/create', '/request/create/1')->name('requests.create');
        Route::get('/request/create/{step}', 'form')->whereNumber('step')->name('requests.form');
        Route::post('/request/create/{step}', 'saveStep')->whereNumber('step')->name('requests.save-step');
        Route::post('/request/create/{step}/clear', 'clearStep')->whereNumber('step')->name('requests.clear-step');
        Route::redirect('/request/website/create', '/request/website/create/1')->name('website-requests.create');
        Route::get('/request/website/create/{step}', 'websiteForm')->whereNumber('step')->name('website-requests.form');
        Route::post('/request/website/create/{step}', 'saveWebsiteStep')->whereNumber('step')->name('website-requests.save-step');
        Route::post('/request/website/create/{step}/clear', 'clearWebsiteStep')->whereNumber('step')->name('website-requests.clear-step');
        Route::redirect('/request/website-contact-change/create', '/request/website-contact-change/create/1')->name('website-contact-change-requests.create');
        Route::get('/request/website-contact-change/create/{step}', 'websiteContactChangeForm')->whereNumber('step')->name('website-contact-change-requests.form');
        Route::post('/request/website-contact-change/create/{step}', 'saveWebsiteContactChangeStep')->whereNumber('step')->name('website-contact-change-requests.save-step');
        Route::post('/request/website-contact-change/create/{step}/clear', 'clearWebsiteContactChangeStep')->whereNumber('step')->name('website-contact-change-requests.clear-step');
        Route::redirect('/request/emeeting/create', '/request/emeeting/create/1')->name('emeeting-requests.create');
        Route::get('/request/emeeting/create/{step}', 'emeetingForm')->whereNumber('step')->name('emeeting-requests.form');
        Route::post('/request/emeeting/create/{step}', 'saveEmeetingStep')->whereNumber('step')->name('emeeting-requests.save-step');
        Route::post('/request/emeeting/create/{step}/clear', 'clearEmeetingStep')->whereNumber('step')->name('emeeting-requests.clear-step');
        Route::redirect('/request/website-storage/create', '/request/website-storage/create/1')->name('website-storage-requests.create');
        Route::get('/request/website-storage/create/{step}', 'websiteStorageForm')->whereNumber('step')->name('website-storage-requests.form');
        Route::post('/request/website-storage/create/{step}', 'saveWebsiteStorageStep')->whereNumber('step')->name('website-storage-requests.save-step');
        Route::post('/request/website-storage/create/{step}/clear', 'clearWebsiteStorageStep')->whereNumber('step')->name('website-storage-requests.clear-step');
        Route::post('/request/discard', 'discard')->name('requests.discard');
        Route::get('/request/{id}', 'detail')->defaults('role', 'user')->whereNumber('id')->name('requests.show');
        Route::post('/request/{id}/dean-email/resend', 'resendDeanReviewEmail')->whereNumber('id')->name('requests.dean-email.resend');
        Route::post('/request/{id}/student-roster', 'uploadStudentRoster')->whereNumber('id')->name('requests.student-roster.upload');
        Route::post('/request/{id}/student-roster/reopen-request', 'requestStudentRosterReopen')->whereNumber('id')->name('requests.student-roster.reopen-request');
    });

    Route::middleware('portal.role:officer')->group(function () {
        Route::get('/officer/reviews', 'index')->defaults('role', 'officer')->name('officer.reviews');
        Route::get('/officer/reviews/{id}', 'detail')->defaults('role', 'officer')->whereNumber('id')->name('officer.show');
        Route::post('/officer/reviews/{id}', 'review')->whereNumber('id')->name('officer.review');
        Route::post('/officer/reviews/{id}/result', 'updateResult')->whereNumber('id')->name('officer.result.update');
        Route::post('/officer/reviews/{id}/student-roster/acknowledge', 'acknowledgeStudentRoster')->whereNumber('id')->name('officer.student-roster.acknowledge');
        Route::post('/officer/reviews/{id}/student-roster/reopen', 'reopenStudentRoster')->whereNumber('id')->name('officer.student-roster.reopen');
    });

    Route::middleware('portal.role:user,officer')->group(function () {
        Route::get('/documents/{id}', 'document')->where('id', 'draft|[0-9]+')->name('requests.document');
        Route::get('/documents/{id}/download', 'downloadDocument')->where('id', 'draft|[0-9]+')->name('requests.document.download');
        Route::get('/website-documents/{id}', 'websiteDocument')->where('id', 'draft|[0-9]+')->name('website-requests.document');
        Route::get('/website-documents/{id}/download', 'downloadWebsiteDocument')->where('id', 'draft|[0-9]+')->name('website-requests.document.download');
        Route::get('/website-contact-change-documents/{id}', 'websiteContactChangeDocument')->where('id', 'draft|[0-9]+')->name('website-contact-change-requests.document');
        Route::get('/website-contact-change-documents/{id}/download', 'downloadWebsiteContactChangeDocument')->where('id', 'draft|[0-9]+')->name('website-contact-change-requests.document.download');
        Route::get('/emeeting-documents/{id}', 'emeetingDocument')->where('id', 'draft|[0-9]+')->name('emeeting-requests.document');
        Route::get('/emeeting-documents/{id}/download', 'downloadEmeetingDocument')->where('id', 'draft|[0-9]+')->name('emeeting-requests.document.download');
        Route::get('/website-storage-documents/{id}', 'websiteStorageDocument')->where('id', 'draft|[0-9]+')->name('website-storage-requests.document');
        Route::get('/website-storage-documents/{id}/download', 'downloadWebsiteStorageDocument')->where('id', 'draft|[0-9]+')->name('website-storage-requests.document.download');
        Route::get('/request/{id}/attachments/{kind}', 'attachment')->whereNumber('id')->name('requests.attachment');
    });
});
