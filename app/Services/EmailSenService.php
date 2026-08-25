<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Email;

/**
 * ET-002 — SEN stakeholder-change email after CSV import.
 *
 * Manual "Send Email" button on the Data Import screen (SA only). Runs:
 *  Part 1: detect stakeholder changes from today's imports (updated_at >= HK today
 *          on tblAdvisor_Student / tblSubject / tblStudent_Reg) -> all affected
 *          SEN cases get a PENDING row in tblEmail_SEN (skip if PENDING exists).
 *  Part 2: for each PENDING job, resolve recipients (PL + Academic Advisor from
 *          tblAdvisor_Student, DA/Counsellor/USSO from tblSEN; NO subject teacher)
 *          -> one PENDING row per recipient in tblEmail_List; job -> PROCESSED.
 *  Part 3: send ET-002 to each PENDING tblEmail_List.Email (dev: Email_To from
 *          tblEmail_Temp, BCC = Email_BCC); SENT on success, FAILED + reason on error.
 */
class EmailSenService
{
    private const TEMPLATE = 'ET-002';

    /** Run all three parts; returns a summary array for the UI. */
    public function run(): array
    {
        $p1 = $this->createJobs();
        $p2 = $this->buildList();
        $p3 = $this->sendEmails();

        return [
            'jobs_created'  => $p1['created'],
            'jobs_skipped'  => $p1['skipped'],
            'recipients'    => $p2['recipients'],
            'jobs_processed'=> $p2['processed'],
            'sent'          => $p3['sent'],
            'failed'        => $p3['failed'],
        ];
    }

    /* ================= Part 1: create email jobs ================= */

    public function createJobs(): array
    {
        $conn = DB::connection('pusen');
        $today = now('Asia/Hong_Kong')->toDateString();

        // --- 1. advisor changes: new/updated PROG_LEADER / PRIMARY rows today,
        //        whose assignment period covers today
        $advisorStudents = $conn->table('tblAdvisor_Student')
            ->whereIn('Advisor_Type', ['PROG_LEADER', 'PRIMARY'])
            ->whereDate('updated_at', '>=', $today)
            ->whereDate('Start_Date', '<=', $today)
            ->whereDate('End_Date', '>=', $today)
            ->distinct()
            ->pluck('Student_Id');

        // --- 2. subject teacher changes: tblSubject rows touched today
        //        (import only updates when Teacher_Staff_Id / Subject_Type differs)
        $changedCodes = $conn->table('tblSubject')
            ->whereDate('updated_at', '>=', $today)
            ->pluck('Subject_Code');
        $subjectStudents = collect();
        if ($changedCodes->isNotEmpty()) {
            $subjectStudents = $conn->table('tblStudent_Reg')
                ->whereIn('Subject_Code', $changedCodes->all())
                ->distinct()
                ->pluck('Student_Id');
        }

        // --- 3. student-registration changes: new pairs today
        $regStudents = $conn->table('tblStudent_Reg')
            ->whereDate('updated_at', '>=', $today)
            ->distinct()
            ->pluck('Student_Id');

        $studentIds = $advisorStudents->merge($subjectStudents)->merge($regStudents)
            ->unique()->filter()->values()->all();

        if (! $studentIds) {
            return ['created' => 0, 'skipped' => 0];
        }

        // all SEN cases of the affected students (a student may have several)
        $senIds = $conn->table('tblSEN')
            ->whereIn('Student_Id', $studentIds)
            ->pluck('SEN_Id')->unique()->filter()->values()->all();
        if (! $senIds) {
            return ['created' => 0, 'skipped' => 0];
        }

        // skip SEN_IDs that already have a PENDING job
        $pending = $conn->table('tblEmail_SEN')
            ->whereIn('SEN_ID', $senIds)
            ->where('Email_Status', 'PENDING')
            ->pluck('SEN_ID')->all();

        $created = 0;
        $skipped = count($pending);
        $user = $this->actor();
        foreach ($senIds as $senId) {
            if (in_array($senId, $pending, true)) {
                continue;
            }
            $conn->table('tblEmail_SEN')->insert([
                'SEN_ID'       => $senId,
                'Email_Status' => 'PENDING',
                'created_at'   => now(),
                'created_by'   => $user,
            ]);
            $created++;
        }

        Log::info("EmailSenService part1: {$created} job(s) created, {$skipped} skipped (already PENDING)");
        return ['created' => $created, 'skipped' => $skipped];
    }

    /* ================= Part 2: build recipient list ================= */

