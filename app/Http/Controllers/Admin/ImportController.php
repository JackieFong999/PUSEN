<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\StaffImportService;
use App\Services\StudentImportService;
use App\Services\SubjectImportService;
use Illuminate\Http\Request;

class ImportController extends Controller
{
    public function __construct(
        private SubjectImportService $importService,
        private StaffImportService $staffImportService,
        private StudentImportService $studentImportService,
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

        return view('admin.data-import', [
            'sftpError' => $subject['error'] ?? $staff['error'] ?? $student['error'],
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
            ],
        ]);
    }

    /**
     * Run an import for the given type + file (POST).
     */
    public function import(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|string|in:subject,staff,student',
            'file' => 'required|string|max:80',
        ]);

        $result = match ($data['type']) {
            'staff'   => $this->staffImportService->import($data['file']),
            'student' => $this->studentImportService->import($data['file']),
            default   => $this->importService->import($data['file']),
        };

        return response()->json($result);
    }
}
