<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class SubjectTypeListController extends Controller
{
    /**
     * Show all subject types from tblSubject_Type (PUSENDB).
     */
    public function index()
    {
        $subjectTypes = DB::connection('pusen')
            ->table('tblSubject_Type')
            ->orderBy('Subject_Type')
            ->get();

        return view('admin.subject-type-list', ['subjectTypes' => $subjectTypes]);
    }
}
