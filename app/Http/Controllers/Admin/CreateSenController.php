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
     * When ?sen_id= is given, renders in EDIT mode (loads the SEN record + its docs).
     */
    public function index(Request $request)
    {
        $conn = DB::connection('pusen');

        $isEdit  = false;
        $editSen = null;
        $editDocs = collect();

        $senId = trim((string) $request->input('sen_id'));
        if ($senId !== '') {
            $editSen = $conn->table('tblSEN')->where('SEN_Id', $senId)->first();
            if (! $editSen) {
                return redirect()->route('admin.sen-search');
            }
            $isEdit = true;
            $editDocs = $conn->table('tblSEN_Doc')
                ->where('SEN_Id', $senId)
                ->orderBy('Doc_Seq')
                ->get();
        }

        $staff = [];
        foreach (self::STAFF_ROLES as $key => $targetUserId) {
            $q = $conn->table('tblStaff')->where('Target_User_Id', $targetUserId);
            if (! $isEdit) {
                $q->where('status', 0); // create mode: enabled only
            }
            // edit mode: ignore status — existing SEN data may reference disabled staff
            $staff[$key] = $q->orderBy('Staff_Id')->get(['Staff_Id', 'Staff_Name']);

            // ensure the record's current value is always present in the dropdown
            if ($isEdit && $editSen->{$this->colName($key)}) {
                $cur = $editSen->{$this->colName($key)};
                if (! $staff[$key]->contains('Staff_Id', $cur)) {
                    $s = $conn->table('tblStaff')->where('Staff_Id', $cur)->first(['Staff_Id', 'Staff_Name']);
                    if ($s) {
                        $staff[$key]->push($s);
                    }
                }
            }
        }

        $senTypes = $conn->table('tblSEN_Type')
            ->orderBy('SEN_Type')
            ->pluck('SEN_Type');

        // active students only (Student_Id selection box)
        $students = $conn->table('tblStudent')
            ->where('Student_Status', 'ACTIVE')
            ->orderBy('Student_Id')
            ->get(['Student_Id', 'Student_Name_Eng']);

        // edit mode: ensure the record's student is in the dropdown even if not ACTIVE
        if ($isEdit && $editSen->Student_Id && ! $students->contains('Student_Id', $editSen->Student_Id)) {
            $s = $conn->table('tblStudent')->where('Student_Id', $editSen->Student_Id)->first(['Student_Id', 'Student_Name_Eng']);
            if ($s) {
                $students->push($s);
            }
        }

        $nextSenId = $isEdit ? $editSen->SEN_Id : $this->nextSenId();

        return view('admin.create-sen', compact('staff', 'senTypes', 'students', 'nextSenId', 'isEdit', 'editSen', 'editDocs'));
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
     * Save a SEN case. Create mode: INSERT new row + finalize staged docs.
     * Edit mode (sen_id given): UPDATE row, finalize staged docs, delete removed docs.
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

        // edit mode? (sen_id present in the payload)
        $senId = trim((string) $request->input('sen_id'));
        $isEdit = $senId !== '';
        if ($isEdit && ! $conn->table('tblSEN')->where('SEN_Id', $senId)->exists()) {
            return response()->json(['success' => false, 'message' => 'SEN case not found.'], 404);
        }

        // staff fields: optional; create mode requires an ENABLED staff of the right role,
        // edit mode ignores status (existing values may reference disabled staff)
        $data = [];
        foreach (self::STAFF_ROLES as $key => $targetUserId) {
            $val = trim((string) $request->input($key));
            if ($val === '') {
                $data[$this->colName($key)] = null;
                continue;
            }
            $q = $conn->table('tblStaff')
                ->where('Staff_Id', $val)
                ->where('Target_User_Id', $targetUserId);
            if (! $isEdit) {
                $q->where('status', 0);
            }
            if (! $q->exists()) {
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

        $data['Student_Id'] = $studentId;
        $data['SEN_Detail'] = $this->nullable($request->input('sen_detail'));
        $data['Special_Support_Required'] = $this->nullable($request->input('special_support_required'));
        $data['Special_Examination_Arrangement'] = $this->nullable($request->input('special_examination_arrangement'));
        $data['Temporary_Special_Support'] = $this->nullable($request->input('temporary_special_support'));

        $data['updated_at'] = now();
        $data['updated_by'] = 'system01';
        $data['updated_ip'] = $request->ip();

        if ($isEdit) {
            $conn->table('tblSEN')->where('SEN_Id', $senId)->update($data);
            $finalSenId = $senId;
        } else {
            $data['SEN_Id'] = $this->nextSenId();
            $data['created_at'] = now();
            $data['created_by'] = 'system01';
            $conn->table('tblSEN')->insert($data);
            $finalSenId = $data['SEN_Id'];
        }

        // move staged docs to the final folder + insert tblSEN_Doc rows
        $this->finalizeDocs($finalSenId, (string) $request->ip());

        // edit mode: delete docs the user removed (file + tblSEN_Doc row)
        $removed = $request->input('removed_docs', []);
        if ($isEdit && is_array($removed)) {
            foreach ($removed as $name) {
                $name = basename((string) $name);
                if (! str_starts_with($name, $finalSenId . '_')) {
                    continue;
                }
                $conn->table('tblSEN_Doc')
                    ->where('SEN_Id', $finalSenId)
                    ->where('Doc_Filename', $name)
                    ->delete();
                @unlink($this->finalDocDir() . '/' . $name);
            }
        }

        return response()->json(['success' => true, 'sen_id' => $finalSenId]);
    }

    /** Serve a SEN document for in-browser PDF preview (checks staging, then final). */
    public function previewDoc(string $filename)
    {
        $filename = basename($filename);
        $candidates = [
            $this->finalDocDir() . '/' . $filename,
            $this->stagingDir() . '/' . $filename,
        ];
        foreach ($candidates as $path) {
            if (is_file($path)) {
                return response()->file($path, ['Content-Type' => 'application/pdf']);
            }
        }
        abort(404, 'Document not found');
    }

    /** form input key -> tblSEN column name (SEN_Officer is the only non-ucwords case) */
    private function colName(string $key): string
    {
        return $key === 'sen_officer' ? 'SEN_Officer' : ucwords($key, '_');
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

    /** next 2-digit sequence for a SEN case = max(saved Doc_Seq, staged seq) + 1 */
    private function nextDocSeq(string $senId): int
    {
        $max = 0;
        foreach ($this->stagedList($senId) as $name) {
            if (preg_match('/_(\d+)_/', $name, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }
        $saved = DB::connection('pusen')->table('tblSEN_Doc')
            ->where('SEN_Id', $senId)
            ->max('Doc_Seq');
        $max = max($max, (int) $saved);
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

        if (count($this->stagedList($senId)) + DB::connection('pusen')->table('tblSEN_Doc')->where('SEN_Id', $senId)->count() >= self::MAX_DOCS) {
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
