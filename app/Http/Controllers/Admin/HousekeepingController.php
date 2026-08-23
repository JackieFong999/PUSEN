<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\StudentHousekeepingService;
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
     * Housekeeping screen: student housekeeping button + recent runs.
     */
    public function index()
    {
        $conn = DB::connection('pusen');

        $runs = $conn->table('tblHK_Student_Log')->orderByDesc('Id')->limit(20)->get();

        $senCounts = $conn->table('tblHK_SEN_Log')
            ->selectRaw('HK_Run_Id, COUNT(*) AS sen_count')
            ->groupBy('HK_Run_Id')
            ->pluck('sen_count', 'HK_Run_Id');

        $docCounts = $conn->table('tblHK_SEN_Doc_Log')
            ->selectRaw('HK_Run_Id, COUNT(*) AS doc_count')
            ->groupBy('HK_Run_Id')
            ->pluck('doc_count', 'HK_Run_Id');

        return view('admin.housekeeping', compact('runs', 'senCounts', 'docCounts'));
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
