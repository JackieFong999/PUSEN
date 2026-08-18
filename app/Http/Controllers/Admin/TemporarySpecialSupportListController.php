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
            ->pluck('Temporary_Special_Support');

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
            $conn->table('tblTemporary_Special_Support')->insert([
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

        return response()->json(['success' => true, 'support' => $value]);
    }

    /**
     * Delete a temporary special support. Blocked when it is still referenced
     * by any SEN case (tblSEN.Temporary_Special_Support).
     */
    public function destroy(Request $request)
    {
        $value = trim((string) $request->input('support'));
        if ($value === '') {
            return response()->json(['success' => false, 'message' => 'Missing value.'], 422);
        }

        $conn = DB::connection('pusen');

        if (! $conn->table('tblTemporary_Special_Support')->where('Temporary_Special_Support', $value)->exists()) {
            return response()->json(['success' => false, 'message' => 'Not found.'], 404);
        }

        $used = $conn->table('tblSEN')->where('Temporary_Special_Support', $value)->count();
        if ($used > 0) {
            return response()->json([
                'success' => false,
                'message' => "Cannot delete: used by {$used} SEN case(s).",
            ], 409);
        }

        $conn->table('tblTemporary_Special_Support')->where('Temporary_Special_Support', $value)->delete();

        return response()->json(['success' => true]);
    }
}
