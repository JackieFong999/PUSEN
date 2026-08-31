<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\StudentHousekeepingService;
use App\Services\StudentNameEncryption;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Housekeeping page (SA only — enforced by nav config + CheckRoleAccess).
 * Currently hosts the "Housekeeping for Student" function.
 */
class HousekeepingController extends Controller
{
    public function __construct(private StudentHousekeepingService $service)
    {
    }

    /**
     * Housekeeping screen: student housekeeping button + run log (AG Grid).
     */
    public function index()
    {
        // distinct Student_Id values from the HK log -> autocomplete datalist
        $studentIds = DB::connection('pusen')
            ->table('tblHK_Student_Log')
            ->distinct()
            ->orderBy('Student_Id')
            ->pluck('Student_Id');

        return view('admin.housekeeping', compact('studentIds'));
    }

    /**
     * JSON search for the housekeeping run log (AG Grid).
     * Filters: Student_Id (LIKE) + Delete_At date range (HK-local dates;
     * Delete_At is stored UTC, so the range is converted to UTC).
     */
    public function searchRuns(Request $request)
    {
        $conn = DB::connection('pusen');

        $q = $conn->table('tblHK_Student_Log');

        $studentId = trim((string) $request->input('student_id'));
        if ($studentId !== '') {
            $q->where('Student_Id', 'like', '%'.$studentId.'%');
        }

        $from = trim((string) $request->input('delete_at_from'));
        $to   = trim((string) $request->input('delete_at_to'));
        if ($from !== '') {
            $q->where('Delete_At', '>=', \Carbon\Carbon::parse($from, 'Asia/Hong_Kong')->startOfDay()->utc());
        }
        if ($to !== '') {
            $q->where('Delete_At', '<=', \Carbon\Carbon::parse($to, 'Asia/Hong_Kong')->endOfDay()->utc());
        }

        $runs = $q->orderByDesc('Id')->get();
        $ids  = $runs->pluck('Id')->all();

        $senCounts = $ids
            ? $conn->table('tblHK_SEN_Log')
                ->selectRaw('HK_Run_Id, COUNT(*) AS sen_count')
                ->whereIn('HK_Run_Id', $ids)
                ->groupBy('HK_Run_Id')
                ->pluck('sen_count', 'HK_Run_Id')
            : collect();

        $docCounts = $ids
            ? $conn->table('tblHK_SEN_Doc_Log')
                ->selectRaw('HK_Run_Id, COUNT(*) AS doc_count')
                ->whereIn('HK_Run_Id', $ids)
                ->groupBy('HK_Run_Id')
                ->pluck('doc_count', 'HK_Run_Id')
            : collect();

        return response()->json($runs->map(fn ($r) => [
            'id'             => (int) $r->Id,
            'student_id'     => $r->Student_Id,
            'name_eng'       => StudentNameEncryption::decrypt($r->Student_Name_Eng),
            'name_chn'       => StudentNameEncryption::decrypt($r->Student_Name_Chn),
            'student_status' => $r->Student_Status,
            'sen_count'      => (int) ($senCounts[$r->Id] ?? 0),
            'doc_count'      => (int) ($docCounts[$r->Id] ?? 0),
            'delete_at_hk'   => $r->Delete_At
                ? \Carbon\Carbon::parse($r->Delete_At, 'UTC')->setTimezone('Asia/Hong_Kong')->format('Y-m-d H:i:s')
                : null,
            'delete_by'      => $r->Delete_By,
            'remarks'        => $r->Remarks,
        ]));
    }

    /**
     * Preview: how many students would be processed (no changes).
     */
    public function previewStudent()
    {
        return response()->json(['success' => true, ...$this->service->preview()]);
    }

    /**
     * Run housekeeping for all qualifying students.
     * Guarded by an atomic cache flag against concurrent runs.
     */
    public function runStudent()
    {
        if (! Cache::add('hk.student.run.lock', true, 600)) {
            return response()->json([
                'success' => false,
                'message' => 'A housekeeping run is already in progress. Please wait.',
            ], 409);
        }

        try {
            $summary = $this->service->run();

            return response()->json(['success' => true, ...$summary]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Housekeeping failed: '.$e->getMessage(),
            ], 500);
        } finally {
            Cache::forget('hk.student.run.lock');
        }
    }
}
