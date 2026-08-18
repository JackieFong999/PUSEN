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
     * NOTE: Programme Leader is NOT a selection box — it is derived from the
     * selected student's PROG_LEADER advisor (tblAdvisor_Student).
     * NOTE: SEN Officer removed (2026-08-14, Jackie) — the field is gone from
     * the form; the SEN_Officer column stays in tblSEN for SEN Search.
     * The three remaining selects list ALL enabled staff (status=0) with
     * NO Target_User_Id filter (Jackie 2026-08-14).
     */
    private const STAFF_ROLES = [
        'department_admin_staff'               => 'DA',
        'counsellor'                           => 'C',
        'undergraduate_studies_support_officer'=> 'USSO',
    ];

    /**
     * Create SEN page: form + dropdown data (staff by role, SEN types, next SEN_Id).
     * When ?sen_id= is given, renders in EDIT mode (loads the SEN record + its docs).
     * When ?sen_id= + &mode=view is given, renders in VIEW mode (read-only, docs view-only).
     */
    public function index(Request $request)
    {
        $conn = DB::connection('pusen');

        $isEdit  = false;
        $isView  = false;
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

        // view mode only makes sense on an existing case
        $isView = $request->input('mode') === 'view' && $isEdit;

        $staff = [];
        foreach (self::STAFF_ROLES as $key => $targetUserId) {
            // All enabled staff (status=0), NO Target_User_Id filter (Jackie 2026-08-14)
            $q = $conn->table('tblStaff');
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

        // Temporary Special Support options (lookup table)
        $tempSupports = $conn->table('tblTemporary_Special_Support')
            ->orderBy('Temporary_Special_Support')
            ->pluck('Temporary_Special_Support');

        // edit mode: ensure the record's current value is in the dropdown even if
        // it is not in the lookup table (legacy free-text values)
        if ($isEdit && $editSen->Temporary_Special_Support && ! $tempSupports->contains($editSen->Temporary_Special_Support)) {
            $tempSupports->push($editSen->Temporary_Special_Support);
        }

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

        // Programme Leader is derived from the student's PROG_LEADER advisors (edit mode display)
        $editPlLabels = [];
        if ($isEdit && $editSen->Student_Id) {
            $plRows = $conn->table('tblAdvisor_Student')
                ->where('Student_Id', $editSen->Student_Id)
                ->where('Advisor_Type', 'PROG_LEADER')
                ->whereDate('Start_Date', '<=', now()->toDateString())
                ->whereDate('End_Date', '>=', now()->toDateString())
                ->get(['Advisor_Id']);
            $plStaffIds = $plRows->pluck('Advisor_Id')->unique()->filter()->all();
            $plStaffMap = $plStaffIds
                ? $conn->table('tblStaff')->whereIn('Staff_Id', $plStaffIds)->get()->keyBy('Staff_Id')
                : collect();
            foreach ($plRows as $p) {
                $s = $plStaffMap->get($p->Advisor_Id);
                $editPlLabels[] = trim($p->Advisor_Id . ' — ' . ($s->Staff_Name ?? ''));
            }
        }

        $nextSenId = $isEdit ? $editSen->SEN_Id : $this->nextSenId();

        return view('admin.create-sen', compact('staff', 'senTypes', 'tempSupports', 'students', 'nextSenId', 'isEdit', 'isView', 'editSen', 'editDocs', 'editPlLabels'));
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

        // --- academic advisors: PRIMARY advisors of this student whose
        // date range (Start_Date .. End_Date) covers today
        $advisorRows = $conn->table('tblAdvisor_Student')
            ->where('Student_Id', $studentId)
            ->where('Advisor_Type', 'PRIMARY')
            ->whereDate('Start_Date', '<=', now()->toDateString())
            ->whereDate('End_Date', '>=', now()->toDateString())
            ->get(['Advisor_Id']);

        $advisorIds = $advisorRows->pluck('Advisor_Id')->unique()->filter();

        $advisors = $advisorIds->isNotEmpty()
            ? $conn->table('tblStaff')->whereIn('Staff_Id', $advisorIds->all())
                ->orderBy('Staff_Id')->get(['Staff_Id', 'Staff_Name'])
            : collect();

        $advisors = $advisors->map(fn ($a) => [
            'id'    => $a->Staff_Id,
            'label' => trim($a->Staff_Id . ' — ' . ($a->Staff_Name ?? '')),
        ]);

        // --- programme leaders: ALL PROG_LEADER advisors of this student whose
        // date range (Start_Date .. End_Date) covers today
        $plRows = $conn->table('tblAdvisor_Student')
            ->where('Student_Id', $studentId)
            ->where('Advisor_Type', 'PROG_LEADER')
            ->whereDate('Start_Date', '<=', now()->toDateString())
            ->whereDate('End_Date', '>=', now()->toDateString())
            ->get(['Advisor_Id']);
        $programmeLeaders = [];
        if ($plRows->isNotEmpty()) {
            $plStaffIds = $plRows->pluck('Advisor_Id')->unique()->filter()->all();
            $plStaffMap = $plStaffIds
                ? $conn->table('tblStaff')->whereIn('Staff_Id', $plStaffIds)->get()->keyBy('Staff_Id')
                : collect();
            foreach ($plRows as $p) {
                $s = $plStaffMap->get($p->Advisor_Id);
                $programmeLeaders[] = [
                    'id'    => $p->Advisor_Id,
                    'label' => trim($p->Advisor_Id . ' — ' . ($s->Staff_Name ?? '')),
                ];
            }
        }

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
            'programme_leaders' => $programmeLeaders,
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

        // staff fields: optional; create mode requires an ENABLED staff (status=0),
        // edit mode ignores status (existing values may reference disabled staff).
        // No Target_User_Id check (Jackie 2026-08-14).
        $data = [];
        foreach (self::STAFF_ROLES as $key => $targetUserId) {
            $val = trim((string) $request->input($key));
            if ($val === '') {
                $data[$this->colName($key)] = null;
                continue;
            }
            $q = $conn->table('tblStaff')->where('Staff_Id', $val);
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

    /** Serve a SEN document for preview / download (checks staging, then final). */
    public function previewDoc(Request $request, string $filename)
    {
        $filename = basename($filename);
        $candidates = [
            $this->finalDocDir() . '/' . $filename,
            $this->stagingDir() . '/' . $filename,
        ];
        foreach ($candidates as $path) {
            if (is_file($path)) {
                // ?dl=1 forces a download; otherwise preview in-browser.
                // Content-Disposition carries the ORIGINAL filename (RFC 5987 for non-ASCII).
                $disposition = $request->boolean('dl') ? 'attachment' : 'inline';
                $original = $this->originalNameFor($filename);
                $safe = str_replace(['"', '\\', "\r", "\n"], '_', $original);
                return response()->file($path, [
                    'Content-Disposition' => $disposition . '; filename="' . $safe . '"; filename*=UTF-8\'\'' . rawurlencode($original),
                ]);
            }
        }
        abort(404, 'Document not found');
    }

    /** Best-known original filename for a stored doc (staged meta -> DB -> stripped fallback). */
    private function originalNameFor(string $filename): string
    {
        if (preg_match('/^(SEN-\d+)_\d+_/', $filename, $m)) {
            $senId = $m[1];
            $meta = $this->docMeta($senId);
            if (isset($meta[$filename]) && $meta[$filename] !== '') {
                return $meta[$filename];
            }
            $row = DB::connection('pusen')->table('tblSEN_Doc')
                ->where('SEN_Id', $senId)
                ->where('Doc_Filename', $filename)
                ->first(['Doc_Filename_Original']);
            if ($row && $row->Doc_Filename_Original) {
                return $row->Doc_Filename_Original;
            }
            return preg_replace('/^' . preg_quote($senId, '/') . '_\d+_/', '', $filename);
        }
        return $filename;
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
    private const MAX_DOC_SIZE_KB = 10240; // 10 MB per file

    /** file extensions never allowed for upload (executables / script / active content) */
    private const BLOCKED_EXTENSIONS = [
        // Windows executables / installers
        'exe', 'msi', 'bat', 'cmd', 'com', 'scr', 'pif',
        // scripts (browser / shell)
        'js', 'jse', 'vbs', 'vbe', 'wsf', 'wsh', 'ps1', 'psm1', 'psd1',
        'sh', 'bash', 'php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phar',
        'pl', 'py', 'pyc', 'rb', 'jar', 'class',
        // misc active content
        'app', 'dmg', 'reg', 'lnk', 'msc', 'gadget', 'cpl',
        'asp', 'aspx', 'jsp', 'jspx', 'htaccess',
        // served inline by previewDoc -> would execute in the app origin
        'html', 'htm', 'shtml', 'svg',
    ];

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

    /** sidecar meta file: staged filename -> true original client filename */
    private function docMetaPath(string $senId): string
    {
        return $this->stagingDir() . '/' . $senId . '.meta.json';
    }

    private function docMeta(string $senId): array
    {
        $path = $this->docMetaPath($senId);
        if (! is_file($path)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($path), true);
        return is_array($data) ? $data : [];
    }

    private function saveDocMeta(string $senId, array $meta): void
    {
        $dir = $this->stagingDir();
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($this->docMetaPath($senId), json_encode($meta));
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
     * Stage an uploaded document (any type except executables, <= 1 MB, max 20 per case).
     * Filename: {SEN_Id}_{2-digit seq}_{original filename}
     */
    public function upload(Request $request)
    {
        $senId = trim((string) $request->input('sen_id'));
        if ($senId === '' || ! preg_match('/^SEN-\d+$/', $senId)) {
            return response()->json(['success' => false, 'message' => 'Missing or invalid SEN_Id.'], 422);
        }

        $validated = $request->validate([
            'file' => [
                'required', 'file', 'max:' . self::MAX_DOC_SIZE_KB,
                function ($attribute, $value, $fail) {
                    $ext = strtolower($value->getClientOriginalExtension());
                    if (in_array($ext, self::BLOCKED_EXTENSIONS, true)) {
                        $fail('Executable / active-content files (.'.$ext.') are not allowed.');
                    }
                },
            ],
        ]);

        if (count($this->stagedList($senId)) + DB::connection('pusen')->table('tblSEN_Doc')->where('SEN_Id', $senId)->count() >= self::MAX_DOCS) {
            return response()->json(['success' => false, 'message' => 'Maximum ' . self::MAX_DOCS . ' documents allowed.'], 422);
        }

        $dir = $this->stagingDir();
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $seq = $this->nextDocSeq($senId);
        $rawOriginal = $validated['file']->getClientOriginalName();
        $original = preg_replace('/[^A-Za-z0-9._-]/', '_', $rawOriginal);
        $original = $original === '' ? 'document.pdf' : $original;
        $filename = $senId . '_' . str_pad((string) $seq, 2, '0', STR_PAD_LEFT) . '_' . $original;

        $validated['file']->move($dir, $filename);

        // remember the true original filename -> tblSEN_Doc.Doc_Filename_Original on save
        $meta = $this->docMeta($senId);
        $meta[$filename] = $rawOriginal;
        $this->saveDocMeta($senId, $meta);

        return response()->json([
            'success' => true,
            'filename' => $filename,
            'original' => $rawOriginal,
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
        $meta = $this->docMeta($senId);
        if (array_key_exists($filename, $meta)) {
            unset($meta[$filename]);
            $this->saveDocMeta($senId, $meta);
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
        @unlink($this->docMetaPath($senId));
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

        $meta = $this->docMeta($senId);
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
            // true original filename from the upload meta (fallback: strip the SEN_ prefix)
            $original = $meta[$name] ?? preg_replace('/^' . preg_quote($senId, '/') . '_\d+_/', '', $name);
            $original = mb_substr((string) $original, 0, 60);

            $rows[] = [
                'SEN_Id'                => $senId,
                'Doc_Seq'               => $seq,
                'Doc_Filename'          => $name,
                'Doc_Filename_Original' => $original === '' ? null : $original,
                'created_at'            => now(),
                'updated_at'            => now(),
                'created_by'            => 'system01',
                'updated_by'            => 'system01',
                'updated_ip'            => $ip,
            ];
        }

        if ($rows) {
            DB::connection('pusen')->table('tblSEN_Doc')->insert($rows);
        }

        // staging is now finalized; drop the sidecar meta file
        @unlink($this->docMetaPath($senId));
    }
}
