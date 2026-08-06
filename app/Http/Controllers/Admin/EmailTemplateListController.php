<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmailTemplateListController extends Controller
{
    /**
     * Email Template page: AG Grid + Add/Edit form (data loaded via /data).
     */
    public function index()
    {
        return view('admin.email-template-list');
    }

    /**
     * Grid data: all email templates, ordered by Template_Name.
     */
    public function data()
    {
        $rows = DB::connection('pusen')
            ->table('tblEmail_Template')
            ->orderBy('Template_Name')
            ->get();

        return response()->json($rows->map(fn ($r) => [
            'id'                => $r->Id,
            'template_name'     => $r->Template_Name,
            'template_title'    => $r->Template_Title,
            'template_content'  => $r->Template_Content,
            'template_remarks'  => $r->Template_Remarks,
        ]));
    }

    /**
     * Save: create (no id) or update (id present).
     * Template_Name is immutable on update (disabled in the form).
     */
    public function save(Request $request)
    {
        $conn = DB::connection('pusen');

        $id = (int) $request->input('id', 0);
        $isEdit = $id > 0;

        if ($isEdit) {
            $validated = $request->validate([
                'template_title'   => 'required|string|max:100',
                'template_content' => 'required|string',
            ]);
            $exists = $conn->table('tblEmail_Template')->where('Id', $id)->exists();
            if (! $exists) {
                return response()->json(['success' => false, 'message' => 'Record not found.'], 404);
            }
            $conn->table('tblEmail_Template')->where('Id', $id)->update([
                'Template_Title'   => trim($validated['template_title']),
                'Template_Content' => trim($validated['template_content']),
                'Template_Remarks' => $this->nullable($request->input('template_remarks')),
                'updated_at'       => now(),
                'updated_by'       => 'system01',
                'updated_ip'       => $request->ip(),
            ]);
            return response()->json(['success' => true, 'id' => $id, 'mode' => 'update']);
        }

        $validated = $request->validate([
            'template_name'    => 'required|string|max:50',
            'template_title'   => 'required|string|max:100',
            'template_content' => 'required|string',
        ]);

        // duplicate name guard (name is the identity — unique-ish)
        $dup = $conn->table('tblEmail_Template')
            ->where('Template_Name', trim($validated['template_name']))
            ->exists();
        if ($dup) {
            return response()->json(['success' => false, 'message' => 'Template Name already exists.'], 422);
        }

        $newId = $conn->table('tblEmail_Template')->insertGetId([
            'Template_Name'    => trim($validated['template_name']),
            'Template_Title'   => trim($validated['template_title']),
            'Template_Content' => trim($validated['template_content']),
            'Template_Remarks' => $this->nullable($request->input('template_remarks')),
            'created_at'       => now(),
            'created_by'       => 'system01',
            'updated_at'       => now(),
            'updated_by'       => 'system01',
            'updated_ip'       => $request->ip(),
        ]);

        return response()->json(['success' => true, 'id' => $newId, 'mode' => 'create']);
    }

    private function nullable($value): ?string
    {
        $v = trim((string) $value);
        return $v === '' ? null : $v;
    }
}
