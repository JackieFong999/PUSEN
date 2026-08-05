<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class StudentStatusListController extends Controller
{
    /**
     * Show all student statuses from tblStudent_Status (PUSENDB).
     */
    public function index()
    {
        $statuses = DB::connection('pusen')
            ->table('tblStudent_Status')
            ->orderBy('Student_Status')
            ->get();

        return view('admin.student-status-list', ['statuses' => $statuses]);
    }
}
