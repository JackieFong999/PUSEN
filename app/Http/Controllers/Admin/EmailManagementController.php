<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\TempEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Email;

/**
 * Email Management (Super Administrator only).
 *
 * Bulk-sends template emails for selected SEN cases:
 *  - ET-003 -> the subject teachers of the case's student
 *  - ET-004 -> the student (tblStudent has NO email column yet, so the
 *              recipient is the MAIL_STUDENT_EMAIL placeholder until a real
 *              field is added; MAIL_DEV_OVERRIDE_TO replaces everyone in dev)
 * One email per (SEN case, recipient); every attempt is logged to tblEmail_Log.
 */
class EmailManagementController extends Controller
{
    /** Email Management page (menu: Email Management, SA only). */
    public function index()
    {
        $devEmail = TempEmail::get();
        $studentEmail = TempEmail::get();

        return view('admin.email-management', [
            'devEmail'      => $devEmail,
            'studentEmail'  => $studentEmail,
            'devOverrideOn' => $devEmail !== '',
        ]);
    }

    /** Autocomplete: SEN case numbers (prefix match). */
    public function caseSearch(Request $request)
    {
        $q = trim((string) $request->input('q'));
        if ($q === '') {
            return response()->json([]);
        }
        $rows = DB::connection('pusen')->table('tblSEN')
            ->where('SEN_Id', 'like', $q . '%')
            ->orderBy('SEN_Id')
            ->limit(50)
            ->pluck('SEN_Id');

        return response()->json($rows->values()->all());
    }

    /** Autocomplete: student numbers that exist in SEN cases. */
    public function studentSearch(Request $request)
    {
        $q = trim((string) $request->input('q'));
        $conn = DB::connection('pusen');

        // collation-safe: tblStudent is 0900_ai_ci, tblSEN is unicode_ci ->
        // no subquery/join between them (error 1267); resolve ids first
        $idsInSen = $conn->table('tblSEN')->distinct()->pluck('Student_Id')->filter()->values()->all();
        if (! $idsInSen) {
            return response()->json([]);
        }

        $rows = $conn->table('tblStudent')
            ->whereIn('Student_Id', $idsInSen)
            ->when($q !== '', function ($w) use ($q) {
                $w->where(function ($w2) use ($q) {
                    $w2->where('Student_Id', 'like', $q . '%')
                        ->orWhere('Student_Name_Eng', 'like', '%' . $q . '%')
                        ->orWhere('Student_Name_Chn', 'like', '%' . $q . '%');
                });
            })
            ->orderBy('Student_Id')
            ->limit(50)
            ->get(['Student_Id', 'Student_Name_Eng', 'Student_Name_Chn']);

        return response()->json($rows->map(fn ($r) => [
            'id'    => $r->Student_Id,
            'label' => trim($r->Student_Id . ' — ' . ($r->Student_Name_Eng ?? '') . ($r->Student_Name_Chn ? '(' . $r->Student_Name_Chn . ')' : '')),
        ])->values());
    }

    /**
     * Grid rows for the selected cases.
     * Input: sen_ids[] (exact SEN cases) and/or student_ids[] (expands to ALL
     * of that student's SEN cases) and/or all=1 (every SEN case).
     */
    public function data(Request $request)
    {
        $conn = DB::connection('pusen');
        $senIds = array_values(array_filter(array_map('trim', (array) $request->input('sen_ids', []))));
        $studentIds = array_values(array_filter(array_map('trim', (array) $request->input('student_ids', []))));
        $all = (bool) $request->input('all');

        $q = $conn->table('tblSEN');
        if ($all) {
            // every case
        } elseif ($senIds || $studentIds) {
            $q->where(function ($w) use ($senIds, $studentIds) {
                if ($senIds) {
                    $w->whereIn('SEN_Id', $senIds);
                }
                if ($studentIds) {
                    $w->orWhereIn('Student_Id', $studentIds);
                }
            });
        } else {
            return response()->json([]);
        }

        $rows = $q->orderBy('SEN_Id')->get();

        return response()->json($this->rowsPayload($conn, $rows));
    }

