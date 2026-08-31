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
use App\Http\Controllers\Admin\EmailManagementController;
use App\Http\Controllers\Admin\TemporarySpecialSupportListController;
use App\Http\Controllers\Admin\ImportController;
use App\Http\Controllers\Admin\HousekeepingController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\SsoLoginController;
use Illuminate\Support\Facades\Route;

// ===================== AUTH (public) =====================
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// ===================== SSO (public, SAML 2.0) =====================
Route::get('/login/sso', [SsoLoginController::class, 'redirectToIdp'])->name('login.sso');
Route::post('/login/sso/callback', [SsoLoginController::class, 'callback'])->name('login.sso.callback');
Route::get('/login/sso/metadata', [SsoLoginController::class, 'metadata'])->name('login.sso.metadata');

// ===================== APPLICATION (login required) =====================
Route::middleware(['auth', 'role.access'])->group(function () {

    // Root -> SEN Search (Dashboard temporarily hidden for demo, 2026-08-19)
    Route::get('/', fn () => redirect()->route('admin.sen-search'));

    // Dashboard: import log for all types (per-file summary, latest 50, filterable)
    Route::get('/dashboard', function () {
        $status = request()->query('status');
        $status = in_array($status, ['success', 'failure'], true) ? $status : null;

        $query = DB::connection('pusen')->table('tblImport_Log');
        if ($status === 'success') {
            $query->where('Import_Status', 'Success');
        } elseif ($status === 'failure') {
            $query->where('Import_Status', 'Failure');
        }
        $importLogs = $query->orderByDesc('Id')
            ->limit(50)
            ->get(['created_at', 'File_Name', 'FileType', 'Import_Status', 'CSV_Row_Count', 'Import_Count', 'Updated_Count', 'Duplicated_Count', 'Error_Count', 'created_by']);

        // Login statistic: last 10 days (HK local date), success (Y) vs failure (N)
        // Login_Time is stored in UTC; group by Asia/Hong_Kong calendar date.
        $loginRows = DB::connection('pusen')->table('tblLogin_Log')
            ->where('Login_Time', '>=', now()->subDays(12))
            ->get(['Login_Time', 'Status']);

        $days = [];
        for ($i = 9; $i >= 0; $i--) {
            $d = now('Asia/Hong_Kong')->subDays($i)->toDateString();
            $days[$d] = ['date' => $d, 'success' => 0, 'failure' => 0];
        }
        foreach ($loginRows as $row) {
            $local = \Carbon\Carbon::parse($row->Login_Time, 'UTC')->setTimezone('Asia/Hong_Kong')->toDateString();
            if (isset($days[$local])) {
                if ($row->Status === 'Y') {
                    $days[$local]['success']++;
                } else {
                    $days[$local]['failure']++;
                }
            }
        }
        $loginStats = array_values($days);

        return view('dashboard', compact('importLogs', 'status', 'loginStats'));
    })->name('dashboard');

    // Admin: Role List
    Route::get('/admin/role-list', [RoleListController::class, 'index'])->name('admin.role-list');

    // Admin: Target User List
    Route::get('/admin/target-user-list', [TargetUserListController::class, 'index'])->name('admin.target-user-list');

    // Admin: Subject/Lecture List
    Route::get('/admin/subject-list', [SubjectListController::class, 'index'])->name('admin.subject-list');
    Route::get('/admin/subject-list/search', [SubjectListController::class, 'search'])->name('admin.subject-list.search');

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

    // Admin: Temporary Special Support
    Route::get('/admin/temporary-special-support-list', [TemporarySpecialSupportListController::class, 'index'])->name('admin.temporary-special-support-list');
    Route::post('/admin/temporary-special-support-list/store', [TemporarySpecialSupportListController::class, 'store'])->name('admin.temporary-special-support-list.store');
    Route::post('/admin/temporary-special-support-list/update', [TemporarySpecialSupportListController::class, 'update'])->name('admin.temporary-special-support-list.update');
    Route::post('/admin/temporary-special-support-list/delete', [TemporarySpecialSupportListController::class, 'destroy'])->name('admin.temporary-special-support-list.delete');
Route::post('/admin/temporary-special-support-list/reorder', [TemporarySpecialSupportListController::class, 'reorder'])->name('admin.temporary-special-support-list.reorder');

    // Admin: Advisor List
    Route::get('/admin/advisor-list', [AdvisorListController::class, 'index'])->name('admin.advisor-list');
    Route::get('/admin/advisor-list/search', [AdvisorListController::class, 'search'])->name('admin.advisor-list.search');

    // Admin: Student Registration
    Route::get('/admin/student-registration-list', [StudentRegistrationListController::class, 'index'])->name('admin.student-registration-list');
    Route::get('/admin/student-registration-list/search', [StudentRegistrationListController::class, 'search'])->name('admin.student-registration-list.search');

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
    Route::post('/admin/sen-type-list/store', [SenTypeListController::class, 'store'])->name('admin.sen-type-list.store');
    Route::post('/admin/sen-type-list/update', [SenTypeListController::class, 'update'])->name('admin.sen-type-list.update');
    Route::post('/admin/sen-type-list/delete', [SenTypeListController::class, 'destroy'])->name('admin.sen-type-list.delete');
Route::post('/admin/sen-type-list/reorder', [SenTypeListController::class, 'reorder'])->name('admin.sen-type-list.reorder');

    // Admin: SEN Search (AG Grid + search, edit opens Create SEN in edit mode)
    Route::get('/admin/sen-search', [SenSearchController::class, 'index'])->name('admin.sen-search');
    Route::get('/admin/sen-search/search', [SenSearchController::class, 'search']);
Route::get('/admin/sen-search/export', [SenSearchController::class, 'export']);

    // Admin: Create SEN (also serves as Edit SEN when ?sen_id= is given)
    Route::get('/admin/create-sen', [CreateSenController::class, 'index'])->name('admin.create-sen');
    Route::get('/admin/create-sen/student-info', [CreateSenController::class, 'studentInfo']);
    Route::post('/admin/create-sen/save', [CreateSenController::class, 'save']);
    Route::post('/admin/create-sen/upload', [CreateSenController::class, 'upload']);
    Route::post('/admin/create-sen/remove-doc', [CreateSenController::class, 'removeDoc']);
    Route::post('/admin/create-sen/clear-staged', [CreateSenController::class, 'clearStaged']);

    // Admin: Email Management (SA only)
    Route::get('/admin/email-management', [EmailManagementController::class, 'index'])->name('admin.email-management');
    Route::get('/admin/email-management/data', [EmailManagementController::class, 'data']);
    Route::post('/admin/email-management/data', [EmailManagementController::class, 'data']);
    Route::get('/admin/email-management/case-search', [EmailManagementController::class, 'caseSearch']);
    Route::get('/admin/email-management/student-search', [EmailManagementController::class, 'studentSearch']);
    Route::post('/admin/email-management/send', [EmailManagementController::class, 'send']);

    // Admin: SEN document preview (serves the PDF for browser preview)
    Route::get('/admin/sen-doc/{filename}', [CreateSenController::class, 'previewDoc'])->where('filename', '.*');

    // Admin: SEN document locked viewer (PDF.js — no download / no print)
    Route::get('/admin/sen-doc-viewer/{filename}', [CreateSenController::class, 'viewer'])->where('filename', '.*')->name('admin.sen-doc.viewer');

    // Admin: Data Import (Subject first)
    Route::get('/admin/data-import', [ImportController::class, 'index'])->name('admin.data-import');
    Route::post('/admin/data-import/import', [ImportController::class, 'import']);
    Route::post('/admin/data-import/send-email', [ImportController::class, 'sendEmail']);

    // Admin: Housekeeping (SA only — nav + role.access)
    Route::get('/admin/housekeeping', [HousekeepingController::class, 'index'])->name('admin.housekeeping');
    Route::post('/admin/housekeeping/student/preview', [HousekeepingController::class, 'previewStudent']);
    Route::post('/admin/housekeeping/student/run', [HousekeepingController::class, 'runStudent']);
    Route::get('/admin/housekeeping/runs/search', [HousekeepingController::class, 'searchRuns']);

});
