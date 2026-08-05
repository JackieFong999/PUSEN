<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class StudentRegistrationListController extends Controller
{
    /**
     * Show all student registrations from tblStudent_Reg (PUSENDB).
     */
    public function index()
    {
        $registrations = DB::connection('pusen')
            ->table('tblStudent_Reg')
            ->orderBy('Student_Id')
            ->orderBy('Subject_Code')
            ->get();

        return view('admin.student-registration-list', ['registrations' => $registrations]);
    }
}
