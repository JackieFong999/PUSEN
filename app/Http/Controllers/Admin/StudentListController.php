<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\StudentNameEncryption;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentListController extends Controller
{
    /**
     * Student List page: criteria bar + AG Grid (dropdown data).
     */
    public function index()
    {
        $faculties = DB::connection('pusen')
            ->table('tblStudent')
            ->distinct()
            ->orderBy('Faculty')
            ->pluck('Faculty');

        $departments = DB::connection('pusen')
            ->table('tblStudent')
            ->distinct()
            ->orderBy('Department')
            ->pluck('Department');

        $fundTypes = DB::connection('pusen')
            ->table('tblFund_Type')
            ->orderBy('Fund_Type_Code')
            ->get();

        $studentStatuses = DB::connection('pusen')
            ->table('tblStudent_Status')
            ->orderBy('Student_Status')
            ->pluck('Student_Status');

        return view('admin.student-list', compact('faculties', 'departments', 'fundTypes', 'studentStatuses'));
    }

    /**
     * Search tblStudent by criteria (AND logic; text fields = partial match).
     * Empty criteria returns all students.
     */
    public function search(Request $request)
    {
        // Fund type descriptions (code => desc). Fetched separately on purpose:
        // tblStudent is utf8mb4_0900_ai_ci while tblFund_Type is utf8mb4_unicode_ci,
        // so a SQL JOIN on Fund_Type_Code fails with collation error 1267.
        $fundDescs = DB::connection('pusen')
            ->table('tblFund_Type')
            ->pluck('Fund_Status', 'Fund_Type_Code');

        $q = DB::connection('pusen')->table('tblStudent as s');

        // name filters are applied in PHP AFTER the SQL fetch (names are
        // encrypted at rest since 2026-08-26 — SQL LIKE can't match ciphertext)
        $nameEng = trim((string) $request->input('student_name_eng'));
        $nameChn = trim((string) $request->input('student_name_chn'));
        if ($faculty = trim((string) $request->input('faculty'))) {
            $q->where('s.Faculty', $faculty);
        }
        if ($department = trim((string) $request->input('department'))) {
            $q->where('s.Department', $department);
        }
        if ($fundType = trim((string) $request->input('fund_type_code'))) {
            $q->where('s.Fund_Type_Code', $fundType);
        }
        if ($status = trim((string) $request->input('student_status'))) {
            $q->where('s.Student_Status', $status);
        }

        $rows = $q->orderBy('s.Student_Id')
            ->get([
                's.Id', 's.Student_Id', 's.Student_Name_Eng', 's.Student_Name_Chn',
                's.Faculty', 's.Department', 's.Prog_Sub_Code', 's.Prog_Title',
                's.Fund_Type_Code', 's.Student_Status',
            ]);

        // decrypt-then-filter for the name criteria (case-insensitive substring)
        if ($nameEng !== '' || $nameChn !== '') {
            $rows = $rows->filter(function ($r) use ($nameEng, $nameChn) {
                if ($nameEng !== '' && mb_stripos((string) StudentNameEncryption::decrypt($r->Student_Name_Eng), $nameEng) === false) {
                    return false;
                }
                if ($nameChn !== '' && mb_stripos((string) StudentNameEncryption::decrypt($r->Student_Name_Chn), $nameChn) === false) {
                    return false;
                }
                return true;
            })->values();
        }

        return response()->json($rows->map(fn ($r) => [
            'id'               => $r->Id,
            'student_id'       => $r->Student_Id,
            'student_name_eng' => StudentNameEncryption::decrypt($r->Student_Name_Eng),
            'student_name_chn' => StudentNameEncryption::decrypt($r->Student_Name_Chn),
            'faculty'          => $r->Faculty,
            'department'       => $r->Department,
            'prog_sub_code'    => $r->Prog_Sub_Code,
            'prog_title'       => $r->Prog_Title,
            'fund_type_code'   => $r->Fund_Type_Code,
            'fund_type_desc'   => $fundDescs[$r->Fund_Type_Code] ?? null,
            'student_status'   => $r->Student_Status,
        ]));
    }
}
