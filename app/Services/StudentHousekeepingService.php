<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Housekeeping for Student.
 *
 * Finds students whose Student_Status is COMPLETED / LEFT / PASSED AWAY and
 * whose tblStudent.updated_at is strictly older than 3 years, then for each
 * student (one at a time):
 *   1. writes the audit log rows (tblHK_Student_Log / tblHK_SEN_Log /
 *      tblHK_SEN_Doc_Log) and commits them FIRST — the log is the backup,
 *   2. deletes the physical files (abort the student on any unlink error;
 *      missing file = remark, not error), also purging staging leftovers,
 *   3. deletes the records in ONE DB transaction per student in this order:
 *      tblSEN_Doc → tblSEN → tblStudent_Reg → tblAdvisor_Student → tblStudent.
 *
 * No archiving, no recovery. Delete_At is stored in UTC (app timezone).
 *
 * Spec: docs/pusen01-housekeeping-student-spec.md
 */
class StudentHousekeepingService
{
    /** Statuses that make a student eligible for housekeeping. */
    public const QUALIFYING_STATUSES = ['COMPLETED', 'LEFT', 'PASSED AWAY'];

    /** Documents live flat in storage/app/public/sen_docs. */
    public function docDir(): string
    {
        return storage_path('app/public/sen_docs');
    }

    public function stagingDir(): string
    {
        return storage_path('app/public/sen_docs/staging');
    }

    private function conn()
    {
        return DB::connection('pusen');
    }

    /**
     * Students currently qualifying: status + strictly older than 3 years.
     *
     * Deliberately NOT filtered by "already in tblHK_Student_Log": a student
     * only stops qualifying once the record is gone (deleted from tblStudent),
     * which is exactly what re-runs after a failure need. Concurrent runs are
     * prevented by the controller-level lock instead.
     */
    public function qualifyingStudents(): array
    {
        return $this->conn()
            ->table('tblStudent')
            ->whereIn('Student_Status', self::QUALIFYING_STATUSES)
            ->where('updated_at', '<', now()->subYears(3))
            ->orderBy('Student_Id')
            ->get()
            ->all();
    }

    /**
     * Preview counts for the confirm dialog (no changes made).
     */
    public function preview(): array
    {
        $students = $this->qualifyingStudents();
        $ids = array_column($students, 'Student_Id');

        $sen = $docs = $advisor = $reg = 0;
        if ($ids) {
            $sen = (int) $this->conn()->table('tblSEN')->whereIn('Student_Id', $ids)->count();
            $docs = (int) $this->conn()->table('tblSEN_Doc')
                ->whereIn('SEN_Id', function ($q) use ($ids) {
                    $q->select('SEN_Id')->from('tblSEN')->whereIn('Student_Id', $ids);
                })
                ->count();
            $advisor = (int) $this->conn()->table('tblAdvisor_Student')->whereIn('Student_Id', $ids)->count();
            $reg = (int) $this->conn()->table('tblStudent_Reg')->whereIn('Student_Id', $ids)->count();
        }

        return [
            'students'  => count($students),
            'sen'       => $sen,
            'docs'      => $docs,
            'advisor'   => $advisor,
            'reg'       => $reg,
            'stale_as_of' => now()->subYears(3)->toDateTimeString(),
            'students_list' => array_map(function ($s) {
                return [
                    'student_id'    => $s->Student_Id,
                    'name_eng'      => StudentNameEncryption::decrypt($s->Student_Name_Eng),
                    'name_chn'      => StudentNameEncryption::decrypt($s->Student_Name_Chn),
                    'status'        => $s->Student_Status,
                    'updated_at'    => $s->updated_at,
                    'updated_at_hk' => $s->updated_at
                        ? \Carbon\Carbon::parse($s->updated_at)->format('Y-m-d H:i:s')
                        : null,
                ];
            }, $students),
        ];
    }

