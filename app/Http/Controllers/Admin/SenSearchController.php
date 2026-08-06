<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SenSearchController extends Controller
{
    /**
     * Staff roles shown as selection boxes in the search criteria.
     * key = form input, value = tblStaff Target_User_Id filter.
     * NOTE: status is intentionally NOT filtered — SEN data may reference
     * staff that have since been disabled (Jackie's decision).
     */
    private const STAFF_ROLES = [
        'programme_leader'                      => 'PL',
        'department_admin_staff'                => 'DA',
        'counsellor'                            => 'C',
        'sen_officer'                           => 'SO',
        'undergraduate_studies_support_officer' => 'USSO',
    ];

    /**
     * SEN Search page: criteria bar + AG Grid (dropdown data).
     */
    public function index()
    {
        $conn = DB::connection('pusen');

        $staff = [];
        foreach (self::STAFF_ROLES as $key => $targetUserId) {
            $staff[$key] = $conn->table('tblStaff')
                ->where('Target_User_Id', $targetUserId)
                ->orderBy('Staff_Id')
                ->get(['Staff_Id', 'Staff_Name']);
        }

        $senTypes = $conn->table('tblSEN_Type')
            ->orderBy('SEN_Type')
            ->pluck('SEN_Type');

        return view('admin.sen-search', compact('staff', 'senTypes'));
    }

    /**
     * Search tblSEN by criteria (AND logic; text fields partial, Student_Id exact).
     * NOTE: no SQL JOINs — student names and staff display names are merged in PHP
     * (collation 1267 landmine between utf8mb4_0900_ai_ci and utf8mb4_unicode_ci).
     */
    public function search(Request $request)
    {
        $conn = DB::connection('pusen');

        // --- student-name criteria resolve to student ids first (names live in tblStudent)
        $nameEng = trim((string) $request->input('student_name_eng'));
        $nameChn = trim((string) $request->input('student_name_chn'));
        $studentIdsByN = null; // null = no name filter applied
        if ($nameEng !== '' || $nameChn !== '') {
            $q = $conn->table('tblStudent');
            if ($nameEng !== '') {
                $q->where('Student_Name_Eng', 'like', "%{$nameEng}%");
            }
            if ($nameChn !== '') {
                $q->where('Student_Name_Chn', 'like', "%{$nameChn}%");
            }
            $studentIdsByN = $q->pluck('Student_Id')->all();
            if (empty($studentIdsByN)) {
                return response()->json([]); // no student matches the name → no SEN rows
            }
        }

        // --- tblSEN criteria
        $q = $conn->table('tblSEN');

        if ($sid = trim((string) $request->input('student_id'))) {
            $q->where('Student_Id', $sid); // exact match
        }
        if ($studentIdsByN !== null) {
            $q->whereIn('Student_Id', $studentIdsByN);
        }
        foreach (self::STAFF_ROLES as $key => $targetUserId) {
            $val = trim((string) $request->input($key));
            if ($val !== '') {
                $q->where($this->colName($key), $val);
            }
        }
        if ($senType = trim((string) $request->input('sen_type'))) {
            $q->where('SEN_Type', $senType);
        }
        if ($detail = trim((string) $request->input('sen_detail'))) {
            $q->where('SEN_Detail', 'like', "%{$detail}%");
        }

        $rows = $q->orderBy('SEN_Id')->get();

        // --- student name map (collation-safe merge)
        $studentIds = $rows->pluck('Student_Id')->unique()->filter()->all();
        $students = $studentIds
            ? $conn->table('tblStudent')->whereIn('Student_Id', $studentIds)->get()->keyBy('Student_Id')
            : collect();

        // --- staff display-name map
        $staffIds = collect();
        foreach (self::STAFF_ROLES as $key => $_) {
            $staffIds = $staffIds->merge($rows->pluck($this->colName($key))->filter());
        }
        $staffIds = $staffIds->unique()->all();
        $staffMap = $staffIds
            ? $conn->table('tblStaff')->whereIn('Staff_Id', $staffIds)->get()->keyBy('Staff_Id')
            : collect();

        $displayName = function ($staffId) use ($staffMap) {
            if (! $staffId) {
                return '';
            }
            $s = $staffMap->get($staffId);
            if (! $s) {
                return $staffId; // staff record missing → show the raw id
            }
            return $s->Staff_Display_Name ?: ($s->Staff_Name ?: $staffId);
        };

        return response()->json($rows->map(function ($r) use ($students, $displayName) {
            $st = $students->get($r->Student_Id);
            return [
                'sen_id'          => $r->SEN_Id,
                'student_id'      => $r->Student_Id,
                'student_name_eng'=> $st->Student_Name_Eng ?? '—',
                'student_name_chn'=> $st->Student_Name_Chn ?? '—',
                'programme_leader'=> $displayName($r->Programme_Leader),
                'department_admin_staff'          => $displayName($r->Department_Admin_Staff),
                'counsellor'                      => $displayName($r->Counsellor),
                'sen_officer'                     => $displayName($r->SEN_Officer),
                'undergraduate_studies_support_officer' => $displayName($r->Undergraduate_Studies_Support_Officer),
                'sen_type'         => $r->SEN_Type,
                'sen_detail'       => $r->SEN_Detail,
                'special_support_required'        => $r->Special_Support_Required,
                'special_examination_arrangement' => $r->Special_Examination_Arrangement,
                'temporary_special_support'       => $r->Temporary_Special_Support,
            ];
        }));
    }

    /** form input key -> tblSEN column name (SEN_Officer is the only non-ucwords case) */
    private function colName(string $key): string
    {
        return $key === 'sen_officer' ? 'SEN_Officer' : ucwords($key, '_');
    }
}
