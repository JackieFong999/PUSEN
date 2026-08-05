<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class AdvisorTypeListController extends Controller
{
    /**
     * Show all advisor types from tblAdvisor_Type (PUSENDB).
     */
    public function index()
    {
        $advisorTypes = DB::connection('pusen')
            ->table('tblAdvisor_Type')
            ->orderBy('Advisor_Type')
            ->get();

        return view('admin.advisor-type-list', ['advisorTypes' => $advisorTypes]);
    }
}