    /** Send emails for the selected cases. */
    public function send(Request $request)
    {
        $conn = DB::connection('pusen');

        $type = (string) $request->input('type'); // subject_teacher | student | both
        $senIds = array_values(array_filter(array_map('trim', (array) $request->input('sen_ids', []))));

        if (! in_array($type, ['subject_teacher', 'student', 'both'], true)) {
            return response()->json(['success' => false, 'message' => 'Invalid recipient type.'], 422);
        }
        if (! $senIds) {
            return response()->json(['success' => false, 'message' => 'No SEN case selected.'], 422);
        }

        $rows = $conn->table('tblSEN')->whereIn('SEN_Id', $senIds)->get();
        if ($rows->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Selected SEN cases not found.'], 404);
        }

        // --- resolve recipient emails (one entry per SEN case + recipient) ---
        $override = TempEmail::get();
        $studentEmail = TempEmail::get();

        $studentIds = $rows->pluck('Student_Id')->unique()->filter()->all();
        $regs = $studentIds
            ? $conn->table('tblStudent_Reg')->whereIn('Student_Id', $studentIds)->get(['Student_Id', 'Subject_Code'])
            : collect();
        $subjectCodes = $regs->pluck('Subject_Code')->unique()->filter()->all();
        $teacherByCode = $subjectCodes
            ? $conn->table('tblSubject')->whereIn('Subject_Code', $subjectCodes)->get(['Subject_Code', 'Teacher_Staff_Id'])->keyBy('Subject_Code')
            : collect();
        $staffIds = $teacherByCode->pluck('Teacher_Staff_Id')->unique()->filter()->all();
        $staffMap = $staffIds
            ? $conn->table('tblStaff')->whereIn('Staff_Id', $staffIds)->get(['Staff_Id', 'SSO_Email', 'Staff_Display_Name'])->keyBy('Staff_Id')
            : collect();
        $studentNameMap = $studentIds
            ? $conn->table('tblStudent')->whereIn('Student_Id', $studentIds)->get(['Student_Id', 'Student_Name_Eng'])->keyBy('Student_Id')
            : collect();

        $teacherEmails = [];
        $studentEmails = [];
        $seenTeacher = [];
        foreach ($rows as $r) {
            foreach ($regs->where('Student_Id', $r->Student_Id) as $reg) {
                $tid = $teacherByCode->get($reg->Subject_Code)?->Teacher_Staff_Id;
                if (! $tid) {
                    continue;
                }
                $email = trim((string) ($staffMap->get($tid)->SSO_Email ?? ''));
                $email = $override !== '' ? $override : $email;
                if ($email === '') {
                    continue;
                }
                // one email per (SEN case, recipient); same teacher for the same case sends once
                $key = $r->SEN_Id . '|' . $email;
                if (isset($seenTeacher[$key])) {
                    continue;
                }
                $seenTeacher[$key] = true;
                $teacherEmails[] = [
                    'email'      => $email,
                    'name'       => (string) ($staffMap->get($tid)->Staff_Display_Name ?? $tid),
                    'sen_id'     => $r->SEN_Id,
                    'student_id' => $r->Student_Id,
                ];
            }
            $email = $override !== '' ? $override : $studentEmail;
            if ($email !== '') {
                $studentEmails[] = [
                    'email'      => $email,
                    'name'       => (string) ($studentNameMap->get($r->Student_Id)->Student_Name_Eng ?? $r->Student_Id),
                    'sen_id'     => $r->SEN_Id,
                    'student_id' => $r->Student_Id,
                ];
            }
        }

        // template per recipient group
        $toSend = [];
        if (in_array($type, ['subject_teacher', 'both'], true)) {
            $toSend['ET-003'] = $teacherEmails;
        }
        if (in_array($type, ['student', 'both'], true)) {
            $toSend['ET-004'] = $studentEmails;
        }
        $toSend = array_filter($toSend);

        if (! $toSend) {
            return response()->json(['success' => true, 'sent' => 0, 'failed' => 0, 'recipients' => 0, 'message' => 'No recipients resolved.']);
        }

        // templates
        $templates = $conn->table('tblEmail_Template')
            ->whereIn('Template_Name', array_keys($toSend))
            ->get()
            ->keyBy('Template_Name');

        // SMTP config
        $smtp = $conn->table('tblConfig_SMTP')->orderBy('Id')->first();
        if (! $smtp) {
            return response()->json(['success' => false, 'message' => 'SMTP not configured (tblConfig_SMTP empty).']);
        }

        $sent = 0;
        $failed = 0;
        try {
            $transport = new EsmtpTransport($smtp->Host, (int) $smtp->Port, strtolower((string) $smtp->Security) === 'tls' ? 'tls' : 'ssl');
            $transport->setUsername($smtp->Username);
            $transport->setPassword($smtp->Password);
            $mailer = new Mailer($transport);

            foreach ($toSend as $tplName => $recipients) {
                $tpl = $templates->get($tplName);
                $subject = $tpl ? (trim((string) $tpl->Template_Title) ?: 'SEN Details Updated') : 'SEN Details Updated';
                $body = $tpl ? trim((string) $tpl->Template_Content) : '';
                foreach ($recipients as $rcpt) {
                    try {
                        $mailer->send((new Email())
                            ->from($smtp->Username)
                            ->to($rcpt['email'])
                            ->subject($subject)
                            ->text($body));
                        $sent++;
                        $this->logEmail($conn, $tplName, $rcpt, 'SENT', '');
                    } catch (\Throwable $e) {
                        Log::error("EmailManagement ({$tplName}): failed to {$rcpt['email']}: " . $e->getMessage());
                        $failed++;
                        $this->logEmail($conn, $tplName, $rcpt, 'FAILED', substr($e->getMessage(), 0, 97));
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('EmailManagement transport failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'SMTP error: ' . $e->getMessage()]);
        }

        Log::info("EmailManagement: type={$type} cases=" . count($senIds) . " sent={$sent} failed={$failed}");
        return response()->json(['success' => true, 'sent' => $sent, 'failed' => $failed, 'recipients' => array_sum(array_map('count', $toSend))]);
    }

    /** Write one row per sent/failed email to tblEmail_Log (manual Email Management audit). */
    private function logEmail($conn, string $template, array $rcpt, string $status, string $remark): void
    {
        try {
            $conn->table('tblEmail_Log')->insert([
                'SEN_Id'          => $rcpt['sen_id'],
                'Student_Id'      => $rcpt['student_id'] ?? null,
                'Recipient_Type'  => $template === 'ET-003' ? 'SUBJECT_TEACHER' : 'STUDENT',
                'Template_Name'   => $template,
                'Recipient_Name'  => $rcpt['name'] ?? null,
                'Recipient_Email' => $rcpt['email'],
                'Email_Status'    => $status,
                'Remarks'         => $remark !== '' ? $remark : null,
                'created_at'      => now(),
                'created_by'      => (string) (auth()->id() ?? 'system'),
            ]);
        } catch (\Throwable $e) {
            Log::error('EmailManagement: failed to write tblEmail_Log: ' . $e->getMessage());
        }
    }

    /** Build grid rows: SEN no, student id, names, subject teacher(s), SEN_Type. */
    private function rowsPayload($conn, $rows)
    {
        $studentIds = $rows->pluck('Student_Id')->unique()->filter()->all();
        $students = $studentIds
            ? $conn->table('tblStudent')->whereIn('Student_Id', $studentIds)->get()->keyBy('Student_Id')
            : collect();

        // subject teacher(s) per student: student_reg -> subject codes -> Teacher_Staff_Id -> staff
        $teachersByStudent = collect();
        if ($studentIds) {
            $regs = $conn->table('tblStudent_Reg')->whereIn('Student_Id', $studentIds)->get(['Student_Id', 'Subject_Code']);
            $subjectCodes = $regs->pluck('Subject_Code')->unique()->filter()->all();
            $teacherByCode = $subjectCodes
                ? $conn->table('tblSubject')->whereIn('Subject_Code', $subjectCodes)->get(['Subject_Code', 'Teacher_Staff_Id'])->keyBy('Subject_Code')
                : collect();
            $staffIds = $teacherByCode->pluck('Teacher_Staff_Id')->unique()->filter()->all();
            $staffMap = $staffIds
                ? $conn->table('tblStaff')->whereIn('Staff_Id', $staffIds)->get(['Staff_Id', 'Staff_Display_Name', 'Staff_Name'])->keyBy('Staff_Id')
                : collect();

            foreach ($regs as $reg) {
                $tid = $teacherByCode->get($reg->Subject_Code)?->Teacher_Staff_Id;
                if (! $tid) {
                    continue;
                }
                $s = $staffMap->get($tid);
                $name = $s ? ($s->Staff_Display_Name ?: ($s->Staff_Name ?: $tid)) : $tid;
                $teachersByStudent[$reg->Student_Id] = ($teachersByStudent[$reg->Student_Id] ?? collect())->push($name);
            }
        }

        // SEN Type: id -> value map (no joins, collation-safe)
        $typeIds = $rows->pluck('SEN_Type_ID')->unique()->filter()->all();
        $typeMap = $typeIds
            ? $conn->table('tblSEN_Type')->whereIn('Id', $typeIds)->get(['Id', 'SEN_Type'])->pluck('SEN_Type', 'Id')
            : collect();

        return $rows->map(function ($r) use ($students, $teachersByStudent, $typeMap) {
            $st = $students->get($r->Student_Id);
            $teachers = ($teachersByStudent->get($r->Student_Id) ?? collect())->unique()->values();

            return [
                'sen_id'           => $r->SEN_Id,
                'student_id'       => $r->Student_Id,
                'student_name_eng' => $st->Student_Name_Eng ?? '—',
                'student_name_chn' => $st->Student_Name_Chn ?? '—',
                'subject_teacher'  => $teachers->join('; '),
                'sen_type'         => $r->SEN_Type_ID ? ($typeMap->get($r->SEN_Type_ID) ?? '—') : '—',
            ];
        })->values();
    }
}
