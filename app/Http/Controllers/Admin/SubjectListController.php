<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubjectListController extends Controller
{
    /**
     * Subject/Lecture list page: AG Grid (10 rows/page) with a criteria bar
     * (Academic Year, Subject Code autocomplete, Teacher Staff ID, Subject Type).
     */
    public function index()
    {
        $conn = DB::connection('pusen');

        $rows = $conn->table('tblSubject')
            ->orderBy('Academic_Year')
            ->orderBy('Semester')
            ->orderBy('Subject_Code')
            ->get(['Academic_Year', 'Semester', 'Subject_Code', 'Teacher_Staff_Id', 'Subject_Type'])
            ->map(fn ($r) => $this->row($r))
            ->values();

        $options = [
            'academicYears' => $conn->table('tblSubject')->distinct()->orderBy('Academic_Year')->pluck('Academic_Year'),
            'subjectTypes'  => $conn->table('tblSubject_Type')->orderBy('Subject_Type')->pluck('Subject_Type'),
            'subjectCodes'  => $conn->table('tblSubject')->distinct()->orderBy('Subject_Code')->pluck('Subject_Code'),
        ];

        return view('admin.subject-list', ['rows' => $rows, 'options' => $options]);
    }

    /**
     * Filtered subject rows (JSON) for the AG Grid.
     */
    public function search(Request $request)
    {
        $q = DB::connection('pusen')->table('tblSubject');

        if ($request->filled('academic_year')) {
            $q->where('Academic_Year', (int) $request->input('academic_year'));
        }

        $code = trim((string) $request->input('subject_code'));
        if ($code !== '') {
            $q->where('Subject_Code', 'like', '%'.$code.'%');
        }

        $teacher = trim((string) $request->input('teacher_staff_id'));
        if ($teacher !== '') {
            $q->where('Teacher_Staff_Id', 'like', '%'.$teacher.'%');
        }

        if ($request->filled('subject_type')) {
            $q->where('Subject_Type', $request->input('subject_type'));
        }

        $rows = $q->orderBy('Academic_Year')
            ->orderBy('Semester')
            ->orderBy('Subject_Code')
            ->get(['Academic_Year', 'Semester', 'Subject_Code', 'Teacher_Staff_Id', 'Subject_Type'])
            ->map(fn ($r) => $this->row($r))
            ->values();

        return response()->json($rows);
    }

    private function row($r)
    {
        return [
            'academicYear'  => $r->Academic_Year,
            'semester'      => $r->Semester,
            'subjectCode'   => $r->Subject_Code,
            'teacherStaffId'=> $r->Teacher_Staff_Id,
            'subjectType'   => $r->Subject_Type,
        ];
    }
}
