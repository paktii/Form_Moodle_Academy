<?php

use App\Http\Controllers\PortalController;
use Illuminate\Support\Facades\Route;

Route::controller(PortalController::class)->group(function () {
    Route::get('/login', 'login')->name('login');
    Route::post('/login', 'enter')->name('login.enter');
    Route::post('/logout', 'logout')->name('logout');

    Route::middleware('portal.role:user')->group(function () {
        Route::get('/', 'index')->defaults('role', 'user')->name('requests.index');
        Route::redirect('/request/create', '/request/create/1')->name('requests.create');
        Route::get('/request/create/{step}', 'form')->whereNumber('step')->name('requests.form');
        Route::post('/request/create/{step}', 'saveStep')->whereNumber('step')->name('requests.save-step');
        Route::post('/request/create/{step}/clear', 'clearStep')->whereNumber('step')->name('requests.clear-step');
        Route::post('/request/discard', 'discard')->name('requests.discard');
        Route::post('/request/{id}/edit', 'edit')->whereNumber('id')->name('requests.edit');
        Route::get('/request/{id}', 'detail')->defaults('role', 'user')->whereNumber('id')->name('requests.show');
        Route::post('/request/{id}/upload', 'upload')->whereNumber('id')->name('requests.upload');
        Route::post('/request/{id}/student-roster', 'uploadStudentRoster')->whereNumber('id')->name('requests.student-roster.upload');
    });

    Route::middleware('portal.role:officer')->group(function () {
        Route::get('/officer/reviews', 'index')->defaults('role', 'officer')->name('officer.reviews');
        Route::get('/officer/reviews/{id}', 'detail')->defaults('role', 'officer')->whereNumber('id')->name('officer.show');
        Route::post('/officer/reviews/{id}', 'review')->whereNumber('id')->name('officer.review');
        Route::post('/officer/reviews/{id}/course-id', 'courseId')->whereNumber('id')->name('officer.course-id');
        Route::post('/officer/reviews/{id}/student-roster/acknowledge', 'acknowledgeStudentRoster')->whereNumber('id')->name('officer.student-roster.acknowledge');
        Route::post('/officer/reviews/{id}/student-roster/reopen', 'reopenStudentRoster')->whereNumber('id')->name('officer.student-roster.reopen');
    });

    Route::middleware('portal.role:approver')->group(function () {
        Route::get('/approver/reviews', 'index')->defaults('role', 'approver')->name('approver.reviews');
        Route::get('/approver/reviews/{id}', 'detail')->defaults('role', 'approver')->whereNumber('id')->name('approver.show');
        Route::post('/approver/reviews/{id}', 'approve')->whereNumber('id')->name('approver.approve');
    });

    Route::middleware('portal.role:user,officer,approver')->group(function () {
        Route::get('/documents/{id}', 'document')->where('id', 'draft|[0-9]+')->name('requests.document');
        Route::get('/documents/{id}/download', 'downloadDocument')->where('id', 'draft|[0-9]+')->name('requests.document.download');
        Route::get('/request/{id}/attachments/{kind}', 'attachment')->whereNumber('id')->name('requests.attachment');
    });
});