    /**
     * Run housekeeping for every qualifying student.
     *
     * @return array summary (counters + per-student details)
     */
    public function run(): array
    {
        $conn = $this->conn();
        $user = auth()->user()?->Staff_Id ?? 'system01';
        $now  = now();

        $summary = [
            'students_processed' => 0,
            'students_failed'    => 0,
            'sen_deleted'        => 0,
            'docs_deleted'       => 0,
            'files_deleted'      => 0,
            'files_missing'      => 0,
            'files_failed'       => 0,
            'advisor_deleted'    => 0,
            'reg_deleted'        => 0,
            'details'            => [],
        ];

        foreach ($this->qualifyingStudents() as $student) {
            $detail = ['student' => $student->Student_Id, 'notes' => [], 'error' => null];

            $senIds = $conn->table('tblSEN')->where('Student_Id', $student->Student_Id)->pluck('SEN_Id')->all();
            $docs = $senIds
                ? $conn->table('tblSEN_Doc')->whereIn('SEN_Id', $senIds)->get()->all()
                : [];

            /* ---------- 5.2 log phase — committed first (the backup) ---------- */
            $runId = $conn->table('tblHK_Student_Log')->insertGetId([
                'Student_Id'         => $student->Student_Id,
                'Student_Name_Eng'   => StudentNameEncryption::encrypt($student->Student_Name_Eng),
                'Student_Name_Chn'   => StudentNameEncryption::encrypt($student->Student_Name_Chn),
                'Student_Status'     => $student->Student_Status,
                'Student_created_at' => $student->created_at,
                'Student_updated_at' => $student->updated_at,
                'Remarks'            => 'HK delete: '.count($senIds).' SEN case(s), '.count($docs).' doc(s)',
                'Delete_At'          => $now,
                'Delete_By'          => $user,
            ]);

            foreach ($senIds as $senId) {
                $conn->table('tblHK_SEN_Log')->insert([
                    'HK_Run_Id'  => $runId,
                    'SEN_Id'     => $senId,
                    'Student_Id' => $student->Student_Id,
                    'Remarks'    => 'HK delete',
                    'Delete_At'  => $now,
                    'Delete_By'  => $user,
                ]);
            }

            $docLogIds = [];
            foreach ($docs as $doc) {
                $key = $doc->SEN_Id.'|'.$doc->Doc_Seq;
                $docLogIds[$key] = $conn->table('tblHK_SEN_Doc_Log')->insertGetId([
                    'HK_Run_Id'             => $runId,
                    'SEN_Id'                => $doc->SEN_Id,
                    'Doc_Seq'               => $doc->Doc_Seq,
                    'Doc_Path'              => $doc->Doc_Filename ? $this->docDir().'/'.$doc->Doc_Filename : null,
                    'Doc_Filename'          => $doc->Doc_Filename,
                    'Doc_Filename_Original' => $doc->Doc_Filename_Original,
                    'Remarks'               => $doc->Doc_Filename ? 'pending' : 'no file',
                    'Delete_At'             => $now,
                    'Delete_By'             => $user,
                ]);
            }
            // (log committed — survives anything below)

            /* ---------- 5.3 delete physical files ---------- */
            $fileError = null;
            foreach ($docs as $doc) {
                $logId = $docLogIds[$doc->SEN_Id.'|'.$doc->Doc_Seq] ?? null;

                if (! $doc->Doc_Filename) {
                    continue; // remark already 'no file'
                }

                $path = $this->docDir().'/'.$doc->Doc_Filename;

                if (! is_file($path)) {
                    $summary['files_missing']++;
                    $detail['notes'][] = 'file not found: '.$doc->Doc_Filename;
                    if ($logId) {
                        $conn->table('tblHK_SEN_Doc_Log')->where('Id', $logId)->update(['Remarks' => 'file not found']);
                    }
                    continue;
                }

                if (! @unlink($path)) {
                    $summary['files_failed']++;
                    $fileError = 'Cannot delete file: '.$doc->Doc_Filename;
                    if ($logId) {
                        $conn->table('tblHK_SEN_Doc_Log')->where('Id', $logId)->update(['Remarks' => 'FILE DELETE FAILED']);
                    }
                    break; // abort this student — records stay, re-run possible
                }

                $summary['files_deleted']++;
                if ($logId) {
                    $conn->table('tblHK_SEN_Doc_Log')->where('Id', $logId)->update(['Remarks' => 'deleted']);
                }
            }

            // purge staging leftovers for this student's cases (best effort)
            foreach ($senIds as $senId) {
                foreach (glob($this->stagingDir().'/'.$senId.'*') ?: [] as $stale) {
                    @unlink($stale);
                }
            }

            if ($fileError) {
                $summary['students_failed']++;
                $detail['error'] = $fileError;
                $conn->table('tblHK_Student_Log')->where('Id', $runId)->update(['Remarks' => 'FILE DELETE FAILED: '.$fileError]);
                $summary['details'][] = $detail;
                continue;
            }

            /* ---------- 5.4 delete records — one transaction per student ---------- */
            $conn->beginTransaction();
            try {
                $docsDeleted = 0;
                if ($senIds) {
                    $docsDeleted = $conn->table('tblSEN_Doc')->whereIn('SEN_Id', $senIds)->delete();
                    $conn->table('tblSEN')->whereIn('SEN_Id', $senIds)->delete();
                }
                $regDeleted     = $conn->table('tblStudent_Reg')->where('Student_Id', $student->Student_Id)->delete();
                $advisorDeleted = $conn->table('tblAdvisor_Student')->where('Student_Id', $student->Student_Id)->delete();
                $conn->table('tblStudent')->where('Student_Id', $student->Student_Id)->delete();
                $conn->commit();
            } catch (\Throwable $e) {
                $conn->rollBack();
                $summary['students_failed']++;
                $detail['error'] = 'DB delete failed: '.$e->getMessage();
                $conn->table('tblHK_Student_Log')->where('Id', $runId)->update(['Remarks' => 'DB DELETE FAILED: '.$e->getMessage()]);
                $summary['details'][] = $detail;
                continue;
            }

            $summary['students_processed']++;
            $summary['sen_deleted']     += count($senIds);
            $summary['docs_deleted']    += $docsDeleted;
            $summary['advisor_deleted'] += $advisorDeleted;
            $summary['reg_deleted']     += $regDeleted;
            $detail['notes'][] = 'deleted';
            $conn->table('tblHK_Student_Log')->where('Id', $runId)->update([
                'Remarks' => 'HK delete: '.count($senIds).' SEN case(s), '.$docsDeleted.' doc(s) deleted',
            ]);
            $summary['details'][] = $detail;
        }

        return $summary;
    }
}
