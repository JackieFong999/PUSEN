<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class TargetUserListController extends Controller
{
    /**
     * Show all target users from tblTarget_User (PUSENDB).
     */
    public function index()
    {
        $targetUsers = DB::connection('pusen')
            ->table('tblTarget_User')
            ->orderBy('Target_User_Id')
            ->get();

        return view('admin.target-user-list', ['targetUsers' => $targetUsers]);
    }
}
