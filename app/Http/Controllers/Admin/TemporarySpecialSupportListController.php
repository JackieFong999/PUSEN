<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class TemporarySpecialSupportListController extends Controller
{
    /**
     * Show all temporary special supports from tblTemporary_Special_Support (PUSENDB).
     */
    public function index()
    {
        $supports = DB::connection('pusen')
            ->table('tblTemporary_Special_Support')
            ->orderBy('Temporary_Special_Support')
            ->get();

        return view('admin.temporary-special-support-list', ['supports' => $supports]);
    }
}
