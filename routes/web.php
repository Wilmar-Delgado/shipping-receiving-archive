<?php

use App\Http\Controllers\Admin\UserController;
use App\Models\Document;
use App\Models\DocumentType;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::redirect('/', '/dashboard')->name('home');
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', fn () => Inertia::render('archive/Dashboard'))->name('dashboard');
    Route::get('/inbox', fn () => Inertia::render('archive/Placeholder', ['title' => 'Inbox', 'section' => 'Workspace', 'description' => 'A dedicated place to review incoming scans before filing.']))->name('inbox');
    Route::get('/search', fn () => Inertia::render('archive/Placeholder', ['title' => 'Global Search', 'section' => 'Workspace', 'description' => 'Find records across receiving, shipping, and job routers.']))->name('search');
    Route::get('/archive/{documentType:slug}', function (DocumentType $documentType) {
        Gate::authorize('viewAny', Document::class);

        return Inertia::render('archive/Placeholder', ['title' => $documentType->name, 'section' => ucfirst($documentType->section), 'description' => 'A separate archive for '.strtolower($documentType->name).'.']);
    })->name('archive.category');
    Route::get('/admin/users', [UserController::class, 'index'])->name('admin.users.index');
    Route::post('/admin/users', [UserController::class, 'store'])->name('admin.users.store');
    Route::patch('/admin/users/{user}', [UserController::class, 'update'])->name('admin.users.update');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
