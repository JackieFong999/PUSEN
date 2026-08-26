<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\StudentNameEncryption;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class SenSearchController extends Controller
{
    /**
     * Staff roles shown as selection boxes in the search criteria.
     * key = form input, value = tblStaff Target_User_Id filter.
     * NOTE: Programme Leader is NOT here — it is derived from each student's
     * PROG_LEADER advisor (tblAdvisor_Student) since tblSEN no longer stores it.
     * NOTE: SEN Officer removed (2026-08-14, Jackie) — column dropped from tblSEN.
     * status is intentionally NOT filtered — SEN data may reference
     * staff that have since been disabled (Jackie's decision).
     */
    private const STAFF_ROLES = [
        'department_admin_staff'                => 'DA',
        'counsellor'                            => 'C',
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

        // Programme Leader dropdown: staff with Target_User_Id='PL' (filters students
        // by their PROG_LEADER advisor — see search())
        $plStaff = $conn->table('tblStaff')
            ->where('Target_User_Id', 'PL')
            ->orderBy('Staff_Id')
            ->get(['Staff_Id', 'Staff_Name']);

        $senTypes = $conn->table('tblSEN_Type')
            ->orderBy('display_order_seq')
            ->orderBy('Id')
            ->get(['Id', 'SEN_Type']);

        return view('admin.sen-search', compact('staff', 'plStaff', 'senTypes'));
    }

    /**
     * Search tblSEN by criteria (AND logic; text fields partial, Student_Id exact).
     * NOTE: no SQL JOINs — student names and staff display names are merged in PHP
     * (collation 1267 landmine between utf8mb4_0900_ai_ci and utf8mb4_unicode_ci).
     */
    public function search(Request $request)
    {
        return response()->json($this->searchRows($request));
    }

    /** shared row builder for the search grid and the Excel export */
    private function searchRows(Request $request): \Illuminate\Support\Collection
    {
        $conn = DB::connection('pusen');

        // --- student-name criteria resolve to student ids first (names live in
        // tblStudent and are ENCRYPTED at rest since 2026-08-26, so the filter
        // decrypts in PHP instead of SQL LIKE)
        $nameEng = trim((string) $request->input('student_name_eng'));
        $nameChn = trim((string) $request->input('student_name_chn'));
        $studentIdsByN = null; // null = no name filter applied
        if ($nameEng !== '' || $nameChn !== '') {
            $studentIdsByN = $conn->table('tblStudent')
                ->get(['Student_Id', 'Student_Name_Eng', 'Student_Name_Chn'])
                ->filter(function ($s) use ($nameEng, $nameChn) {
                    if ($nameEng !== '' && mb_stripos((string) StudentNameEncryption::decrypt($s->Student_Name_Eng), $nameEng) === false) {
                        return false;
                    }
                    if ($nameChn !== '' && mb_stripos((string) StudentNameEncryption::decrypt($s->Student_Name_Chn), $nameChn) === false) {
                        return false;
                    }
                    return true;
                })
                ->pluck('Student_Id')
                ->all();
            if (empty($studentIdsByN)) {
                return collect(); // no student matches the name → no SEN rows
            }
        }

        // --- tblSEN criteria
        $q = $conn->table('tblSEN');

        // Restricted roles (KS etc.): only SEN cases of students this staff
        // currently advises (tblAdvisor_Student, Advisor_Id = login Staff_Id,
        // today within Start_Date..End_Date) AND whose student record is ACTIVE.
        // A student may have more than one advisor - the filter is by student.
        $user = Auth::user();
        if ($user && ! in_array($user->Role_Id, ['SA', 'AU'], true)) {
            $advisedIds = $conn->table('tblAdvisor_Student')
                ->where('Advisor_Id', $user->Staff_Id)
                ->whereDate('Start_Date', '<=', now()->toDateString())
                ->whereDate('End_Date', '>=', now()->toDateString())
                ->pluck('Student_Id')
                ->unique();

            $activeIds = $advisedIds->isNotEmpty()
                ? $conn->table('tblStudent')
                    ->whereIn('Student_Id', $advisedIds->all())
                    ->where('Student_Status', 'ACTIVE')
                    ->pluck('Student_Id')
                : collect();

            // empty set -> no student matches -> no SEN rows (whereIn([]) = 0=1)
            $q->whereIn('Student_Id', $activeIds->all());
        }

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
        // Programme Leader: not stored in tblSEN anymore — resolve to student ids
        // whose PROG_LEADER advisor matches the selected staff
        if ($pl = trim((string) $request->input('programme_leader'))) {
            $plStudentIds = $conn->table('tblAdvisor_Student')
                ->where('Advisor_Type', 'PROG_LEADER')
                ->where('Advisor_Id', $pl)
                ->pluck('Student_Id');
            if ($plStudentIds->isEmpty()) {
                return collect(); // no student has this programme leader -> no SEN rows
            }
            $q->whereIn('Student_Id', $plStudentIds->all());
        }
        if ($senType = trim((string) $request->input('sen_type'))) {
            $q->where('SEN_Type_ID', (int) $senType);
        }
        if ($detail = trim((string) $request->input('sen_detail'))) {
            $q->where('SEN_Detail', 'like', "%{$detail}%");
        }

        $rows = $q->orderBy('updated_at', 'desc')->get();

        // --- student name map (collation-safe merge)
        $studentIds = $rows->pluck('Student_Id')->unique()->filter()->all();
        $students = $studentIds
            ? $conn->table('tblStudent')->whereIn('Student_Id', $studentIds)->get()->keyBy('Student_Id')
            : collect();

        // --- staff display-name map (only for roles still stored on tblSEN)
        $staffIds = collect();
        foreach (self::STAFF_ROLES as $key => $_) {
            $staffIds = $staffIds->merge($rows->pluck($this->colName($key))->filter());
        }
        $staffIds = $staffIds->unique()->all();
        $staffMap = $staffIds
            ? $conn->table('tblStaff')->whereIn('Staff_Id', $staffIds)->get()->keyBy('Staff_Id')
            : collect();

        // --- programme leader per student (derived from PROG_LEADER advisors)
        $plStudentIds = $rows->pluck('Student_Id')->unique()->filter()->all();
        $plByStudent = collect();
        if ($plStudentIds) {
            $plRows = $conn->table('tblAdvisor_Student')
                ->whereIn('Student_Id', $plStudentIds)
                ->where('Advisor_Type', 'PROG_LEADER')
                ->get(['Student_Id', 'Advisor_Id']);
            $plStaffIds = $plRows->pluck('Advisor_Id')->unique()->filter()->all();
            $plStaffMap = $plStaffIds
                ? $conn->table('tblStaff')->whereIn('Staff_Id', $plStaffIds)->get()->keyBy('Staff_Id')
                : collect();
            foreach ($plRows as $p) {
                $s = $plStaffMap->get($p->Advisor_Id);
                $plByStudent[$p->Student_Id] = $s
                    ? ($s->Staff_Display_Name ?: ($s->Staff_Name ?: $p->Advisor_Id))
                    : $p->Advisor_Id;
            }
        }

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

        // --- SEN Type / Temp Support: id -> value maps (no joins, collation-safe) ---
        $typeIds = $rows->pluck('SEN_Type_ID')->unique()->filter()->all();
        $typeMap = $typeIds
            ? $conn->table('tblSEN_Type')->whereIn('Id', $typeIds)->get(['Id', 'SEN_Type'])->pluck('SEN_Type', 'Id')
            : collect();
        $tempIds = $rows->pluck('Temporary_Special_Support_ID')->unique()->filter()->all();
        $tempMap = $tempIds
            ? $conn->table('tblTemporary_Special_Support')->whereIn('Id', $tempIds)->get(['Id', 'Temporary_Special_Support'])->pluck('Temporary_Special_Support', 'Id')
            : collect();

        return $rows->map(function ($r) use ($students, $displayName, $plByStudent, $typeMap, $tempMap) {
            $st = $students->get($r->Student_Id);
            return [
                'sen_id'          => $r->SEN_Id,
                'student_id'      => $r->Student_Id,
                'student_name_eng'=> StudentNameEncryption::decrypt($st->Student_Name_Eng ?? '') ?: '—',
                'student_name_chn'=> StudentNameEncryption::decrypt($st->Student_Name_Chn ?? '') ?: '—',
                'programme_leader'=> $plByStudent->get($r->Student_Id, ''),
                'department_admin_staff'          => $displayName($r->Department_Admin_Staff),
                'counsellor'                      => $displayName($r->Counsellor),
                'undergraduate_studies_support_officer' => $displayName($r->Undergraduate_Studies_Support_Officer),
                'sen_type'         => $r->SEN_Type_ID ? ($typeMap->get($r->SEN_Type_ID) ?? '—') : '—',
                'sen_detail'       => $r->SEN_Detail,
                'special_support_required'        => $r->Special_Support_Required,
                'special_examination_arrangement' => $r->Special_Examination_Arrangement,
                'temporary_special_support'       => $r->Temporary_Special_Support_ID ? ($tempMap->get($r->Temporary_Special_Support_ID) ?? '—') : '—',
                'updated_at'          => $r->updated_at,
            ];
        });
    }

    /**
     * Export the current search result to a real Excel .xlsx file.
     * Filename: SEN_YYYYMMDD_HHMMSS.xlsx (local HK time).
     */
    public function export(Request $request)
    {
        $rows = $this->searchRows($request);

        $columns = [
            'sen_id'          => 'SEN Id',
            'student_id'      => 'Student Id',
            'student_name_eng'=> 'Name (Eng)',
            'student_name_chn'=> 'Name (Chn)',
            'programme_leader'=> 'Programme Leader',
            'department_admin_staff'          => 'Dept Admin',
            'counsellor'                      => 'Counsellor',
            'undergraduate_studies_support_officer' => 'USSO',
            'sen_type'        => 'SEN Type',
            'sen_detail'      => 'SEN Detail',
            'special_support_required'        => 'Support Required',
            'special_examination_arrangement' => 'Exam Arrangement',
            'temporary_special_support'       => 'Temp Support',
            'updated_at'          => 'Update Date',
        ];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('SEN Search');

        // header row
        $col = 1;
        foreach ($columns as $header) {
            $sheet->setCellValue([$col++, 1], $header);
        }
        $lastCol = Coordinate::stringFromColumnIndex(count($columns));
        $headerRange = 'A1:' . $lastCol . '1';
        $sheet->getStyle($headerRange)->getFont()->setBold(true);
        $sheet->getStyle($headerRange)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('D9E2F3');

        // data rows
        $r = 2;
        foreach ($rows as $row) {
            $c = 1;
            foreach (array_keys($columns) as $key) {
                $val = $row[$key] ?? '';
                if ($key === 'updated_at' && $val !== '') {
                    $val = \Carbon\Carbon::parse($val)->format('d/m/Y H:i');
                }
                $sheet->setCellValue([$c++, $r], $val);
            }
            $r++;
        }

        foreach (range('A', $lastCol) as $colLetter) {
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        $filename = 'SEN_' . now('Asia/Hong_Kong')->format('Ymd_His') . '.xlsx';

        $writer = new Xlsx($spreadsheet);
        $tmp = tempnam(sys_get_temp_dir(), 'sen') . '.xlsx';
        $writer->save($tmp);

        // Password protection (2026-08-22): encrypt the workbook with the password
        // stored in tblConfig_Password (PW_Type='EXCEL') — OOXML Agile Encryption
        // (Excel prompts for the password on open). Requires the msoffcrypto-tool
        // python package + scripts/encrypt_xlsx.py inside the phpdev container.
        $excelPassword = DB::connection('pusen')
            ->table('tblConfig_Password')
            ->where('PW_Type', 'EXCEL')
            ->value('Password');

        if (! $excelPassword) {
            @unlink($tmp);
            abort(500, 'Excel export password is not configured (tblConfig_Password PW_Type=EXCEL).');
        }

        $encrypted = tempnam(sys_get_temp_dir(), 'sen') . '.xlsx';
        // scripts/encrypt_xlsx.py ships inside the app (msoffcrypto-tool python
        // package must be installed on the server; see Upgrade Procedure docx).
        $script = base_path('scripts/encrypt_xlsx.py');
        $cmd = sprintf(
            '/usr/bin/python3 %s %s %s %s 2>&1',
            escapeshellarg($script),
            escapeshellarg($tmp),
            escapeshellarg($encrypted),
            escapeshellarg($excelPassword)
        );
        exec($cmd, $output, $exitCode);
        @unlink($tmp);

        if ($exitCode !== 0) {
            @unlink($encrypted);
            abort(500, 'Excel encryption failed: ' . implode(' ', $output));
        }

        return response()->download($encrypted, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /** form input key -> tblSEN column name */
    private function colName(string $key): string
    {
        return ucwords($key, '_');
    }}
