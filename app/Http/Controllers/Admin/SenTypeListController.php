<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class SenTypeListController extends Controller
{
    /**
     * Show all SEN types from tblSEN_Type (PUSENDB).
     */
    public function index()
    {
        $senTypes = DB::connection('pusen')
            ->table('tblSEN_Type')
            ->orderBy('SEN_Type')
            ->get();

        return view('admin.sen-type-list', ['senTypes' => $senTypes]);
    }
}
