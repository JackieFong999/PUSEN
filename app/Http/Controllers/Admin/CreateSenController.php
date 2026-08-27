<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\StudentNameEncryption;
use App\Services\TempEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Email;

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

        // Restricted roles (KS etc.): a case is only viewable when its student is
        // currently advised by this staff member (same rule as SEN Search).
        // Guards against URL manipulation (e.g. changing ?sen_id=).
        if ($isEdit && $this->isRestrictedUser() && ! $this->canViewStudent($editSen->Student_Id)) {
            return response()->view('errors.access-denied', [], 403);
        }

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
            ->orderBy('display_order_seq')
            ->orderBy('Id')
            ->get(['Id', 'SEN_Type']);

        // Temporary Special Support options (lookup table)
        $tempSupports = $conn->table('tblTemporary_Special_Support')
            ->orderBy('display_order_seq')
            ->orderBy('Id')
            ->get(['Id', 'Temporary_Special_Support']);

        // edit mode: ensure the record's current value is in the dropdown even if
        // it is not in the lookup table (legacy free-text values)
        if ($isEdit && $editSen->Temporary_Special_Support_ID && ! $tempSupports->contains('Id', $editSen->Temporary_Special_Support_ID)) {
            $row = $conn->table('tblTemporary_Special_Support')->where('Id', $editSen->Temporary_Special_Support_ID)->first(['Id', 'Temporary_Special_Support']);
            if ($row) {
                $tempSupports->push($row);
            }
        }

        // active students only (Student_Id selection box) — names decrypted
        // for display (encrypted at rest since 2026-08-26)
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

        $students = $students->map(fn ($s) => (object) [
            'Student_Id'        => $s->Student_Id,
            'Student_Name_Eng'  => StudentNameEncryption::decrypt($s->Student_Name_Eng),
        ]);

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

        // staff_id -> display name + email for the preview email banner.
        // Temporary demo address (tblEmail_Temp): every email becomes the temp
        // address so no real stakeholder receives mail during development.
        $devEmail = TempEmail::get();
        $staffEmails = $conn->table('tblStaff')
            ->get(['Staff_Id', 'Staff_Display_Name', 'Staff_Name', 'SSO_Email'])
            ->mapWithKeys(fn ($s) => [
                $s->Staff_Id => [
                    'name'  => $s->Staff_Display_Name ?: $s->Staff_Name ?: $s->Staff_Id,
                    'email' => $devEmail !== '' ? $devEmail : (string) $s->SSO_Email,
                ],
            ])
            ->all();

        return view('admin.create-sen', compact('staff', 'senTypes', 'tempSupports', 'students', 'nextSenId', 'isEdit', 'isView', 'editSen', 'editDocs', 'editPlLabels', 'staffEmails'));
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

        // Restricted roles (KS etc.): student info is only served for students
        // this staff member currently advises (same rule as SEN Search).
        if ($this->isRestrictedUser() && ! $this->canViewStudent($studentId)) {
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
                'student_name_eng' => StudentNameEncryption::decrypt($student->Student_Name_Eng),
                'student_name_chn' => StudentNameEncryption::decrypt($student->Student_Name_Chn),
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

        // SEN_Type: optional; the form posts the lookup Id
        $senTypeId = null;
        $senTypeRaw = trim((string) $request->input('sen_type'));
        if ($senTypeRaw !== '') {
            $senTypeId = (int) $senTypeRaw;
            if (! $conn->table('tblSEN_Type')->where('Id', $senTypeId)->exists()) {
                return response()->json(['success' => false, 'message' => 'Invalid SEN Type.'], 422);
            }
        }
        $data['SEN_Type_ID'] = $senTypeId;

        $data['Student_Id'] = $studentId;
        $data['SEN_Detail'] = $this->nullable($request->input('sen_detail'));
        $data['Special_Support_Required'] = $this->nullable($request->input('special_support_required'));
        $data['Special_Examination_Arrangement'] = $this->nullable($request->input('special_examination_arrangement'));

        // Temporary Special Support: optional; the form posts the lookup Id
        $tempSupportId = null;
        $tempSupportRaw = trim((string) $request->input('temporary_special_support'));
        if ($tempSupportRaw !== '') {
            $tempSupportId = (int) $tempSupportRaw;
            if (! $conn->table('tblTemporary_Special_Support')->where('Id', $tempSupportId)->exists()) {
                return response()->json(['success' => false, 'message' => 'Invalid Temporary Special Support.'], 422);
            }
        }
        $data['Temporary_Special_Support_ID'] = $tempSupportId;

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

        // "send email" checkbox checked -> notify stakeholders (create or edit)
        // (email failure never blocks the save; status goes back in the JSON)
        $emailStatus = 'skipped';
        if ($request->input('send_email') === '1') {
            $emailStatus = $this->sendStakeholderEmail($studentId, $data, $finalSenId);
        }

        return response()->json(['success' => true, 'sen_id' => $finalSenId, 'email' => $emailStatus]);
    }

    /**
     * Send the ET-001 stakeholder notification after a SEN case is created.
     * Recipients: Programme Leader + Academic Advisor (current assignments in
     * tblAdvisor_Student), Department Admin Staff, Counsellor, USSO.
     * One email per stakeholder (deduped by staff). Failure never blocks the save.
     * Every attempt is logged to tblEmail_Log (Recipient_Type = stakeholder role).
     * Returns 'sent' | 'skipped' (no recipients) | 'failed'.
     */
    private function sendStakeholderEmail(string $studentId, array $data, string $senId): string
    {
        $conn = DB::connection('pusen');
        $today = now()->toDateString();

        // --- gather stakeholder staff ids (unique, keep order) ---
        $ids = [];
        $roleMap = [];
        $addIds = function ($v, $role) use (&$ids, &$roleMap) {
            if ($v === null || $v === '') {
                return;
            }
            $arr = is_array($v)
                ? $v
                : ($v instanceof \Traversable ? iterator_to_array($v) : [$v]);
            foreach ($arr as $id) {
                $id = trim((string) $id);
                if ($id !== '' && ! in_array($id, $ids, true)) {
                    $ids[] = $id;
                    $roleMap[$id] = $role; // first role wins (gathering order = priority)
                }
            }
        };

        // Programme Leader(s): current PROG_LEADER advisors of this student
        $plRows = $conn->table('tblAdvisor_Student')
            ->where('Student_Id', $studentId)
            ->where('Advisor_Type', 'PROG_LEADER')
            ->whereDate('Start_Date', '<=', $today)
            ->whereDate('End_Date', '>=', $today)
            ->pluck('Advisor_Id');
        $addIds($plRows, 'Programme Leader');

        // Department Admin Staff / Counsellor / USSO from the saved SEN row
        $addIds($data['Department_Admin_Staff'] ?? null, 'Department Admin Staff');
        $addIds($data['Counsellor'] ?? null, 'Counsellor');
        $addIds($data['Undergraduate_Studies_Support_Officer'] ?? null, 'Undergraduate Studies Support Officer');

        // Academic Advisor(s): current PRIMARY advisors of this student
        $advRows = $conn->table('tblAdvisor_Student')
            ->where('Student_Id', $studentId)
            ->where('Advisor_Type', 'PRIMARY')
            ->whereDate('Start_Date', '<=', $today)
            ->whereDate('End_Date', '>=', $today)
            ->pluck('Advisor_Id');
        $addIds($advRows, 'Primary');

        if (! $ids) {
            return 'skipped';
        }

        // --- resolve emails (temp demo address replaces every address) ---
        $devEmail = TempEmail::get();
        $staff = $conn->table('tblStaff')
            ->whereIn('Staff_Id', $ids)
            ->get(['Staff_Id', 'SSO_Email', 'Staff_Display_Name']);
        // one recipient per unique staff member (dedupe by staff, NOT by email,
        // so the dev override still sends one email per stakeholder)
        $recipients = [];
        foreach ($staff as $s) {
            $email = $devEmail !== '' ? $devEmail : trim((string) $s->SSO_Email);
            if ($email === '') {
                continue;
            }
            $recipients[] = [
                'email'    => $email,
                'staff_id' => $s->Staff_Id,
                'name'     => (string) ($s->Staff_Display_Name ?? $s->Staff_Id),
                'role'     => $roleMap[$s->Staff_Id] ?? 'Stakeholder',
            ];
        }
        if (! $recipients) {
            return 'skipped';
        }

        // --- email template ET-001 ---
        $tpl = $conn->table('tblEmail_Template')->where('Template_Name', 'ET-001')->first();
        if (! $tpl) {
            Log::warning('sendStakeholderEmail: template ET-001 not found');
            return 'failed';
        }

        // --- SMTP config ---
        $smtp = $conn->table('tblConfig_SMTP')->orderBy('Id')->first();
        if (! $smtp) {
            Log::warning('sendStakeholderEmail: no row in tblConfig_SMTP');
            return 'failed';
        }

        try {
            $transport = new EsmtpTransport($smtp->Host, (int) $smtp->Port, strtolower((string) $smtp->Security) === 'tls' ? 'tls' : 'ssl');
            $transport->setUsername($smtp->Username);
            $transport->setPassword($smtp->Password);
            $mailer = new Mailer($transport);

            $sent = 0;
            foreach ($recipients as $rcpt) {
                $message = (new Email())
                    ->from($smtp->Username)
                    ->to($rcpt['email'])
                    ->subject(trim((string) $tpl->Template_Title) ?: 'SEN Details Updated')
                    ->text(trim((string) $tpl->Template_Content));
                try {
                    $mailer->send($message);
                    $sent++;
                    $this->logStakeholderEmail($conn, $senId, $studentId, $rcpt, 'SENT', '');
                } catch (\Throwable $e) {
                    Log::error('sendStakeholderEmail: failed to ' . $rcpt['email'] . ': ' . $e->getMessage());
                    $this->logStakeholderEmail($conn, $senId, $studentId, $rcpt, 'FAILED', substr($e->getMessage(), 0, 97));
                }
            }

            Log::info('sendStakeholderEmail: sent ' . $sent . '/' . count($recipients) . ' for student ' . $studentId . ' sen ' . $senId);
            return $sent > 0 ? 'sent' : 'failed';
        } catch (\Throwable $e) {
            Log::error('sendStakeholderEmail failed: ' . $e->getMessage());
            return 'failed';
        }
    }

    /** Write one row per stakeholder email attempt to tblEmail_Log (ET-001 audit). */
    private function logStakeholderEmail($conn, string $senId, string $studentId, array $rcpt, string $status, string $remark): void
    {
        try {
            $conn->table('tblEmail_Log')->insert([
                'SEN_Id'          => $senId,
                'Student_Id'      => $studentId,
                'Recipient_Type'  => $rcpt['role'],
                'Template_Name'   => 'ET-001',
                'Recipient_Name'  => $rcpt['name'],
                'Recipient_Email' => $rcpt['email'],
                'Email_Status'    => $status,
                'Remarks'         => $remark !== '' ? $remark : null,
                'created_at'      => now(),
                'created_by'      => (string) (auth()->id() ?? 'system01'),
            ]);
        } catch (\Throwable $e) {
            Log::error('sendStakeholderEmail: failed to write tblEmail_Log: ' . $e->getMessage());
        }
    }

    /** file extensions shown inside the locked viewer (PDF via PDF.js, images natively) */
    private const PREVIEW_IMAGE_EXTS = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp'];

    /**
     * Serve a SEN document:
     *  - ?dl=1  -> download. PDFs are password-encrypted first (AES-256,
     *              password from tblConfig_Password PW_Type='PDF'), other types
     *              are served as-is. Content-Disposition carries the ORIGINAL
     *              filename (RFC 5987 for non-ASCII).
     *  - ?raw=1 -> raw bytes for the locked viewer (PDF.js blob fetch).
     *  - none   -> preview navigation: PDFs and images redirect to the locked
     *              viewer page (no download/print), other types stay inline.
     */
    public function previewDoc(Request $request, string $filename)
    {
        $filename = basename($filename);

        if (! $this->docAllowed($filename)) {
            return response()->view('errors.access-denied', [], 403);
        }

        $path = $this->findDoc($filename);
        if ($path === null) {
            abort(404, 'Document not found');
        }

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $original = $this->originalNameFor($filename);
        $safe = str_replace(['"', '\\', "\r", "\n"], '_', $original);

        // ----- download: PDFs are encrypted with the PW_Type=PDF password -----
        if ($request->boolean('dl')) {
            if ($ext === 'pdf') {
                $encrypted = $this->encryptPdf($path);
                if ($encrypted === null) {
                    abort(500, 'PDF encryption is not configured (tblConfig_Password PW_Type=PDF).');
                }
                return response()->download($encrypted, $original, [
                    'Content-Type' => 'application/pdf',
                ])->deleteFileAfterSend(true);
            }

            return response()->file($path, [
                'Content-Disposition' => 'attachment; filename="' . $safe . '"; filename*=UTF-8\'\'' . rawurlencode($original),
            ]);
        }

        // ----- raw bytes for the locked viewer (PDF.js fetch as blob) -----
        if ($request->boolean('raw')) {
            return response()->file($path, [
                'Content-Type'           => $this->mimeFor($ext),
                'Content-Disposition'    => 'inline; filename="' . $safe . '"',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        // ----- preview navigation: PDFs/images go to the locked viewer -----
        if ($ext === 'pdf' || in_array($ext, self::PREVIEW_IMAGE_EXTS, true)) {
            return redirect()->route('admin.sen-doc.viewer', $filename);
        }

        return response()->file($path, [
            'Content-Disposition' => 'inline; filename="' . $safe . '"; filename*=UTF-8\'\'' . rawurlencode($original),
        ]);
    }

    /**
     * Locked document viewer page (PDF.js for PDFs, plain <img> for images).
     * No download button, no print button; printing is also blocked in the page.
     */
    public function viewer(Request $request, string $filename)
    {
        $filename = basename($filename);

        if (! $this->docAllowed($filename)) {
            return response()->view('errors.access-denied', [], 403);
        }
        if ($this->findDoc($filename) === null) {
            abort(404, 'Document not found');
        }

        return view('admin.sen-doc-viewer', [
            'filename' => $filename,
            'original' => $this->originalNameFor($filename),
            'ext'      => strtolower(pathinfo($filename, PATHINFO_EXTENSION)),
        ]);
    }

    /** staging or final path for a doc filename, or null when not found */
    private function findDoc(string $filename): ?string
    {
        foreach ([$this->finalDocDir(), $this->stagingDir()] as $dir) {
            $p = $dir . '/' . $filename;
            if (is_file($p)) {
                return $p;
            }
        }

        return null;
    }

    /** Access rule shared by previewDoc + viewer (restricted roles: advised student only). */
    private function docAllowed(string $filename): bool
    {
        if (! $this->isRestrictedUser()) {
            return true;
        }
        $senId = preg_match('/^(SEN-\d+)_/', $filename, $m) ? $m[1] : null;
        $studentId = $senId
            ? DB::connection('pusen')->table('tblSEN')->where('SEN_Id', $senId)->value('Student_Id')
            : null;

        return $studentId !== null && $this->canViewStudent($studentId);
    }

    /** MIME type for the raw viewer fetch (PDFs + preview images). */
    private function mimeFor(string $ext): string
    {
        return match ($ext) {
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'bmp' => 'image/bmp',
            default => 'application/octet-stream',
        };
    }

    /**
     * Encrypt a PDF with the password from tblConfig_Password (PW_Type='PDF')
     * via scripts/encrypt_pdf.py (pikepdf, AES-256). Returns the temp file path
     * or null when the password is missing / encryption fails.
     */
    private function encryptPdf(string $path): ?string
    {
        $password = DB::connection('pusen')
            ->table('tblConfig_Password')
            ->where('PW_Type', 'PDF')
            ->value('Password');

        if (! $password) {
            Log::error('encryptPdf: no password for PW_Type=PDF in tblConfig_Password');

            return null;
        }

        $encrypted = tempnam(sys_get_temp_dir(), 'senc') . '.pdf';
        $script = base_path('scripts/encrypt_pdf.py');
        $cmd = sprintf(
            '/usr/bin/python3 %s %s %s %s 2>&1',
            escapeshellarg($script),
            escapeshellarg($path),
            escapeshellarg($encrypted),
            escapeshellarg((string) $password)
        );
        $output = [];
        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0 || ! is_file($encrypted)) {
            @unlink($encrypted);
            Log::error('encryptPdf failed: ' . implode(' ', $output));

            return null;
        }

        return $encrypted;
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

    /** Restricted roles (KS etc.) are scoped to students they currently advise. */
    private function isRestrictedUser(): bool
    {
        $user = Auth::user();
        return $user && ! in_array($user->Role_Id, ['SA', 'AU'], true);
    }

    /**
     * Can this (restricted) user see data of the given student?
     * Advisor_Id = login Staff_Id AND today within Start_Date..End_Date
     * AND the student record is ACTIVE.
     */
    private function canViewStudent(?string $studentId): bool
    {
        if ($studentId === null || $studentId === '') {
            return false;
        }
        $today = now()->toDateString();
        $conn = DB::connection('pusen');

        $advised = $conn->table('tblAdvisor_Student')
            ->where('Advisor_Id', Auth::user()->Staff_Id)
            ->where('Student_Id', $studentId)
            ->whereDate('Start_Date', '<=', $today)
            ->whereDate('End_Date', '>=', $today)
            ->exists();
        if (! $advised) {
            return false;
        }

        return $conn->table('tblStudent')
            ->where('Student_Id', $studentId)
            ->where('Student_Status', 'ACTIVE')
            ->exists();
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
