<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentRegistrationListController extends Controller
{
    /**
     * Student Registration page: AG Grid (10 rows/page) with a criteria bar
     * (Student Id autocomplete from tblStudent, Subject Code autocomplete
     * from distinct tblSubject.Subject_Code).
     */
    public function index()
    {
        $conn = DB::connection('pusen');

        $rows = $conn->table('tblStudent_Reg')
            ->orderBy('Student_Id')
            ->orderBy('Subject_Code')
            ->get(['Student_Id', 'Subject_Code', 'created_at'])
            ->map(fn ($r) => $this->row($r))
            ->values();

        $options = [
            'studentIds'   => $conn->table('tblStudent')->distinct()->orderBy('Student_Id')->pluck('Student_Id'),
            'subjectCodes' => $conn->table('tblSubject')->distinct()->orderBy('Subject_Code')->pluck('Subject_Code'),
        ];

        return view('admin.student-registration-list', ['rows' => $rows, 'options' => $options]);
    }

    /**
     * Filtered registration rows (JSON) for the AG Grid.
     */
    public function search(Request $request)
    {
        $q = DB::connection('pusen')->table('tblStudent_Reg');

        $sid = trim((string) $request->input('student_id'));
        if ($sid !== '') {
            $q->where('Student_Id', 'like', '%'.$sid.'%');
        }

        $code = trim((string) $request->input('subject_code'));
        if ($code !== '') {
            $q->where('Subject_Code', 'like', '%'.$code.'%');
        }

        $rows = $q->orderBy('Student_Id')
            ->orderBy('Subject_Code')
            ->get(['Student_Id', 'Subject_Code', 'created_at'])
            ->map(fn ($r) => $this->row($r))
            ->values();

        return response()->json($rows);
    }

    private function row($r)
    {
        return [
            'studentId'   => $r->Student_Id,
            'subjectCode' => $r->Subject_Code,
            // app tz is now Asia/Hong_Kong (2026-09-02) - DB stores HK time directly
            'createdAt'   => $r->created_at,
        ];
    }
}
