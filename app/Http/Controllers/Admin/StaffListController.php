<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StaffListController extends Controller
{
    /**
     * Staff List page: criteria bar + AG Grid (role / target-user dropdown data).
     */
    public function index()
    {
        $roles = DB::connection('pusen')
            ->table('tblRole')
            ->orderBy('Role_Id')
            ->get();

        $targetUsers = DB::connection('pusen')
            ->table('tblTarget_User')
            ->orderBy('Target_User_Id')
            ->get();

        return view('admin.staff-list', compact('roles', 'targetUsers'));
    }

    /**
     * Search tblStaff by criteria (AND logic; text fields = partial match).
     * Empty criteria returns all staff.
     */
    public function search(Request $request)
    {
        $q = DB::connection('pusen')->table('tblStaff');

        if ($staffId = trim((string) $request->input('staff_id'))) {
            $q->where('Staff_Id', 'like', "%{$staffId}%");
        }
        if ($name = trim((string) $request->input('staff_name'))) {
            $q->where('Staff_Name', 'like', "%{$name}%");
        }
        if ($display = trim((string) $request->input('display_name'))) {
            $q->where('Staff_Display_Name', 'like', "%{$display}%");
        }
        if ($roleId = trim((string) $request->input('role_id'))) {
            $q->where('Role_Id', $roleId);
        }
        if ($targetId = trim((string) $request->input('target_user_id'))) {
            $q->where('Target_User_Id', $targetId);
        }
        if ($request->filled('status')) {
            $q->where('status', (int) $request->input('status'));
        }

        $rows = $q->orderBy('Staff_Id')
            ->get(['Id', 'Staff_Id', 'Staff_Name', 'Staff_Display_Name', 'Role_Id', 'Target_User_Id', 'status']);

        return response()->json($rows->map(fn ($r) => [
            'id'                => $r->Id,
            'staff_id'          => $r->Staff_Id,
            'staff_name'        => $r->Staff_Name,
            'staff_display_name'=> $r->Staff_Display_Name,
            'role_id'           => $r->Role_Id,
            'target_user_id'    => $r->Target_User_Id,
            'status'            => (int) $r->status,
        ]));
    }

    /**
     * Save the row's status (only Status is editable per spec).
     */
    public function updateStatus(Request $request)
    {
        $validated = $request->validate([
            'id'     => 'required|integer',
            'status' => 'required|in:0,1',
        ]);

        $updated = DB::connection('pusen')
            ->table('tblStaff')
            ->where('Id', $validated['id'])
            ->update([
                'status'     => (int) $validated['status'],
                'updated_at' => now(),
                'updated_by' => (string) (auth()->id() ?? 'system01'),
                'updated_ip' => $request->ip(),
            ]);

        if (! $updated) {
            return response()->json(['success' => false, 'message' => 'Staff not found.'], 404);
        }

        return response()->json(['success' => true]);
    }
}
