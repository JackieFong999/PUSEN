<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class FundTypeListController extends Controller
{
    /**
     * Show all fund types from tblFund_Type (PUSENDB).
     */
    public function index()
    {
        $fundTypes = DB::connection('pusen')
            ->table('tblFund_Type')
            ->orderBy('Fund_Type_Code')
            ->get();

        return view('admin.fund-type-list', ['fundTypes' => $fundTypes]);
    }
}
