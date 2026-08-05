<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class AcademicYearSemesterListController extends Controller
{
    /**
     * Show all academic year/semesters from tblAcademicYear_Semester (PUSENDB).
     */
    public function index()
    {
        $years = DB::connection('pusen')
            ->table('tblAcademicYear_Semester')
            ->orderBy('Year_Semester_Code')
            ->get();

        return view('admin.academic-year-semester-list', ['years' => $years]);
    }
}
