<?php

use App\Http\Controllers\Admin\RoleListController;
use App\Http\Controllers\Admin\TargetUserListController;
use App\Http\Controllers\Admin\SubjectListController;
use App\Http\Controllers\Admin\FundTypeListController;
use App\Http\Controllers\Admin\StudentStatusListController;
use App\Http\Controllers\Admin\SubjectTypeListController;
use App\Http\Controllers\Admin\AdvisorTypeListController;
use App\Http\Controllers\Admin\AcademicYearSemesterListController;
use App\Http\Controllers\Admin\AdvisorListController;
use App\Http\Controllers\Admin\StudentRegistrationListController;
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

// Admin: Fund Type
Route::get('/admin/fund-type-list', [FundTypeListController::class, 'index'])->name('admin.fund-type-list');

// Admin: Student Status
Route::get('/admin/student-status-list', [StudentStatusListController::class, 'index'])->name('admin.student-status-list');

// Admin: Subject Type
Route::get('/admin/subject-type-list', [SubjectTypeListController::class, 'index'])->name('admin.subject-type-list');

// Admin: Advisor Type
Route::get('/admin/advisor-type-list', [AdvisorTypeListController::class, 'index'])->name('admin.advisor-type-list');

// Admin: Academic Year Semester
Route::get('/admin/academic-year-semester-list', [AcademicYearSemesterListController::class, 'index'])->name('admin.academic-year-semester-list');

// Admin: Advisor List
Route::get('/admin/advisor-list', [AdvisorListController::class, 'index'])->name('admin.advisor-list');

// Admin: Student Registration
Route::get('/admin/student-registration-list', [StudentRegistrationListController::class, 'index'])->name('admin.student-registration-list');
