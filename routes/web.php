<?php

use App\Http\Controllers\Admin\RoleListController;
use App\Http\Controllers\Admin\TargetUserListController;
use App\Http\Controllers\Admin\SubjectListController;
use Illuminate\Support\Facades\Route;

// Root → dashboard
Route::get('/', fn () => redirect()->route('dashboard'));

// Dashboard (screen layout step 1)
Route::get('/dashboard', fn () => view('dashboard'))->name('dashboard');

// Admin: Role List
Route::get('/admin/role-list', [RoleListController::class, 'index'])->name('admin.role-list');

// Admin: Target User List
Route::get('/admin/target-user-list', [TargetUserListController::class, 'index'])->name('admin.target-user-list');

// Admin: Subject/Lecture List
Route::get('/admin/subject-list', [SubjectListController::class, 'index'])->name('admin.subject-list');
