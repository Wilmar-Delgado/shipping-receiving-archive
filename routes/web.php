<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\ArchiveController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\InboxController;
use App\Support\ArchiveCategories;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/inbox', [InboxController::class, 'index'])->name('inbox');
    Route::post('/inbox', [InboxController::class, 'store'])->middleware('throttle:30,1')->name('inbox.store');
    Route::get('/documents/{document:uuid}', [DocumentController::class, 'show'])->name('documents.show');
    Route::patch('/documents/{document:uuid}', [DocumentController::class, 'update'])->name('documents.update');
    Route::post('/documents/{document:uuid}/file', [DocumentController::class, 'file'])->name('documents.file');
    Route::post('/documents/{document:uuid}/discard', [DocumentController::class, 'discard'])->name('documents.discard');
    Route::get('/documents/{document:uuid}/preview', [DocumentController::class, 'preview'])->name('documents.preview');
    Route::get('/documents/{document:uuid}/download', [DocumentController::class, 'download'])->name('documents.download');
    Route::get('/search', [ArchiveController::class, 'search'])->name('search');
    Route::get('/archive/rmas', function (Request $request) {
        $query = $request->getQueryString();

        return redirect('/archive/'.ArchiveCategories::RMA_SLUG.($query ? '?'.$query : ''), 301);
    })->name('archive.rmas.legacy');
    Route::get('/archive/return-merchandise-authorization', function (Request $request) {
        $query = $request->getQueryString();

        return redirect('/archive/'.ArchiveCategories::RMA_SLUG.($query ? '?'.$query : ''), 301);
    })->name('archive.rma.singular');
    Route::get('/archive/{documentType:slug}', [ArchiveController::class, 'category'])->name('archive.category');
    Route::get('/admin/users', [UserController::class, 'index'])->name('admin.users.index');
    Route::post('/admin/users', [UserController::class, 'store'])->name('admin.users.store');
    Route::patch('/admin/users/{user}', [UserController::class, 'update'])->name('admin.users.update');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
