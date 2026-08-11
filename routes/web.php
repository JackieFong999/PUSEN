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
use App\Http\Controllers\Admin\StaffListController;
use App\Http\Controllers\Admin\StudentListController;
use App\Http\Controllers\Admin\CreateSenController;
use App\Http\Controllers\Admin\SenSearchController;
use App\Http\Controllers\Admin\SenTypeListController;
use App\Http\Controllers\Admin\EmailTemplateListController;
use App\Http\Controllers\Admin\ImportController;
use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

// ===================== AUTH (public) =====================
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// ===================== APPLICATION (login required) =====================
Route::middleware('auth')->group(function () {

    // Root -> dashboard
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

    // Admin: Staff List (AG Grid + search + status update)
    Route::get('/admin/staff-list', [StaffListController::class, 'index'])->name('admin.staff-list');
    Route::get('/admin/staff-list/search', [StaffListController::class, 'search']);
    Route::post('/admin/staff-list/update-status', [StaffListController::class, 'updateStatus']);

    // Admin: Student List (AG Grid + search, read-only)
    Route::get('/admin/student-list', [StudentListController::class, 'index'])->name('admin.student-list');
    Route::get('/admin/student-list/search', [StudentListController::class, 'search']);

    // Admin: Email Template
    Route::get('/admin/email-template-list', [EmailTemplateListController::class, 'index'])->name('admin.email-template-list');
    Route::get('/admin/email-template-list/data', [EmailTemplateListController::class, 'data']);
    Route::post('/admin/email-template-list/save', [EmailTemplateListController::class, 'save']);

    // Admin: SEN Type
    Route::get('/admin/sen-type-list', [SenTypeListController::class, 'index'])->name('admin.sen-type-list');

    // Admin: SEN Search (AG Grid + search, edit opens Create SEN in edit mode)
    Route::get('/admin/sen-search', [SenSearchController::class, 'index'])->name('admin.sen-search');
    Route::get('/admin/sen-search/search', [SenSearchController::class, 'search']);

    // Admin: Create SEN (also serves as Edit SEN when ?sen_id= is given)
    Route::get('/admin/create-sen', [CreateSenController::class, 'index'])->name('admin.create-sen');
    Route::get('/admin/create-sen/student-info', [CreateSenController::class, 'studentInfo']);
    Route::post('/admin/create-sen/save', [CreateSenController::class, 'save']);
    Route::post('/admin/create-sen/upload', [CreateSenController::class, 'upload']);
    Route::post('/admin/create-sen/remove-doc', [CreateSenController::class, 'removeDoc']);
    Route::post('/admin/create-sen/clear-staged', [CreateSenController::class, 'clearStaged']);

    // Admin: SEN document preview (serves the PDF for browser preview)
    Route::get('/admin/sen-doc/{filename}', [CreateSenController::class, 'previewDoc'])->where('filename', '.*');

    // Admin: Data Import (Subject first)
    Route::get('/admin/data-import', [ImportController::class, 'index'])->name('admin.data-import');
    Route::post('/admin/data-import/import', [ImportController::class, 'import']);

});
