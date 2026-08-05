<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class RoleListController extends Controller
{
    /**
     * Show all roles from tblRole (PUSENDB).
     */
    public function index()
    {
        $roles = DB::connection('pusen')
            ->table('tblRole')
            ->orderBy('Role_Id')
            ->get();

        return view('admin.role-list', ['roles' => $roles]);
    }
}
