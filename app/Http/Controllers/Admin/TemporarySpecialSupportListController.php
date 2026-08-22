<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TemporarySpecialSupportListController extends Controller
{
    /**
     * Temporary Special Support lookup page: AG Grid list (10 rows/page),
     * delete with usage check (tblSEN.Temporary_Special_Support), add below.
     */
    public function index()
    {
        $supports = DB::connection('pusen')
            ->table('tblTemporary_Special_Support')
            ->orderBy('Temporary_Special_Support')
            ->get(['Id', 'Temporary_Special_Support']);

        return view('admin.temporary-special-support-list', ['supports' => $supports]);
    }

    /**
     * Add a new temporary special support. The value must be unique
     * (case-insensitive - column is utf8mb4_unicode_ci).
     */
    public function store(Request $request)
    {
        $value = trim((string) $request->input('support'));

        if ($value === '') {
            return response()->json(['success' => false, 'message' => 'Temporary Special Support is required.'], 422);
        }
        if (mb_strlen($value) > 40) {
            return response()->json(['success' => false, 'message' => 'Temporary Special Support must be 40 characters or fewer.'], 422);
        }

        $conn = DB::connection('pusen');

        if ($conn->table('tblTemporary_Special_Support')->where('Temporary_Special_Support', $value)->exists()) {
            return response()->json(['success' => false, 'message' => 'This value already exists.'], 409);
        }

        $user = Auth::user();
        $who = $user ? $user->Staff_Id : null;

        try {
            $id = $conn->table('tblTemporary_Special_Support')->insertGetId([
                'Temporary_Special_Support' => $value,
                'created_by' => $who,
                'updated_by' => $who,
                'updated_ip' => $request->ip(),
            ]);
        } catch (\Throwable $e) {
            if ($conn->table('tblTemporary_Special_Support')->where('Temporary_Special_Support', $value)->exists()) {
                return response()->json(['success' => false, 'message' => 'This value already exists.'], 409);
            }
            report($e);
            return response()->json(['success' => false, 'message' => 'Failed to save.'], 500);
        }

        return response()->json(['success' => true, 'id' => $id, 'support' => $value]);
    }

    /**
     * Update an existing value (keyed by the auto-increment Id).
     * The new value must not collide with another row (unique column).
     */
    public function update(Request $request)
    {
        $id = (int) $request->input('id');
        $value = trim((string) $request->input('support'));

        if ($id <= 0) {
            return response()->json(['success' => false, 'message' => 'Missing Id.'], 422);
        }
        if ($value === '') {
            return response()->json(['success' => false, 'message' => 'Temporary Special Support is required.'], 422);
        }
        if (mb_strlen($value) > 40) {
            return response()->json(['success' => false, 'message' => 'Temporary Special Support must be 40 characters or fewer.'], 422);
        }

        $conn = DB::connection('pusen');

        if (! $conn->table('tblTemporary_Special_Support')->where('Id', $id)->exists()) {
            return response()->json(['success' => false, 'message' => 'Not found.'], 404);
        }
        // unique check: another row already uses this value?
        $dup = $conn->table('tblTemporary_Special_Support')
            ->where('Temporary_Special_Support', $value)
            ->where('Id', '!=', $id)
            ->exists();
        if ($dup) {
            return response()->json(['success' => false, 'message' => 'This value already exists.'], 409);
        }

        $user = Auth::user();
        $who = $user ? $user->Staff_Id : null;

        $conn->table('tblTemporary_Special_Support')->where('Id', $id)->update([
            'Temporary_Special_Support' => $value,
            'updated_by' => $who,
            'updated_ip' => $request->ip(),
        ]);

        return response()->json(['success' => true, 'id' => $id, 'support' => $value]);
    }

    /**
     * Delete a temporary special support. Blocked when it is still referenced
     * by any SEN case (tblSEN.Temporary_Special_Support_ID).
     */
    public function destroy(Request $request)
    {
        $id = (int) $request->input('id');
        if ($id <= 0) {
            return response()->json(['success' => false, 'message' => 'Missing Id.'], 422);
        }

        $conn = DB::connection('pusen');

        if (! $conn->table('tblTemporary_Special_Support')->where('Id', $id)->exists()) {
            return response()->json(['success' => false, 'message' => 'Not found.'], 404);
        }

        $used = $conn->table('tblSEN')->where('Temporary_Special_Support_ID', $id)->count();
        if ($used > 0) {
            return response()->json([
                'success' => false,
                'message' => "Cannot delete: used by {$used} SEN case(s).",
            ], 409);
        }

        $conn->table('tblTemporary_Special_Support')->where('Id', $id)->delete();

        return response()->json(['success' => true]);
    }
}
