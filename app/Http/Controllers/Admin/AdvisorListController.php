<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class AdvisorListController extends Controller
{
    /**
     * Show all advisor-student assignments from tblAdvisor_Student (PUSENDB).
     */
    public function index()
    {
        $advisors = DB::connection('pusen')
            ->table('tblAdvisor_Student')
            ->orderBy('Advisor_Id')
            ->orderBy('Student_Id')
            ->get();

        return view('admin.advisor-list', ['advisors' => $advisors]);
    }
}
