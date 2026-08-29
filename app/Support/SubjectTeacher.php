<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Subject -> teacher resolution for the CURRENT academic period.
 *
 * A Subject_Code can map to a different Teacher_Staff_Id in tblSubject for
 * each (Academic_Year, Semester), so lookups must filter by the period
 * resolved from today's date (see AcademicPeriod). Exact match only; subjects
 * with no row for the current period are simply absent from the result.
 */
class SubjectTeacher
{
    /**
     * @param  string[]  $subjectCodes
     * @return array<string, object{Subject_Code: string, Teacher_Staff_Id: string}>  code => row (first occurrence wins)
     */
    public static function mapForCodes(array $subjectCodes, string|\DateTimeInterface|null $date = null): array
    {
        $subjectCodes = array_values(array_unique(array_filter(array_map('trim', $subjectCodes))));
        if ($subjectCodes === []) {
            return [];
        }

        $period = AcademicPeriod::forDate($date);
        if ($period['semester'] === null) {
            return [];
        }

        $rows = DB::connection('pusen')
            ->table('tblSubject')
            ->whereIn('Subject_Code', $subjectCodes)
            ->where('Academic_Year', $period['year'])
            ->where('Semester', $period['semester'])
            ->get(['Subject_Code', 'Teacher_Staff_Id']);

        $map = [];
        foreach ($rows as $r) {
            if ($r->Teacher_Staff_Id === null || $r->Teacher_Staff_Id === '') {
                continue;
            }
            // keep the object shape so callers can use ->Teacher_Staff_Id
            $map[$r->Subject_Code] ??= (object) [
                'Subject_Code'     => $r->Subject_Code,
                'Teacher_Staff_Id' => $r->Teacher_Staff_Id,
            ];
        }

        return $map;
    }
}
