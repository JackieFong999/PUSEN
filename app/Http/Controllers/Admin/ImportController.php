<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdvisorImportService;
use App\Services\EmailSenService;
use App\Services\StaffImportService;
use App\Services\StudentImportService;
use App\Services\StudentRegImportService;
use App\Services\SubjectImportService;
use Illuminate\Http\Request;

class ImportController extends Controller
{
    public function __construct(
        private SubjectImportService $importService,
        private StaffImportService $staffImportService,
        private StudentImportService $studentImportService,
        private AdvisorImportService $advisorImportService,
        private StudentRegImportService $studentRegImportService,
    ) {
    }

    /**
     * Data Import screen: shows the import table (Subject + Staff + Student active).
     */
    public function index()
    {
        $subject = $this->importService->latestFile();
        $staff   = $this->staffImportService->latestFile();
        $student = $this->studentImportService->latestFile();
        $advisor = $this->advisorImportService->latestFile();
        $studentReg = $this->studentRegImportService->latestFile();

        return view('admin.data-import', [
            'sftpError' => $subject['error'] ?? $staff['error'] ?? $student['error'] ?? $advisor['error'] ?? $studentReg['error'],
            'functions' => [
                [
                    'type'    => 'subject',
                    'label'   => 'Subject / Lecture List',
                    'desc'    => 'tblSubject — composite key: Academic Year + Semester + Subject Code',
                    'file'    => $subject['filename'],
                    'ready'   => $subject['exists'],
                    'last'    => $this->importService->lastImportedFile(),
                    'confirm' => 'This will validate every row and then insert/update tblSubject in one transaction.',
                ],
                [
                    'type'    => 'staff',
                    'label'   => 'Staff List',
                    'desc'    => 'tblStaff — key: Staff_Id',
                    'file'    => $staff['filename'],
                    'ready'   => $staff['exists'],
                    'last'    => $this->staffImportService->lastImportedFile(),
                    'confirm' => 'This will validate every row and then insert/update tblStaff in one transaction.',
                ],
                [
                    'type'    => 'student',
                    'label'   => 'Student List',
                    'desc'    => 'tblStudent — key: Student_Id',
                    'file'    => $student['filename'],
                    'ready'   => $student['exists'],
                    'last'    => $this->studentImportService->lastImportedFile(),
                    'confirm' => 'This will validate every row and then insert/update tblStudent in one transaction.',
                ],
                [
                    'type'    => 'advisor',
                    'label'   => 'Advisor List for the Student List',
                    'desc'    => 'tblAdvisor_Student — full-row dedup (no key), no update case',
                    'file'    => $advisor['filename'],
                    'ready'   => $advisor['exists'],
                    'last'    => $this->advisorImportService->lastImportedFile(),
                    'confirm' => 'This will validate every row and then insert new advisor-student records in one transaction. Existing records are never updated.',
                ],
                [
                    'type'    => 'studentreg',
                    'label'   => 'Student Registration',
                    'desc'    => 'tblStudent_Reg — one row = many (Student_Id, Subject_Code) pairs',
                    'file'    => $studentReg['filename'],
                    'ready'   => $studentReg['exists'],
                    'last'    => $this->studentRegImportService->lastImportedFile(),
                    'confirm' => 'This will validate every row and insert new student-subject pairs in one transaction. Existing pairs are skipped.',
                ],
            ],
        ]);
    }

    /**
     * Run an import for the given type + file (POST).
     */
    public function import(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|string|in:subject,staff,student,advisor,studentreg',
            'file' => 'required|string|max:80',
        ]);

        $result = match ($data['type']) {
            'staff'      => $this->staffImportService->import($data['file']),
            'student'    => $this->studentImportService->import($data['file']),
            'advisor'    => $this->advisorImportService->import($data['file']),
            'studentreg' => $this->studentRegImportService->import($data['file']),
            default      => $this->importService->import($data['file']),
        };

        return response()->json($result);
    }

    /**
     * ET-002: SEN stakeholder-change email (manual button, SA only).
     * Runs Part 1 (create jobs) -> Part 2 (recipient list) -> Part 3 (send).
     */
    public function sendEmail(EmailSenService $service)
    {
        $summary = $service->run();

        return response()->json([
            'success' => true,
            'message' => 'Email job completed.',
            ...$summary,
        ]);
    }
}
