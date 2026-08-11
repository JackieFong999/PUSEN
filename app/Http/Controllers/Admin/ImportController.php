<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SubjectImportService;
use Illuminate\Http\Request;

class ImportController extends Controller
{
    public function __construct(private SubjectImportService $importService)
    {
    }

    /**
     * Data Import screen: shows the import table (Subject active first).
     */
    public function index()
    {
        $latest = $this->importService->latestFile();

        return view('admin.data-import', [
            'subjectFile'      => $latest['filename'],
            'subjectFileReady' => $latest['exists'],
            'sftpError'        => $latest['error'],
            'lastImported'     => $this->importService->lastImportedFile(),
        ]);
    }

    /**
     * Run the subject import for the given file (POST).
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|string|max:60',
        ]);

        $result = $this->importService->import($request->input('file'));

        return response()->json($result);
    }
}
