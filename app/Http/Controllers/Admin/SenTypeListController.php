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
            ->get(['Id', 'SEN_Type']);

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
            $id = $conn->table('tblSEN_Type')->insertGetId(['SEN_Type' => $type]);
        } catch (\Throwable $e) {
            // unique-key race: another request inserted the same value in between
            if ($conn->table('tblSEN_Type')->where('SEN_Type', $type)->exists()) {
                return response()->json(['success' => false, 'message' => 'This SEN Type already exists.'], 409);
            }
            report($e);
            return response()->json(['success' => false, 'message' => 'Failed to save SEN Type.'], 500);
        }

        return response()->json(['success' => true, 'id' => $id, 'sen_type' => $type]);
    }

    /**
     * Update an existing SEN type (keyed by the auto-increment Id).
     * The new value must not collide with another row (unique SEN_Type).
     */
    public function update(Request $request)
    {
        $id = (int) $request->input('id');
        $type = trim((string) $request->input('sen_type'));

        if ($id <= 0) {
            return response()->json(['success' => false, 'message' => 'Missing SEN Type Id.'], 422);
        }
        if ($type === '') {
            return response()->json(['success' => false, 'message' => 'SEN Type is required.'], 422);
        }
        if (mb_strlen($type) > 60) {
            return response()->json(['success' => false, 'message' => 'SEN Type must be 60 characters or fewer.'], 422);
        }

        $conn = DB::connection('pusen');

        if (! $conn->table('tblSEN_Type')->where('Id', $id)->exists()) {
            return response()->json(['success' => false, 'message' => 'SEN Type not found.'], 404);
        }
        // unique check: another row already uses this value?
        $dup = $conn->table('tblSEN_Type')
            ->where('SEN_Type', $type)
            ->where('Id', '!=', $id)
            ->exists();
        if ($dup) {
            return response()->json(['success' => false, 'message' => 'This SEN Type already exists.'], 409);
        }

        $conn->table('tblSEN_Type')->where('Id', $id)->update([
            'SEN_Type' => $type,
        ]);

        return response()->json(['success' => true, 'id' => $id, 'sen_type' => $type]);
    }

    /**
     * Delete a SEN type. Blocked when it is still referenced by any SEN case
     * (tblSEN.SEN_Type_ID) - the user is told how many cases use it.
     */
    public function destroy(Request $request)
    {
        $id = (int) $request->input('id');
        if ($id <= 0) {
            return response()->json(['success' => false, 'message' => 'Missing SEN Type Id.'], 422);
        }

        $conn = DB::connection('pusen');

        $row = $conn->table('tblSEN_Type')->where('Id', $id)->first();
        if (! $row) {
            return response()->json(['success' => false, 'message' => 'SEN Type not found.'], 404);
        }

        $used = $conn->table('tblSEN')->where('SEN_Type_ID', $id)->count();
        if ($used > 0) {
            return response()->json([
                'success' => false,
                'message' => "Cannot delete: used by {$used} SEN case(s).",
            ], 409);
        }

        $conn->table('tblSEN_Type')->where('Id', $id)->delete();

        return response()->json(['success' => true]);
    }
}
