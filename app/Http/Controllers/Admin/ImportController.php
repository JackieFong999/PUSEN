<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\StaffImportService;
use App\Services\SubjectImportService;
use Illuminate\Http\Request;

class ImportController extends Controller
{
    public function __construct(
        private SubjectImportService $importService,
        private StaffImportService $staffImportService,
    ) {
    }

    /**
     * Data Import screen: shows the import table (Subject + Staff active).
     */
    public function index()
    {
        $subject = $this->importService->latestFile();
        $staff   = $this->staffImportService->latestFile();

        return view('admin.data-import', [
            'sftpError' => $subject['error'] ?? $staff['error'],
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
            ],
        ]);
    }

    /**
     * Run an import for the given type + file (POST).
     */
    public function import(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|string|in:subject,staff',
            'file' => 'required|string|max:80',
        ]);

        $result = $data['type'] === 'staff'
            ? $this->staffImportService->import($data['file'])
            : $this->importService->import($data['file']);

        return response()->json($result);
    }
}
