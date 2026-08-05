<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class SubjectListController extends Controller
{
    /**
     * Show all subject/lecture assignments from tblSubject (PUSENDB).
     */
    public function index()
    {
        $subjects = DB::connection('pusen')
            ->table('tblSubject')
            ->orderBy('Academic_Year')
            ->orderBy('Semester')
            ->orderBy('Subject_Code')
            ->get();

        return view('admin.subject-list', ['subjects' => $subjects]);
    }
}
