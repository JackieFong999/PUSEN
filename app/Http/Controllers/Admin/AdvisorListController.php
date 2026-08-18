<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdvisorListController extends Controller
{
    /**
     * Advisor List for the Student page: AG Grid (10 rows/page) with a criteria
     * bar (Advisor ID autocomplete from tblStaff, Student ID autocomplete from
     * tblStudent, Advisor Type select from tblAdvisor_Type).
     */
    public function index()
    {
        $conn = DB::connection('pusen');

        $rows = $conn->table('tblAdvisor_Student')
            ->orderBy('Advisor_Id')
            ->orderBy('Student_Id')
            ->get(['Advisor_Id', 'Student_Id', 'Advisor_Type', 'Start_Date', 'End_Date'])
            ->map(fn ($r) => $this->row($r))
            ->values();

        $options = [
            'advisorIds'  => $conn->table('tblStaff')->distinct()->orderBy('Staff_Id')->pluck('Staff_Id'),
            'studentIds'  => $conn->table('tblStudent')->distinct()->orderBy('Student_Id')->pluck('Student_Id'),
            'advisorTypes'=> $conn->table('tblAdvisor_Type')->orderBy('Advisor_Type')->pluck('Advisor_Type'),
        ];

        return view('admin.advisor-list', ['rows' => $rows, 'options' => $options]);
    }

    /**
     * Filtered advisor-student rows (JSON) for the AG Grid.
     */
    public function search(Request $request)
    {
        $q = DB::connection('pusen')->table('tblAdvisor_Student');

        $advisor = trim((string) $request->input('advisor_id'));
        if ($advisor !== '') {
            $q->where('Advisor_Id', 'like', '%'.$advisor.'%');
        }

        $sid = trim((string) $request->input('student_id'));
        if ($sid !== '') {
            $q->where('Student_Id', 'like', '%'.$sid.'%');
        }

        if ($request->filled('advisor_type')) {
            $q->where('Advisor_Type', $request->input('advisor_type'));
        }

        $rows = $q->orderBy('Advisor_Id')
            ->orderBy('Student_Id')
            ->get(['Advisor_Id', 'Student_Id', 'Advisor_Type', 'Start_Date', 'End_Date'])
            ->map(fn ($r) => $this->row($r))
            ->values();

        return response()->json($rows);
    }

    private function row($r)
    {
        return [
            'advisorId'   => $r->Advisor_Id,
            'studentId'   => $r->Student_Id,
            'advisorType' => $r->Advisor_Type,
            'startDate'   => $r->Start_Date,
            'endDate'     => $r->End_Date,
        ];
    }
}