    public function buildList(): array
    {
        $conn = DB::connection('pusen');
        $today = now('Asia/Hong_Kong')->toDateString();
        $user = $this->actor();
        $tempEmail = TempEmail::get(); // dev: every recipient -> tblEmail_Temp.Email_To

        $jobs = $conn->table('tblEmail_SEN')
            ->where('Email_Status', 'PENDING')
            ->orderBy('Id')
            ->get();

        $recipients = 0;
        $processed = 0;

        foreach ($jobs as $job) {
            $sen = $conn->table('tblSEN')->where('SEN_Id', $job->SEN_ID)->first();
            if (! $sen) {
                // orphan job: nothing to resolve, close it
                $conn->table('tblEmail_SEN')->where('Id', $job->Id)->update([
                    'Email_Status' => 'PROCESSED',
                    'Remarks'      => 'SEN case not found',
                    'updated_at'   => now(),
                    'updated_by'   => $user,
                ]);
                $processed++;
                continue;
            }

            // --- gather staff ids per stakeholder group (dedup by staff) ---
            $groups = [
                'Programme Leader' => $this->advisorIds($conn, $sen->Student_Id, 'PROG_LEADER', $today),
                'Department Admin Staff' => [trim((string) ($sen->Department_Admin_Staff ?? ''))],
                'Counsellor'       => [trim((string) ($sen->Counsellor ?? ''))],
                'Undergraduate Studies Support Officer' => [trim((string) ($sen->Undergraduate_Studies_Support_Officer ?? ''))],
                'Academic Advisor' => $this->advisorIds($conn, $sen->Student_Id, 'PRIMARY', $today),
            ];

            // --- staff -> display name + email (dev override) ---
            $allIds = [];
            foreach ($groups as $ids) {
                foreach ($ids as $id) {
                    if ($id !== '') {
                        $allIds[] = $id;
                    }
                }
            }
            $allIds = array_values(array_unique($allIds));
            $staffMap = $allIds
                ? $conn->table('tblStaff')->whereIn('Staff_Id', $allIds)
                    ->get(['Staff_Id', 'Staff_Display_Name', 'Staff_Name', 'SSO_Email'])
                    ->keyBy('Staff_Id')
                : collect();

            foreach ($groups as $type => $ids) {
                foreach (array_unique(array_filter($ids)) as $staffId) {
                    $s = $staffMap->get($staffId);
                    $conn->table('tblEmail_List')->insert([
                        'SEN_ID'             => $job->SEN_ID,
                        'Stakeholder_Type'   => $type,
                        'Template_Name'      => self::TEMPLATE, // ET-002 (consistent with tblEmail_Log)
                        'Staff_Id'           => $staffId,
                        'Staff_Display_Name' => $s ? ($s->Staff_Display_Name ?: ($s->Staff_Name ?: $staffId)) : $staffId,
                        'Email'              => $tempEmail !== '' ? $tempEmail : trim((string) ($s->SSO_Email ?? '')),
                        'Email_Status'       => 'PENDING',
                        'created_at'         => now(),
                        'created_by'         => $user,
                    ]);
                    $recipients++;
                }
            }

            $conn->table('tblEmail_SEN')->where('Id', $job->Id)->update([
                'Email_Status' => 'PROCESSED',
                'updated_at'   => now(),
                'updated_by'   => $user,
            ]);
            $processed++;
        }

        Log::info("EmailSenService part2: {$recipients} recipient(s) queued, {$processed} job(s) processed");
        return ['recipients' => $recipients, 'processed' => $processed];
    }

    /* ================= Part 3: send emails ================= */

    public function sendEmails(): array
    {
        $conn = DB::connection('pusen');
        $user = $this->actor();

        $tpl = $conn->table('tblEmail_Template')->where('Template_Name', self::TEMPLATE)->first();
        if (! $tpl) {
            Log::error('EmailSenService part3: template ' . self::TEMPLATE . ' not found');
            return ['sent' => 0, 'failed' => 0];
        }

        $smtp = $conn->table('tblConfig_SMTP')->orderBy('Id')->first();
        if (! $smtp) {
            Log::error('EmailSenService part3: no row in tblConfig_SMTP');
            return ['sent' => 0, 'failed' => 0];
        }

        $pending = $conn->table('tblEmail_List')
            ->where('Email_Status', 'PENDING')
            ->orderBy('ID')
            ->get();

        $sent = 0;
        $failed = 0;

        if ($pending->isEmpty()) {
            return ['sent' => 0, 'failed' => 0];
        }

        try {
            $transport = new EsmtpTransport($smtp->Host, (int) $smtp->Port, strtolower((string) $smtp->Security) === 'tls' ? 'tls' : 'ssl');
            $transport->setUsername($smtp->Username);
            $transport->setPassword($smtp->Password);
            $mailer = new Mailer($transport);
        } catch (\Throwable $e) {
            Log::error('EmailSenService part3: transport init failed: ' . $e->getMessage());
            return ['sent' => 0, 'failed' => 0];
        }

        $subject = trim((string) $tpl->Template_Title) ?: 'SEN Details Updated';
        $body = trim((string) $tpl->Template_Content);
        $bcc = TempEmail::bcc(); // dev: tblEmail_Temp.Email_BCC

        foreach ($pending as $row) {
            $message = (new Email())
                ->from($smtp->Username)
                ->to($row->Email)
                ->subject($subject)
                ->text($body);
            if ($bcc !== '') {
                $message->addBcc($bcc);
            }

            try {
                $mailer->send($message);
                $conn->table('tblEmail_List')->where('ID', $row->ID)->update([
                    'Email_Status' => 'SENT',
                    'updated_at'   => now(),
                    'updated_by'   => $user,
                ]);
                $sent++;
            } catch (\Throwable $e) {
                $conn->table('tblEmail_List')->where('ID', $row->ID)->update([
                    'Email_Status' => 'FAILED',
                    'Remarks'      => mb_substr($e->getMessage(), 0, 100),
                    'updated_at'   => now(),
                    'updated_by'   => $user,
                ]);
                Log::error('EmailSenService part3: send to ' . $row->Email . ' failed: ' . $e->getMessage());
                $failed++;
            }
        }

        Log::info("EmailSenService part3: {$sent} sent, {$failed} failed");
        return ['sent' => $sent, 'failed' => $failed];
    }

    /* ================= helpers ================= */

    /** Current advisor ids of a student for the given type (today within period). */
    private function advisorIds($conn, ?string $studentId, string $type, string $today): array
    {
        if (! $studentId) {
            return [];
        }
        return $conn->table('tblAdvisor_Student')
            ->where('Student_Id', $studentId)
            ->where('Advisor_Type', $type)
            ->whereDate('Start_Date', '<=', $today)
            ->whereDate('End_Date', '>=', $today)
            ->pluck('Advisor_Id')
            ->unique()
            ->filter()
            ->values()
            ->all();
    }

    private function actor(): string
    {
        return (string) (Auth::user()?->Staff_Id ?? 'system01');
    }
}
