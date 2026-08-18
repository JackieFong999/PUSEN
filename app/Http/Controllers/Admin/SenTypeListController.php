<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SenTypeListController extends Controller
{
    /**
     * SEN Type lookup page: AG Grid list (10 rows/page), delete with usage
     * check, add-new-entry form below the list.
     */
    public function index()
    {
        $senTypes = DB::connection('pusen')
            ->table('tblSEN_Type')
            ->orderBy('SEN_Type')
            ->pluck('SEN_Type');

        return view('admin.sen-type-list', ['senTypes' => $senTypes]);
    }

    /**
     * Add a new SEN type. The code must be unique (case-insensitive -
     * the column is utf8mb4_unicode_ci, so 'adhd' and 'ADHD' are duplicates).
     */
    public function store(Request $request)
    {
        $type = trim((string) $request->input('sen_type'));

        if ($type === '') {
            return response()->json(['success' => false, 'message' => 'SEN Type is required.'], 422);
        }
        if (mb_strlen($type) > 60) {
            return response()->json(['success' => false, 'message' => 'SEN Type must be 60 characters or fewer.'], 422);
        }

        $conn = DB::connection('pusen');

        if ($conn->table('tblSEN_Type')->where('SEN_Type', $type)->exists()) {
            return response()->json(['success' => false, 'message' => 'This SEN Type already exists.'], 409);
        }

        try {
            $conn->table('tblSEN_Type')->insert(['SEN_Type' => $type]);
        } catch (\Throwable $e) {
            // PK race: another request inserted the same value in between
            if ($conn->table('tblSEN_Type')->where('SEN_Type', $type)->exists()) {
                return response()->json(['success' => false, 'message' => 'This SEN Type already exists.'], 409);
            }
            report($e);
            return response()->json(['success' => false, 'message' => 'Failed to save SEN Type.'], 500);
        }

        return response()->json(['success' => true, 'sen_type' => $type]);
    }

    /**
     * Delete a SEN type. Blocked when it is still referenced by any SEN case
     * (tblSEN.SEN_Type) - the user is told how many cases use it.
     */
    public function destroy(Request $request)
    {
        $type = trim((string) $request->input('sen_type'));
        if ($type === '') {
            return response()->json(['success' => false, 'message' => 'Missing SEN Type.'], 422);
        }

        $conn = DB::connection('pusen');

        if (! $conn->table('tblSEN_Type')->where('SEN_Type', $type)->exists()) {
            return response()->json(['success' => false, 'message' => 'SEN Type not found.'], 404);
        }

        $used = $conn->table('tblSEN')->where('SEN_Type', $type)->count();
        if ($used > 0) {
            return response()->json([
                'success' => false,
                'message' => "Cannot delete: used by {$used} SEN case(s).",
            ], 409);
        }

        $conn->table('tblSEN_Type')->where('SEN_Type', $type)->delete();

        return response()->json(['success' => true]);
    }
}
