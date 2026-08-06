<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CreateSenController extends Controller
{
    /**
     * Staff roles that appear as selection boxes in the Create SEN form.
     * key = tblSEN column, value = tblStaff Target_User_Id filter.
     */
    private const STAFF_ROLES = [
        'programme_leader'                     => 'PL',
        'department_admin_staff'               => 'DA',
        'counsellor'                           => 'C',
        'sen_officer'                          => 'SO',
        'undergraduate_studies_support_officer'=> 'USSO',
    ];

    /**
     * Create SEN page: form + dropdown data (staff by role, SEN types, next SEN_Id).
     */
    public function index()
    {
        $conn = DB::connection('pusen');

        $staff = [];
        foreach (self::STAFF_ROLES as $key => $targetUserId) {
            $staff[$key] = $conn->table('tblStaff')
                ->where('Target_User_Id', $targetUserId)
                ->where('status', 0) // enabled only
                ->orderBy('Staff_Id')
                ->get(['Staff_Id', 'Staff_Name']);
        }

        $senTypes = $conn->table('tblSEN_Type')
            ->orderBy('SEN_Type')
            ->pluck('SEN_Type');

        // active students only (Student_Id selection box)
        $students = $conn->table('tblStudent')
            ->where('Student_Status', 'ACTIVE')
            ->orderBy('Student_Id')
            ->get(['Student_Id', 'Student_Name_Eng']);

        $nextSenId = $this->nextSenId();

        return view('admin.create-sen', compact('staff', 'senTypes', 'students', 'nextSenId'));
    }

    /**
     * Display-only data for an entered Student_Id:
     * student info + subject teachers + academic advisors + subjects.
     * NOTE: no SQL JOINs here — tblStudent_Reg/tblAdvisor_Student are
     * utf8mb4_unicode_ci while tblSubject/tblStaff are utf8mb4_0900_ai_ci,
     * so cross-table joins fail with collation error 1267.
     */
    public function studentInfo(Request $request)
    {
        $studentId = trim((string) $request->input('student_id'));
        $conn = DB::connection('pusen');

        $student = $conn->table('tblStudent')->where('Student_Id', $studentId)->first();
        if (! $student) {
            return response()->json(['found' => false]);
        }

        // --- subject teachers: student_reg -> subject codes -> teacher staff ids -> staff names
        $subjectCodes = $conn->table('tblStudent_Reg')
            ->where('Student_Id', $studentId)
            ->pluck('Subject_Code');

        $teacherIds = $subjectCodes->isNotEmpty()
            ? $conn->table('tblSubject')->whereIn('Subject_Code', $subjectCodes->all())->pluck('Teacher_Staff_Id')->unique()
            : collect();

        $teachers = $teacherIds->isNotEmpty()
            ? $conn->table('tblStaff')->whereIn('Staff_Id', $teacherIds->all())
                ->orderBy('Staff_Id')->get(['Staff_Id', 'Staff_Name'])
            : collect();

        $teachers = $teachers->map(fn ($t) => [
            'id'    => $t->Staff_Id,
            'label' => trim($t->Staff_Id . ' — ' . ($t->Staff_Name ?? '')),
        ]);

        // --- academic advisors: advisor ids -> staff names
        $advisorIds = $conn->table('tblAdvisor_Student')
            ->where('Student_No', $studentId)
            ->pluck('Advisor_Id');

        $advisors = $advisorIds->isNotEmpty()
            ? $conn->table('tblStaff')->whereIn('Staff_Id', $advisorIds->all())
                ->orderBy('Staff_Id')->get(['Staff_Id', 'Staff_Name'])
            : collect();

        $advisors = $advisors->map(fn ($a) => [
            'id'    => $a->Staff_Id,
            'label' => trim($a->Staff_Id . ' — ' . ($a->Staff_Name ?? '')),
        ]);

        // --- subjects
        $subjects = $conn->table('tblStudent_Reg')
            ->where('Student_Id', $studentId)
            ->orderBy('Subject_Code')
            ->pluck('Subject_Code');

        return response()->json([
            'found' => true,
            'student' => [
                'student_name_eng' => $student->Student_Name_Eng,
                'student_name_chn' => $student->Student_Name_Chn,
                'faculty'          => $student->Faculty,
                'department'       => $student->Department,
                'prog_sub_code'    => $student->Prog_Sub_Code,
                'prog_title'       => $student->Prog_Title,
                'fund_type_code'   => $student->Fund_Type_Code,
                'student_status'   => $student->Student_Status,
            ],
            'subject_teachers' => $teachers,
            'academic_advisors' => $advisors,
            'subjects' => $subjects,
        ]);
    }

    /**
     * Save a new SEN case.
     */
    public function save(Request $request)
    {
        $validated = $request->validate([
            'student_id' => 'required|string|max:12',
        ]);
        $conn = DB::connection('pusen');

        $studentId = trim($validated['student_id']);
        if (! $conn->table('tblStudent')->where('Student_Id', $studentId)->exists()) {
            return response()->json(['success' => false, 'message' => 'Student not found.'], 422);
        }

        // staff fields: optional, but if provided must be an enabled staff of the right role
        $data = [];
        foreach (self::STAFF_ROLES as $key => $targetUserId) {
            $val = trim((string) $request->input($key));
            if ($val === '') {
                $data[$this->colName($key)] = null;
                continue;
            }
            $exists = $conn->table('tblStaff')
                ->where('Staff_Id', $val)
                ->where('Target_User_Id', $targetUserId)
                ->where('status', 0)
                ->exists();
            if (! $exists) {
                return response()->json(['success' => false, 'message' => "Invalid staff selected for $key."], 422);
            }
            $data[$this->colName($key)] = $val;
        }

        // SEN_Type: optional, must exist in tblSEN_Type
        $senType = trim((string) $request->input('sen_type'));
        if ($senType !== '' && ! $conn->table('tblSEN_Type')->where('SEN_Type', $senType)->exists()) {
            return response()->json(['success' => false, 'message' => 'Invalid SEN Type.'], 422);
        }
        $data['SEN_Type'] = $senType !== '' ? $senType : null;

        $data['SEN_Id'] = $this->nextSenId();
        $data['Student_Id'] = $studentId;
        $data['SEN_Detail'] = $this->nullable($request->input('sen_detail'));
        $data['Special_Support_Required'] = $this->nullable($request->input('special_support_required'));
        $data['Special_Examination_Arrangement'] = $this->nullable($request->input('special_examination_arrangement'));
        $data['Temporary_Special_Support'] = $this->nullable($request->input('temporary_special_support'));

        $data['created_at'] = now();
        $data['updated_at'] = now();
        $data['created_by'] = 'system01';
        $data['updated_by'] = 'system01';
        $data['updated_ip'] = $request->ip();

        $conn->table('tblSEN')->insert($data);

        // move staged docs to the final folder + insert tblSEN_Doc rows
        $this->finalizeDocs($data['SEN_Id'], (string) $request->ip());

        return response()->json(['success' => true, 'sen_id' => $data['SEN_Id']]);
    }

    /** form input key -> tblSEN column name (e.g. programme_leader -> Programme_Leader) */
    private function colName(string $key): string
    {
        return ucwords($key, '_');
    }

    private function nullable($value): ?string
    {
        $v = trim((string) $value);
        return $v === '' ? null : $v;
    }

    /** next SEN_Id = max numeric part + 1, zero-padded to 3 digits (e.g. SEN-013) */
    private function nextSenId(): string
    {
        $max = DB::connection('pusen')->table('tblSEN')->max('SEN_Id');
        $num = 0;
        if ($max && preg_match('/SEN-(\d+)/', (string) $max, $m)) {
            $num = (int) $m[1];
        }
        return 'SEN-' . str_pad((string) ($num + 1), 3, '0', STR_PAD_LEFT);
    }

    /* ================= document upload (staging) ================= */

    private const MAX_DOCS = 20;
    private const MAX_DOC_SIZE_KB = 1024; // 1 MB (testing); production will be 10 MB

    private function stagingDir(): string
    {
        return storage_path('app/public/sen_docs/staging');
    }

    private function finalDocDir(): string
    {
        return storage_path('app/public/sen_docs');
    }

    /** sorted list of staged filenames for a SEN case (e.g. [SEN-013_01_a.pdf, ...]) */
    private function stagedList(string $senId): array
    {
        $files = glob($this->stagingDir() . '/' . $senId . '_*') ?: [];
        $names = array_map('basename', $files);
        sort($names);
        return $names;
    }

    /** next 2-digit sequence for the staged files of a SEN case */
    private function nextDocSeq(string $senId): int
    {
        $max = 0;
        foreach ($this->stagedList($senId) as $name) {
            if (preg_match('/_(\d+)_/', $name, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }
        return $max + 1;
    }

    /**
     * Stage an uploaded PDF (PDF only, <= 1 MB, max 20 per case).
     * Filename: {SEN_Id}_{2-digit seq}_{original filename}
     */
    public function upload(Request $request)
    {
        $senId = trim((string) $request->input('sen_id'));
        if ($senId === '' || ! preg_match('/^SEN-\d+$/', $senId)) {
            return response()->json(['success' => false, 'message' => 'Missing or invalid SEN_Id.'], 422);
        }

        $validated = $request->validate([
            'file' => 'required|file|mimes:pdf|max:' . self::MAX_DOC_SIZE_KB,
        ]);

        if (count($this->stagedList($senId)) >= self::MAX_DOCS) {
            return response()->json(['success' => false, 'message' => 'Maximum ' . self::MAX_DOCS . ' documents allowed.'], 422);
        }

        $dir = $this->stagingDir();
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $seq = $this->nextDocSeq($senId);
        $original = preg_replace('/[^A-Za-z0-9._-]/', '_', $validated['file']->getClientOriginalName());
        $original = $original === '' ? 'document.pdf' : $original;
        $filename = $senId . '_' . str_pad((string) $seq, 2, '0', STR_PAD_LEFT) . '_' . $original;

        $validated['file']->move($dir, $filename);

        return response()->json([
            'success' => true,
            'filename' => $filename,
            'files' => $this->stagedList($senId),
        ]);
    }

    /** Remove one staged file (staging copy only). */
    public function removeDoc(Request $request)
    {
        $senId = trim((string) $request->input('sen_id'));
        $filename = basename((string) $request->input('filename')); // strip any path traversal

        // only files belonging to this SEN case may be removed
        if (! str_starts_with($filename, $senId . '_')) {
            return response()->json(['success' => false, 'message' => 'Invalid filename.'], 422);
        }

        $path = $this->stagingDir() . '/' . $filename;
        if (is_file($path)) {
            unlink($path);
        }

        return response()->json(['success' => true, 'files' => $this->stagedList($senId)]);
    }

    /** Delete all staged files for a SEN case (used on Cancel / new case). */
    public function clearStaged(Request $request)
    {
        $senId = trim((string) $request->input('sen_id'));
        foreach ($this->stagedList($senId) as $name) {
            @unlink($this->stagingDir() . '/' . $name);
        }
        return response()->json(['success' => true, 'files' => []]);
    }

    /** move staged files to the final folder + insert tblSEN_Doc rows */
    private function finalizeDocs(string $senId, string $ip): void
    {
        $staging = $this->stagingDir();
        $final = $this->finalDocDir();
        if (! is_dir($final)) {
            mkdir($final, 0775, true);
        }

        $rows = [];
        foreach ($this->stagedList($senId) as $name) {
            $from = $staging . '/' . $name;
            if (! is_file($from)) {
                continue;
            }
            rename($from, $final . '/' . $name);

            $seq = 0;
            if (preg_match('/_(\d+)_/', $name, $m)) {
                $seq = (int) $m[1];
            }
            $rows[] = [
                'SEN_Id'       => $senId,
                'Doc_Seq'      => $seq,
                'Doc_Filename' => $name,
                'created_at'   => now(),
                'updated_at'   => now(),
                'created_by'   => 'system01',
                'updated_by'   => 'system01',
                'updated_ip'   => $ip,
            ];
        }

        if ($rows) {
            DB::connection('pusen')->table('tblSEN_Doc')->insert($rows);
        }
    }
}
