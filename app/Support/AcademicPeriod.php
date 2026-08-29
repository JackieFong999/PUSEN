<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Resolves a calendar date to the school (Academic_Year, Semester) pair.
 *
 * Academic year = calendar year of the preceding 1 Aug (e.g. the 2026/27
 * school year runs Aug 2026 - Jul 2027 and is labelled 2026). The semester
 * comes from the month/day ranges in tblSemester (Sem 2 spans the calendar
 * year boundary, so the range check wraps).
 */
class AcademicPeriod
{
    /** @return array{year: int, semester: int|null} */
    public static function forDate(string|\DateTimeInterface|null $date = null): array
    {
        $date = $date ? Carbon::parse($date) : now();

        $month         = (int) $date->format('n');
        $day           = (int) $date->format('j');
        $calendarYear  = (int) $date->format('Y');
        $academicYear  = $month >= 8 ? $calendarYear : $calendarYear - 1;

        $semester = null;
        $rows = DB::connection('pusen')
            ->table('tblSemester')
            ->orderBy('Semester')
            ->get(['Semester', 'Start_Month', 'Start_Day', 'End_Month', 'End_Day']);

        foreach ($rows as $r) {
            if (self::inRange(
                $month, $day,
                (int) $r->Start_Month, (int) $r->Start_Day,
                (int) $r->End_Month, (int) $r->End_Day
            )) {
                $semester = (int) $r->Semester;
                break;
            }
        }

        return ['year' => $academicYear, 'semester' => $semester];
    }

    /** Month/day comparison (m*100+d); ranges crossing the year boundary wrap. */
    private static function inRange(
        int $m, int $d,
        int $startMonth, int $startDay,
        int $endMonth, int $endDay
    ): bool {
        $pos   = fn (int $month, int $day) => $month * 100 + $day;
        $now   = $pos($m, $d);
        $start = $pos($startMonth, $startDay);
        $end   = $pos($endMonth, $endDay);

        return $start <= $end
            ? $now >= $start && $now <= $end
            : $now >= $start || $now <= $end;
    }
}
