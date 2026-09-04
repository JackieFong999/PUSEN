@extends('layouts.app')

@section('content')

@php
    // label for a staff autocomplete prefill (id + name), falls back to the id
    $staffLabelOf = function (string $key, ?string $id): string {
        if (! $id) return '';
        $s = collect($staff[$key] ?? [])->firstWhere('Staff_Id', $id);
        return $s ? trim($s->Staff_Id . ' — ' . $s->Staff_Name) : $id;
    };
@endphp

<style>
  .form-label { font-size: .78rem; font-weight: 600; color: var(--text-muted); margin-bottom: .35rem; }
  .form-control, .form-select, .form-check-input {
    background: var(--bg-soft);
    border: 1px solid var(--border);
    color: var(--text);
    font-size: .85rem;
    border-radius: 9px;
  }
  .form-control:focus, .form-select:focus {
    border-color: rgba(var(--accent-rgb), .5);
    box-shadow: 0 0 0 3px rgba(var(--accent-rgb), .15);
    background: var(--bg-soft);
    color: var(--text);
  }
  .form-control[readonly] { background: color-mix(in srgb, var(--bg-soft) 55%, transparent); cursor: default; }

  /* send-email checkbox: bigger + explicit white tick when checked */
  #sendEmailChk.form-check-input {
    width: 1.35rem;
    height: 1.35rem;
    margin-top: 0;
    margin-left: 0; /* override Bootstrap's -1.5em (no .form-check padding-left here) */
    cursor: pointer;
  }
  #sendEmailChk.form-check-input:checked {
    background-color: var(--accent);
    border-color: var(--accent);
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23fff' stroke-linecap='round' stroke-linejoin='round' stroke-width='2.5' d='M3.5 8.5l3 3 6-6'/%3e%3c/svg%3e");
    background-size: 1.05em;
    background-position: center;
    background-repeat: no-repeat;
  }
  #sendEmailChk.form-check-input:disabled {
    opacity: .45;
    cursor: not-allowed;
  }
  #sendEmailChk + .form-check-label {
    font-size: .95rem;
    cursor: pointer;
    user-select: none;
    margin-left: .5rem;
  }

  /* display-only fields: gray to distinguish from editable fields */
  .form-control.display-only, .form-select.display-only {
    background: color-mix(in srgb, var(--text-muted) 16%, var(--card-bg));
    color: var(--text-muted);
    border-color: var(--border);
    border-style: dashed;
    cursor: default;
  }
  .form-control.display-only:focus, .form-select.display-only:focus {
    box-shadow: none;
    border-color: var(--border);
    background: color-mix(in srgb, var(--text-muted) 16%, var(--card-bg));
  }

  /* dark mode: editable fields -> white bg + black text (display-only stays gray) */
  [data-bs-theme="dark"] .form-control:not(.display-only),
  [data-bs-theme="dark"] .form-select:not(.display-only) {
    background: #ffffff;
    color: #111111;
    border-color: #c9cfdc;
  }
  [data-bs-theme="dark"] .form-control:not(.display-only)::placeholder {
    color: #8b93a7;
  }
  [data-bs-theme="dark"] .form-control:not(.display-only):focus,
  [data-bs-theme="dark"] .form-select:not(.display-only):focus {
    background: #ffffff;
    color: #111111;
    border-color: rgba(var(--accent-rgb), .6);
    box-shadow: 0 0 0 3px rgba(var(--accent-rgb), .15);
  }
  [data-bs-theme="dark"] .form-select:not(.display-only) option {
    background: #ffffff;
    color: #111111;
  }
  [data-bs-theme="dark"] .form-control:not(.display-only):disabled,
  [data-bs-theme="dark"] .form-select:not(.display-only):disabled {
    opacity: .55;
    cursor: not-allowed;
  }
  .form-select option { background: var(--card-bg); color: var(--text); }

  /* Student Id autocomplete dropdown */
  .student-autocomplete {
    position: absolute;
    top: calc(100% + 4px);
    left: 0; right: 0;
    z-index: 1060;
    max-height: 260px;
    overflow-y: auto;
    background: var(--card-bg);
    border: 1px solid var(--card-border);
    border-radius: 9px;
    box-shadow: 0 8px 24px rgba(0,0,0,.14);
    font-size: .85rem;
  }
  .student-autocomplete .ac-item {
    padding: .5rem .8rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: .6rem;
    color: var(--text);
    white-space: nowrap;
    overflow: hidden;
  }
  .student-autocomplete .ac-item:hover,
  .student-autocomplete .ac-item.active { background: var(--accent-soft); }
  .student-autocomplete .ac-item .ac-id { font-weight: 600; flex-shrink: 0; }
  .student-autocomplete .ac-item .ac-name {
    color: var(--text-muted);
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .student-autocomplete .ac-empty {
    padding: .6rem .8rem;
    color: var(--text-muted);
    font-style: italic;
  }

  .btn-search {
    background: #9B2331;
    color: #fff; font-weight: 600; font-size: .85rem;
    border: 1px solid #7d1d29; border-radius: 10px; padding: .5rem 1.2rem;
    box-shadow: 0 4px 14px rgba(155, 35, 49, .3);
  }
  .btn-search:hover { background: #d04553; border-color: #a02d38; color: #fff; }
  .btn-search i { color: #fff; }

  .btn-cancel { border: 1px solid #7d1d29; color: #fff; background: #9B2331; font-size: .85rem; font-weight: 600; border-radius: 10px; padding: .5rem 1.2rem; }
  .btn-cancel:hover { background: #d04553; border-color: #a02d38; color: #fff; }
  .btn-cancel i { color: #fff; }
  .btn-cancel:hover i { color: #fff; }

  .btn-create { background: #9B2331; color: #fff; font-weight: 600; font-size: .85rem; border: 1px solid #7d1d29; border-radius: 10px; padding: .5rem 1.2rem; box-shadow: 0 4px 14px rgba(155, 35, 49, .3); }
  .btn-create:hover { background: #d04553; border-color: #a02d38; color: #fff; }
  .btn-create i { color: #fff; }

  .form-card { border: 1px solid var(--card-border); border-radius: var(--radius); background: var(--card-bg); }
  .form-card .card-head {
    font-size: .72rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase;
    color: var(--text-faint); border-bottom: 1px solid var(--card-border); padding: .7rem 1.1rem;
  }
  .form-card .card-body { padding: 1.1rem; }

  .display-list { min-height: 90px; }
  .display-list:disabled { opacity: .75; }
  .display-list option { padding: .25rem .5rem; }

  /* document list table (per-row View / X buttons) */
  #docTable tbody td {
    border-color: var(--border);
    padding: .45rem .9rem;
    font-size: .85rem; color: var(--text);
    vertical-align: middle;
    word-break: break-all;
  }
  #docTable .row-btn {
    width: 30px; height: 30px; font-size: .9rem;
    border: 1px solid rgba(var(--accent-rgb), .4); border-radius: 8px;
    background: var(--accent-soft); color: var(--accent); /* eye = blue, tinted like hover */
    display: inline-grid; place-items: center;
    transition: background .15s, border-color .15s;
  }
  #docTable .row-btn i {
    -webkit-text-stroke: 1px currentColor; /* thicken glyph strokes */
    transition: transform .15s;
  }
  #docTable .row-btn:hover i { transform: scale(1.18); } /* on hover only the icon grows */
  #docTable .row-btn:disabled { opacity: .45; cursor: not-allowed; }
  #docTable .row-btn.btn-x { border-color: rgba(248,113,113,.4); background: rgba(248,113,113,.08); color: var(--danger); } /* X = red, tinted like hover */
  #docTable .row-btn.btn-dl { border-color: rgba(21,128,61,.45); background: rgba(21,128,61,.1); color: #15803d; } /* download = dark green, tinted like hover */
  #docTable .doc-name-text { flex: 0 1 auto; min-width: 0; word-break: break-all; }

  .modal-content { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 14px; }
  .modal-content .modal-title { font-size: 1rem; font-weight: 600; color: var(--text); }
  .modal-content .modal-body { font-size: .88rem; color: var(--text); }

  /* save-preview summary table */
  .preview-table td {
    padding: .45rem .7rem;
    font-size: .85rem;
    color: var(--text);
    border-color: var(--border);
    vertical-align: top;
  }
  .preview-table td.pkey {
    width: 34%;
    font-weight: 600;
    color: var(--text-muted);
    white-space: nowrap;
  }
  .preview-table td:last-child { white-space: pre-line; }
  .preview-docs { margin-top: .5rem; }
  .preview-docs .preview-docs-label {
    font-size: .72rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase;
    color: var(--text-faint); margin-bottom: .35rem;
  }
  .preview-doc {
    display: inline-flex; align-items: center; gap: .35rem;
    font-size: .8rem; color: var(--text);
    background: var(--bg-soft); border: 1px solid var(--border);
    border-radius: 8px; padding: .3rem .6rem; margin: 0 .35rem .35rem 0;
  }
  .preview-doc i { color: var(--accent); }
  /* email banner inside the save-preview dialog */
  .preview-email-box {
    background: rgba(var(--accent-rgb, 13, 110, 253), .08);
    border: 1px solid var(--accent);
    border-radius: 10px;
    padding: .6rem .8rem;
    margin-bottom: .7rem;
  }
  .preview-email-title {
    font-size: .8rem; font-weight: 700; color: var(--accent);
    margin-bottom: .35rem;
  }
  .preview-email-list {
    font-size: .82rem; color: var(--text); line-height: 1.55;
  }
  .preview-email-list .pkey { font-weight: 700; color: var(--text-muted); }
</style>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-3" style="margin-top:-1.5rem; margin-bottom:.75rem;">
  <div>
    <h1 class="mb-0" style="font-size:1.25rem;">{{ $isView ? 'View SEN' : ($isEdit ? 'Edit SEN' : 'Create SEN') }}</h1>
  </div>
</div>

{{-- ============ CREATE BUTTON (hidden in edit/view mode) ============ --}}
@if (! $isEdit && ! $isView)
  <div class="mb-3">
    <button type="button" id="createCaseBtn" class="btn btn-create"><i class="bi bi-plus-lg me-1"></i>Create SEN Case</button>
  </div>
@endif

{{-- ============ FORM ============ --}}
<form id="senForm" autocomplete="off">

  <div class="form-card mb-3">
    <div class="card-head">SEN Case</div>
    <div class="card-body">
      <div class="row g-3 align-items-end">
        <div class="col-md-3">
          <label class="form-label" for="fSenId">SEN Id</label>
          <input type="text" class="form-control display-only" id="fSenId" value="{{ $isEdit ? $editSen->SEN_Id : $nextSenId }}" readonly disabled>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="fStudentId">Student Id <span class="text-danger">*</span></label>
          <div class="position-relative">
            <input type="text" class="form-control" id="fStudentId" name="student_id"
                   placeholder="Type Student Id to search…" autocomplete="off"
                   value="{{ $isEdit ? $editSen->Student_Id : '' }}" disabled>
            <div class="student-autocomplete" id="studentAutocomplete" style="display:none;"></div>
          </div>
        </div>
      </div>

      <div class="row g-3 mt-1">
        <div class="col-md-4">
          <label class="form-label" for="fPL">Programme Leader</label>
          <textarea class="form-control display-only" id="fPL" rows="3" readonly disabled>{{ $isEdit ? implode("\n", $editPlLabels ?? []) : '' }}</textarea>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="fDA">Department Admin Staff</label>
          <div class="position-relative">
            <input type="text" class="form-control" id="fDA" placeholder="Type Staff Id / name to search…" autocomplete="off"
                   value="{{ $isEdit ? $staffLabelOf('department_admin_staff', $editSen->Department_Admin_Staff) : '' }}" disabled>
            <input type="hidden" name="department_admin_staff" id="fDA_h" value="{{ $isEdit ? $editSen->Department_Admin_Staff : '' }}">
            <div class="student-autocomplete" id="fDA_ac" style="display:none;"></div>
          </div>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="fC">Counsellor</label>
          <div class="position-relative">
            <input type="text" class="form-control" id="fC" placeholder="Type Staff Id / name to search…" autocomplete="off"
                   value="{{ $isEdit ? $staffLabelOf('counsellor', $editSen->Counsellor) : '' }}" disabled>
            <input type="hidden" name="counsellor" id="fC_h" value="{{ $isEdit ? $editSen->Counsellor : '' }}">
            <div class="student-autocomplete" id="fC_ac" style="display:none;"></div>
          </div>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="fUSSO">Undergraduate Studies Support Officer</label>
          <div class="position-relative">
            <input type="text" class="form-control" id="fUSSO" placeholder="Type Staff Id / name to search…" autocomplete="off"
                   value="{{ $isEdit ? $staffLabelOf('undergraduate_studies_support_officer', $editSen->Undergraduate_Studies_Support_Officer) : '' }}" disabled>
            <input type="hidden" name="undergraduate_studies_support_officer" id="fUSSO_h" value="{{ $isEdit ? $editSen->Undergraduate_Studies_Support_Officer : '' }}">
            <div class="student-autocomplete" id="fUSSO_ac" style="display:none;"></div>
          </div>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="dTeachers">Subject Teacher</label>
          <textarea class="form-control display-only" id="dTeachers" rows="4" readonly disabled></textarea>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="dAdvisors">Academic Advisor</label>
          <textarea class="form-control display-only" id="dAdvisors" rows="4" readonly disabled></textarea>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="fSenType">SEN Type</label>
          <select class="form-select" id="fSenType" name="sen_type" disabled>
            <option value="">-- Select --</option>
            @foreach ($senTypes as $t)
              <option value="{{ $t->Id }}" @selected($isEdit && $editSen->SEN_Type_ID === $t->Id)>{{ $t->SEN_Type }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-12">
          <label class="form-label" for="fDetail">SEN Detail</label>
          <textarea class="form-control" id="fDetail" name="sen_detail" rows="2" disabled>{{ $isEdit ? $editSen->SEN_Detail : '' }}</textarea>
        </div>
        <div class="col-12">
          <label class="form-label" for="fSupport">Special Support Required</label>
          <textarea class="form-control" id="fSupport" name="special_support_required" rows="2" disabled>{{ $isEdit ? $editSen->Special_Support_Required : '' }}</textarea>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="fExam">Special Examination Arrangement</label>
          <textarea class="form-control" id="fExam" name="special_examination_arrangement" rows="2" disabled>{{ $isEdit ? $editSen->Special_Examination_Arrangement : '' }}</textarea>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="fTemp">Temporary Special Support</label>
          <select class="form-select" id="fTemp" name="temporary_special_support" disabled>
            <option value="">-- Select --</option>
            @foreach ($tempSupports as $t)
              <option value="{{ $t->Id }}" @selected($isEdit && $editSen->Temporary_Special_Support_ID === $t->Id)>{{ $t->Temporary_Special_Support }}</option>
            @endforeach
          </select>
        </div>
      </div>
    </div>
  </div>

  {{-- ============ DOCUMENT UPLOAD ============ --}}
  <div class="form-card mb-3">
    <div class="card-head">Documents @if (! $isView)<span class="text-muted" style="text-transform:none;letter-spacing:0;">(upload)</span>@endif <span class="badge-soft" id="docCountLabel">0 / 20</span></div>
    <div class="card-body">
      @if (! $isView)
      <div class="d-flex align-items-center gap-2 mb-2">
        <button type="button" id="chooseFilesBtn" class="btn btn-cancel"><i class="bi bi-paperclip me-1"></i>Choose Files</button>
        <span class="text-muted" style="font-size:.75rem;">Any file except executables (.exe, .js, etc.) &middot; max 10 MB each</span>
      </div>
      <input type="file" id="docFileInput" multiple hidden>
      @endif
      <div class="table-responsive" style="max-height:230px; overflow-y:auto;">
        <table class="table table-hover align-middle mb-0" id="docTable" style="min-width:460px;">
          <tbody id="docTableBody">
            <tr><td colspan="3" class="text-muted" style="font-size:.85rem;">— none —</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  {{-- ============ STUDENT INFO ============ --}}
  <div class="form-card mb-3">
    <div class="card-head">Student Information</div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label" for="dNameEng">Student Name (Eng)</label>
          <input type="text" class="form-control display-only" id="dNameEng" readonly disabled>
        </div>
        <div class="col-md-2">
          <label class="form-label" for="dNameChn">Student Name (Chn)</label>
          <input type="text" class="form-control display-only" id="dNameChn" readonly disabled>
        </div>
        <div class="col-md-2">
          <label class="form-label" for="dFaculty">Faculty</label>
          <input type="text" class="form-control display-only" id="dFaculty" readonly disabled>
        </div>
        <div class="col-md-2">
          <label class="form-label" for="dDepartment">Department</label>
          <input type="text" class="form-control display-only" id="dDepartment" readonly disabled>
        </div>
        <div class="col-md-2">
          <label class="form-label" for="dProgSubCode">Prog Sub Code</label>
          <input type="text" class="form-control display-only" id="dProgSubCode" readonly disabled>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="dProgTitle">Prog Title</label>
          <input type="text" class="form-control display-only" id="dProgTitle" readonly disabled>
        </div>
        <div class="col-md-2">
          <label class="form-label" for="dFundType">Fund Type</label>
          <input type="text" class="form-control display-only" id="dFundType" readonly disabled>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="dStatus">Student Status</label>
          <input type="text" class="form-control display-only" id="dStatus" readonly disabled>
        </div>
      </div>

      <div class="row g-3 mt-1">
        <div class="col-md-4">
          <label class="form-label" for="dSubjects">Subject</label>
          <textarea class="form-control display-only" id="dSubjects" rows="4" readonly disabled></textarea>
          @if (!$isView)
          <div class="form-check mt-3" style="padding-left:0;">
            <input class="form-check-input" type="checkbox" id="sendEmailChk" name="send_email" value="1">
            <label class="form-check-label" for="sendEmailChk">Send email to related stakeholder</label>
          </div>
          @endif
        </div>
      </div>
    </div>
  </div>

  {{-- ============ ACTIONS ============ --}}
  @if ($isView)
    <div class="d-flex gap-2">
      <button type="button" id="backBtn" class="btn btn-cancel"><i class="bi bi-x-lg me-1"></i>Close</button>
    </div>
  @else
    <div class="d-flex gap-2">
      <button type="button" id="saveBtn" class="btn btn-search"><i class="bi bi-arrow-right me-1"></i>Next</button>
      <button type="button" id="cancelBtn" class="btn btn-cancel"><i class="bi bi-x-lg me-1"></i>Cancel</button>
    </div>
  @endif
</form>

{{-- ============ CONFIRM DIALOG ============ --}}
<div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title" id="confirmModalTitle">Confirm</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="confirmModalMsg"></div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-search" id="confirmOk" style="display:none;">OK</button>
        <button type="button" class="btn btn-cancel" id="confirmNo">Cancel</button>
        <button type="button" class="btn btn-search" id="confirmYes">Confirm</button>
      </div>
    </div>
  </div>
</div>

{{-- ============ SAVE PREVIEW DIALOG (review before saving) ============ --}}
<div class="modal fade" id="previewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title"><i class="bi bi-eye me-1" style="color:var(--accent);"></i>Preview SEN</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="previewEmailBox" class="preview-email-box" style="display:none;">
          <div class="preview-email-title"><i class="bi bi-envelope me-1"></i>Email will be sent to the following stakeholders:</div>
          <div id="previewEmailList" class="preview-email-list"></div>
        </div>
        <div style="font-size:.8rem;color:var(--text-faint);margin-bottom:.6rem;">Review the SEN details below, then Save or Cancel.</div>
        <div class="table-responsive" style="max-height:52vh;overflow-y:auto;">
          <table class="table table-sm preview-table mb-2">
            <tbody id="previewBody"></tbody>
          </table>
        </div>
        <div id="previewDocs" class="preview-docs"></div>
      </div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-cancel" id="previewCancel"><i class="bi bi-x-lg me-1"></i>Cancel</button>
        <button type="button" class="btn btn-search" id="previewSave"><i class="bi bi-check-lg me-1"></i>Save</button>
      </div>
    </div>
  </div>
</div>

<script nonce="{{ $cspNonce }}">
  const confirmModalEl = document.getElementById('confirmModal');
  let confirmResolve = null;

  // single-OK alert dialog (error notices like "File too large" / "Filename too long")
  function askAlert(title, message) {
    return new Promise(resolve => {
      document.getElementById('confirmModalTitle').textContent = title;
      document.getElementById('confirmModalMsg').textContent = message;
      document.getElementById('confirmYes').style.display = 'none';
      document.getElementById('confirmNo').style.display = 'none';
      document.getElementById('confirmOk').style.display = '';
      confirmResolve = resolve;
      bootstrap.Modal.getOrCreateInstance(confirmModalEl).show();
    });
  }

  function askConfirm(title, message) {
    return new Promise(resolve => {
      document.getElementById('confirmModalTitle').textContent = title;
      document.getElementById('confirmModalMsg').textContent = message;
      document.getElementById('confirmYes').style.display = '';
      document.getElementById('confirmNo').style.display = '';
      document.getElementById('confirmOk').style.display = 'none';
      confirmResolve = resolve;
      bootstrap.Modal.getOrCreateInstance(confirmModalEl).show();
    });
  }
  function closeConfirm(answer) {
    bootstrap.Modal.getOrCreateInstance(confirmModalEl).hide();
    if (confirmResolve) { confirmResolve(answer); confirmResolve = null; }
  }
  document.getElementById('confirmYes').addEventListener('click', () => closeConfirm(true));
  document.getElementById('confirmNo').addEventListener('click', () => closeConfirm(false));
  document.getElementById('confirmOk').addEventListener('click', () => closeConfirm(true));
  confirmModalEl.addEventListener('hidden.bs.modal', () => { if (confirmResolve) { confirmResolve(false); confirmResolve = null; } });

  /* ---------- Save preview modal (review before saving) ---------- */
  const previewModalEl = document.getElementById('previewModal');
  let previewResolve = null;

  function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  }

  // read a form field for the preview: select -> selected option text, else value
  function previewFieldVal(id) {
    const el = document.getElementById(id);
    if (!el) return '';
    if (el.tagName === 'SELECT') {
      const opt = el.options[el.selectedIndex];
      return opt && opt.value !== '' ? opt.text : '';
    }
    return el.value.trim();
  }

  // parse staff ids from a display-only textarea of "Staff_Id — Name" lines
  function staffIdsFromTextarea(areaId) {
    return (document.getElementById(areaId).value || '')
      .split('\n')
      .map(l => l.trim())
      .filter(Boolean)
      .map(l => l.split(' — ')[0].trim())
      .filter(id => STAFF_EMAILS[id]);
  }

  // one line per stakeholder group: "Label: Name (email); Name2 (email2)"
  // headers are ALWAYS shown (empty group -> just the label, no recipients)
  function buildEmailList() {
    const groups = [
      ['Programme Leader', staffIdsFromTextarea('fPL')],
      ['Department Admin Staff', [document.getElementById('fDA_h').value].filter(id => STAFF_EMAILS[id])],
      ['Counsellor', [document.getElementById('fC_h').value].filter(id => STAFF_EMAILS[id])],
      ['Undergraduate Studies Support Officer', [document.getElementById('fUSSO_h').value].filter(id => STAFF_EMAILS[id])],
      ['Academic Advisor', staffIdsFromTextarea('dAdvisors')],
    ];
    return groups.map(([label, ids]) => {
      const parts = ids.map(id => {
        const s = STAFF_EMAILS[id];
        return s.email ? (s.name + ' (' + s.email + ')') : s.name;
      });
      return '<div><span class="pkey">' + escapeHtml(label) + ':</span> ' + parts.join('; ') + '</div>';
    }).join('');
  }

  function buildPreview() {
    // email banner: shown only when the checkbox is checked (create mode)
    const emailBox = document.getElementById('previewEmailBox');
    const sendEmailChk = document.getElementById('sendEmailChk');
    if (emailBox && sendEmailChk && sendEmailChk.checked) {
      document.getElementById('previewEmailList').innerHTML = buildEmailList();
      emailBox.style.display = 'block';
    } else if (emailBox) {
      emailBox.style.display = 'none';
    }

    const rows = [
      ['SEN Id', document.getElementById('fSenId').value],
      ['Student Id', previewFieldVal('fStudentId')],
      ['Student Name (Eng)', previewFieldVal('dNameEng')],
      ['Student Name (Chn)', previewFieldVal('dNameChn')],
      ['Faculty', previewFieldVal('dFaculty')],
      ['Department', previewFieldVal('dDepartment')],
      ['Prog Sub Code', previewFieldVal('dProgSubCode')],
      ['Prog Title', previewFieldVal('dProgTitle')],
      ['Fund Type', previewFieldVal('dFundType')],
      ['Student Status', previewFieldVal('dStatus')],
      ['Programme Leader', previewFieldVal('fPL')],
      ['Department Admin Staff', previewFieldVal('fDA')],
      ['Counsellor', previewFieldVal('fC')],
      ['Undergraduate Studies Support Officer', previewFieldVal('fUSSO')],
      ['Subject Teacher', previewFieldVal('dTeachers')],
      ['Academic Advisor', previewFieldVal('dAdvisors')],
      ['SEN Type', previewFieldVal('fSenType')],
      ['SEN Detail', previewFieldVal('fDetail')],
      ['Special Support Required', previewFieldVal('fSupport')],
      ['Special Examination Arrangement', previewFieldVal('fExam')],
      ['Temporary Special Support', previewFieldVal('fTemp')],
      ['Subject', previewFieldVal('dSubjects')],
    ];
    document.getElementById('previewBody').innerHTML = rows
      .map(([k, v]) => '<tr><td class="pkey">' + escapeHtml(k) + '</td><td>' + (v ? escapeHtml(v) : '<span class="text-muted">—</span>') + '</td></tr>')
      .join('');

    const docs = currentVisibleDocs.map(displayName);
    document.getElementById('previewDocs').innerHTML = docs.length
      ? '<div class="preview-docs-label">Documents</div>' + docs
          .map(d => '<span class="preview-doc"><i class="bi bi-file-earmark-text"></i>' + escapeHtml(d) + '</span>')
          .join('')
      : '<div class="text-muted" style="font-size:.85rem;">No documents</div>';
  }

  function askPreview() {
    return new Promise(resolve => {
      previewResolve = resolve;
      buildPreview();
      bootstrap.Modal.getOrCreateInstance(previewModalEl).show();
    });
  }
  function closePreview(answer) {
    bootstrap.Modal.getOrCreateInstance(previewModalEl).hide();
    if (previewResolve) { previewResolve(answer); previewResolve = null; }
  }
  document.getElementById('previewSave').addEventListener('click', () => closePreview(true));
  document.getElementById('previewCancel').addEventListener('click', () => closePreview(false));
  previewModalEl.addEventListener('hidden.bs.modal', () => { if (previewResolve) { previewResolve(false); previewResolve = null; } });

  /* ---------- state ---------- */
  let studentValid = false;
  let studentHasCase = false; // one-active-SEN-case rule (2026-09-02): blocks Save in create mode
  let nextSenId = '{{ $nextSenId }}'; // advanced locally after each save
  const IS_EDIT = {{ $isEdit ? 'true' : 'false' }};
  const IS_VIEW = {{ $isView ? 'true' : 'false' }};
  const EDIT_SEN_ID = '{{ $isEdit ? $editSen->SEN_Id : '' }}';
  let removedDocs = []; // saved docs marked for deletion (edit mode, applied on Save)
  let savedDocNames = []; // storage filenames that exist in tblSEN_Doc (edit mode)
  let savedOriginalMap = {}; // storage filename -> original filename (loaded from DB, edit mode)
  let stagedOriginalMap = {}; // storage filename -> original filename (new uploads this session)

  const editableSelectors = '#senForm .form-control, #senForm .form-select, #senForm textarea, #senForm .form-check-input';

  /* ---------- unsaved changes guard (wired to the layout's menu guard) ---------- */
  let formDirty = false;
  document.querySelectorAll(editableSelectors).forEach(el => {
    ['input', 'change'].forEach(ev => el.addEventListener(ev, () => { formDirty = true; }));
  });
  // uploading / removing documents also counts as an unsaved change
  const docFileInputEl = document.getElementById('docFileInput');
  if (docFileInputEl) {
    docFileInputEl.addEventListener('change', () => {
      if (docFileInputEl.files.length) formDirty = true;
    });
  }
  // the layout script (runs after this one) picks this up to guard sidebar navigation
  window.PUSEN_DIRTY_FN = () => formDirty;
  // guard browser refresh / tab close / external navigation (reload button, F5, Ctrl+R) —
  // the browser shows its native "Leave site?" confirm when there are unsaved changes
  window.addEventListener('beforeunload', (e) => {
    if (!formDirty || window.__pusenLogoutConfirmed) return;
    e.preventDefault();
    e.returnValue = ''; // required by Chrome/Edge to display the confirm dialog
  });

  function setFormDisabled(disabled) {
    document.querySelectorAll(editableSelectors).forEach(el => el.disabled = disabled);
    const saveBtn = document.getElementById('saveBtn');
    const cancelBtn = document.getElementById('cancelBtn');
    const chooseFilesBtn = document.getElementById('chooseFilesBtn');
    if (saveBtn) saveBtn.disabled = disabled;
    if (cancelBtn) cancelBtn.disabled = disabled;
    if (chooseFilesBtn) chooseFilesBtn.disabled = disabled;
    renderDocTable(currentVisibleDocs); // re-render doc rows with the locked state
  }

  function resetAll() {
    document.getElementById('senForm').reset();
    (window.__staffAcSync || []).forEach(fn => fn()); // staff autocomplete: resync defaults
    ['dNameEng','dNameChn','dFaculty','dDepartment','dProgSubCode','dProgTitle','dFundType','dStatus'].forEach(id => {
      document.getElementById(id).value = '';
    });
    fillTextArea('dTeachers', []);
    fillTextArea('dAdvisors', []);
    fillTextArea('dSubjects', []);
    refreshDocList([]);
    document.getElementById('docCountLabel').textContent = '0 / 20';
    const fileInput = document.getElementById('docFileInput');
    if (fileInput) fileInput.value = '';
    studentValid = false;
    studentHasCase = false; // one-active-case rule: fresh form -> no case flag
    formDirty = false;
  }

  function fillList(listId, items) {
    const sel = document.getElementById(listId);
    sel.innerHTML = '';
    if (!items.length) {
      const o = document.createElement('option');
      o.value = ''; o.textContent = '— none —';
      sel.appendChild(o);
      return;
    }
    items.forEach(it => {
      const o = document.createElement('option');
      o.value = it.id ?? it;
      o.textContent = it.label ?? it;
      sel.appendChild(o);
    });
  }

  // fill a display-only textarea (e.g. Subject Teacher) — one entry per line
  function fillTextArea(areaId, items) {
    document.getElementById(areaId).value = (items || [])
      .map(it => it.label ?? it)
      .join('\n');
  }

  /* ---------- [+ Create SEN Case] (create mode only) ---------- */
  const createCaseBtn = document.getElementById('createCaseBtn');
  if (createCaseBtn) {
    createCaseBtn.addEventListener('click', () => {
      // clear any staged files left from a previous aborted session for this SEN_Id
      // (fire-and-forget so the form reset below is immediate)
      fetch('/admin/create-sen/clear-staged', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
        body: JSON.stringify({ sen_id: document.getElementById('fSenId').value }),
      }).catch(() => {});
      resetAll();
      document.getElementById('fSenId').value = nextSenId;
      setFormDisabled(false);
      document.getElementById('fStudentId').focus();
    });
  }

  /* ================= Document upload ================= */
  const docFileInput = document.getElementById('docFileInput');
  const docTableBody = document.getElementById('docTableBody');
  let formLocked = false;      // true while the whole form is disabled (e.g. after Save)
  let currentVisibleDocs = []; // last rendered doc list (for re-render on lock/unlock)

  const chooseFilesBtn = document.getElementById('chooseFilesBtn');
  if (chooseFilesBtn) chooseFilesBtn.addEventListener('click', () => docFileInput.click());

  if (docFileInput) docFileInput.addEventListener('change', async () => {
    const files = [...docFileInput.files];
    if (!files.length) return;
    const senId = document.getElementById('fSenId').value;

    // filename length gate at selection time (Jackie 2026-08-28): files with a name
    // over 100 characters are rejected immediately and never uploaded/staged
    const rejected = files.filter(f => f.name.length > 100);
    const accepted = files.filter(f => f.name.length <= 100);
    if (rejected.length) {
      const msgEl = document.getElementById('confirmModalMsg');
      msgEl.style.whiteSpace = 'pre-line';
      askAlert('⚠️ Filename too long',
        'The following file(s) were NOT added - file name over 100 characters:\n\n' +
        rejected.map(f => '• ' + f.name + '  (' + f.name.length + ' characters)').join('\n') +
        '\n\nPlease rename the file(s) and choose them again.');
    }

    // file size gate at selection time (Jackie 2026-09-01, UAT CC-05): files over
    // 10 MB are rejected immediately with a DIALOG (never uploaded/staged), so the
    // user gets a clear message instead of a server-side toast.
    const MAX_DOC_SIZE_BYTES = 10 * 1024 * 1024; // 10 MB
    const tooBig = accepted.filter(f => f.size > MAX_DOC_SIZE_BYTES);
    const uploadable = accepted.filter(f => f.size <= MAX_DOC_SIZE_BYTES);
    if (tooBig.length) {
      const msgEl = document.getElementById('confirmModalMsg');
      msgEl.style.whiteSpace = 'pre-line';
      askAlert('⚠️ File too large',
        'The following file(s) were NOT added - maximum file size is 10 MB:\n\n' +
        tooBig.map(f => '• ' + f.name + '  (' + (f.size / (1024 * 1024)).toFixed(1) + ' MB)').join('\n') +
        '\n\nPlease remove them or choose file(s) smaller than 10 MB.');
    }

    for (const file of uploadable) {
      const fd = new FormData();
      fd.append('sen_id', senId);
      fd.append('file', file);
      try {
        const res = await fetch('/admin/create-sen/upload', {
          method: 'POST',
          headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
          body: fd,
        });
        const json = await res.json();
        if (json.success) {
          stagedOriginalMap[json.filename] = file.name; // remember original name for display
          refreshDocList(json.files);
          // once any file is uploaded, the Student Id can no longer be changed
          document.getElementById('fStudentId').disabled = true;
        } else {
          toast('❌ ' + (json.message || 'Upload failed: ' + file.name));
        }
      } catch (err) {
        toast('❌ Upload failed: ' + file.name + ' — ' + err.message);
      }
    }
    docFileInput.value = '';
  });

  // document list table: per-row View / X buttons + clickable filename (event delegation)
  docTableBody.addEventListener('click', (e) => {
    const viewBtn = e.target.closest('[data-view]');
    if (viewBtn) {
      // locked preview (PDF.js) - no download / no print from the viewer
      window.open('/admin/sen-doc-viewer/' + encodeURIComponent(viewBtn.dataset.view), '_blank');
      return;
    }
    const dlBtn = e.target.closest('[data-download]');
    if (dlBtn) {
      // trigger a download with the original filename (server sends attachment).
      // the download attribute also tells the layout's loading overlay to ignore this link.
      const a = document.createElement('a');
      a.href = '/admin/sen-doc/' + encodeURIComponent(dlBtn.dataset.download) + '?dl=1';
      a.download = '';
      document.body.appendChild(a);
      a.click();
      a.remove();
      return;
    }
    const rmBtn = e.target.closest('[data-remove]');
    if (rmBtn) {
      removeDocument(rmBtn.dataset.remove);
      return;
    }
  });

  // display name = original filename when known, else the storage name
  function displayName(name) {
    return savedOriginalMap[name] || stagedOriginalMap[name] || name;
  }

  // render the document list table: [View] [X] filename per row
  // view mode: [View] [Download] filename only (no remove, nothing disabled)
  function renderDocTable(files) {
    currentVisibleDocs = files.slice();
    docTableBody.innerHTML = '';
    if (!files.length) {
      const tr = document.createElement('tr');
      const td = document.createElement('td');
      td.colSpan = 2;
      td.className = 'text-muted';
      td.style.fontSize = '.85rem';
      td.textContent = '— none —';
      tr.appendChild(td);
      docTableBody.appendChild(tr);
      return;
    }
    files.forEach(name => {
      const tr = document.createElement('tr');
      const shown = displayName(name);

      // left actions cell: [View] [Download]
      const tdActions = document.createElement('td');
      tdActions.style.whiteSpace = 'nowrap';
      tdActions.style.width = '1%'; // shrink-wrap: keep the column as narrow as the buttons
      tdActions.style.padding = '.45rem 0 .45rem .3rem'; // no right padding -> filename sits right next to buttons
      const actions = document.createElement('div');
      actions.className = 'd-flex gap-1';

      const bView = document.createElement('button');
      bView.type = 'button'; bView.className = 'row-btn';
      bView.dataset.view = name;
      bView.title = 'View ' + shown;
      bView.disabled = formLocked && !IS_VIEW;
      bView.innerHTML = '<i class="bi bi-eye"></i>';

      const bDl = document.createElement('button');
      bDl.type = 'button'; bDl.className = 'row-btn btn-dl';
      bDl.dataset.download = name;
      bDl.title = 'Download ' + shown;
      bDl.disabled = formLocked && !IS_VIEW;
      bDl.innerHTML = '<i class="bi bi-download"></i>';

      actions.append(bView, bDl);
      tdActions.appendChild(actions);

      // filename cell: original filename grows, delete X sits at the right end (not in view mode)
      const tdName = document.createElement('td');
      tdName.style.padding = '.45rem .3rem';
      const nameRow = document.createElement('div');
      nameRow.className = 'd-flex align-items-center gap-1';
      const nameSpan = document.createElement('span');
      nameSpan.className = 'doc-name-text';
      nameSpan.textContent = shown;

      nameRow.append(nameSpan);
      if (!IS_VIEW) {
        const bX = document.createElement('button');
        bX.type = 'button'; bX.className = 'row-btn btn-x';
        bX.dataset.remove = name;
        bX.title = 'Remove ' + shown;
        bX.disabled = formLocked;
        bX.innerHTML = '<i class="bi bi-x-lg"></i>';
        nameRow.appendChild(bX);
      }

      tdName.appendChild(nameRow);

      tr.append(tdActions, tdName);
      docTableBody.appendChild(tr);
    });
  }

  // remove a document (staged -> delete now; saved in edit mode -> mark for removal)
  async function removeDocument(filename) {
    formDirty = true;
    const senId = document.getElementById('fSenId').value;

    if (IS_EDIT && savedDocNames.includes(filename)) {
      removedDocs.push(filename);
      refreshDocList([]);
      toast('🗑️ Marked for removal: ' + filename);
      return;
    }

    try {
      const res = await fetch('/admin/create-sen/remove-doc', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
        body: JSON.stringify({ sen_id: senId, filename }),
      });
      const json = await res.json();
      if (json.success) {
        refreshDocList(json.files);
        toast('🗑️ Removed ' + filename);
      } else {
        toast('❌ ' + (json.message || 'Remove failed'));
      }
    } catch (err) {
      toast('❌ Remove failed: ' + err.message);
    }
  }

  // refresh the doc list UI: always show saved docs (minus marked-removed) + staged files
  function refreshDocList(stagedFiles) {
    const visible = [];
    savedDocNames.forEach(n => { if (!removedDocs.includes(n)) visible.push(n); });
    (stagedFiles || []).forEach(n => { if (!visible.includes(n)) visible.push(n); });
    // drop original-name entries for files that no longer exist
    const visibleSet = new Set(visible);
    Object.keys(stagedOriginalMap).forEach(k => { if (!visibleSet.has(k)) delete stagedOriginalMap[k]; });
    renderDocTable(visible);
    document.getElementById('docCountLabel').textContent = visible.length + ' / 20';
  }

  /* ---------- Student Id lookup (autocomplete) ---------- */
  const fStudentId = document.getElementById('fStudentId');
  const acList = document.getElementById('studentAutocomplete');
  // student data injected from the controller (id + name)
  const STUDENTS = @json($students->map(fn ($s) => ['id' => $s->Student_Id, 'name' => $s->Student_Name_Eng]));
  let acIndex = -1; // highlighted item index

  /* ---------- Staff autocomplete (Department Admin / Counsellor / USSO) ---------- */
  const STAFF_POOLS = {
    fDA:   @json($staff['department_admin_staff']->map(fn ($s) => ['id' => $s->Staff_Id, 'name' => $s->Staff_Name])->values()->all()),
    fC:    @json($staff['counsellor']->map(fn ($s) => ['id' => $s->Staff_Id, 'name' => $s->Staff_Name])->values()->all()),
    fUSSO: @json($staff['undergraduate_studies_support_officer']->map(fn ($s) => ['id' => $s->Staff_Id, 'name' => $s->Staff_Name])->values()->all()),
  };

  // staff_id -> display name + email, used by the preview email banner
  // (dev override: all emails replaced with MAIL_DEV_OVERRIDE_TO)
  const STAFF_EMAILS = @json($staffEmails);

  // generic combobox: type to filter, pick from the dropdown, commit exact text on blur/Enter
  function attachStaffAc(inputId, pool) {
    const input = document.getElementById(inputId);
    const hidden = document.getElementById(inputId + '_h');
    const ac = document.getElementById(inputId + '_ac');
    let acIndex = -1;
    let lastValid = input.value;

    function filter(q) {
      q = q.trim().toLowerCase();
      if (!q) return pool;
      return pool.filter(s =>
        s.id.toLowerCase().startsWith(q) ||
        (s.name || '').toLowerCase().includes(q));
    }
    function show() {
      const items = filter(input.value);
      ac.innerHTML = '';
      if (!items.length) {
        const d = document.createElement('div');
        d.className = 'ac-empty';
        d.textContent = 'No matching staff';
        ac.appendChild(d);
      } else {
        items.slice(0, 100).forEach((s, i) => {
          const d = document.createElement('div');
          d.className = 'ac-item' + (i === acIndex ? ' active' : '');
          d.dataset.id = s.id;
          const idSpan = document.createElement('span');
          idSpan.className = 'ac-id';
          idSpan.textContent = s.id;
          const nameSpan = document.createElement('span');
          nameSpan.className = 'ac-name';
          nameSpan.textContent = s.name || '';
          d.append(idSpan, nameSpan);
          d.addEventListener('mousedown', (e) => {
            e.preventDefault(); // keep focus on the input
            pick(s.id);
          });
          ac.appendChild(d);
        });
      }
      ac.style.display = 'block';
    }
    function hide() { ac.style.display = 'none'; acIndex = -1; }
    function pick(id) {
      const s = pool.find(x => x.id === id) || { id, name: '' };
      input.value = s.name ? (s.id + ' — ' + s.name) : s.id;
      hidden.value = s.id;
      lastValid = input.value;
      hide();
      input.dispatchEvent(new Event('change', { bubbles: true })); // mark dirty
    }
    function commitTyped() {
      const v = input.value.trim();
      if (!v) { hidden.value = ''; lastValid = ''; return; }
      const hit = pool.find(s =>
        s.id.toUpperCase() === v.toUpperCase() ||
        (s.id + ' — ' + (s.name || '')).toUpperCase() === v.toUpperCase());
      if (hit) { hidden.value = hit.id; lastValid = input.value.trim(); }
      else { hidden.value = ''; input.value = lastValid; } // not a valid staff -> revert
    }

    input.addEventListener('input', () => { acIndex = -1; show(); });
    input.addEventListener('focus', () => { acIndex = -1; show(); });
    input.addEventListener('keydown', (e) => {
      const items = ac.querySelectorAll('.ac-item');
      if (e.key === 'ArrowDown') {
        e.preventDefault();
        if (items.length) {
          acIndex = (acIndex + 1) % items.length;
          items.forEach((el, i) => el.classList.toggle('active', i === acIndex));
        }
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (items.length) {
          acIndex = (acIndex - 1 + items.length) % items.length;
          items.forEach((el, i) => el.classList.toggle('active', i === acIndex));
        }
      } else if (e.key === 'Enter') {
        e.preventDefault();
        if (items.length && acIndex >= 0 && items[acIndex]) pick(items[acIndex].dataset.id);
        else { commitTyped(); hide(); }
      } else if (e.key === 'Escape') {
        hide();
      }
    });
    input.addEventListener('blur', () => setTimeout(() => { hide(); commitTyped(); }, 150));

    // after form reset the defaults may differ from lastValid -> resync
    window.__staffAcSync = window.__staffAcSync || [];
    window.__staffAcSync.push(() => { lastValid = input.value; });
  }

  ['fDA', 'fC', 'fUSSO'].forEach(id => attachStaffAc(id, STAFF_POOLS[id]));

  function acFilter(q) {
    q = q.trim().toUpperCase();
    if (!q) return STUDENTS; // empty -> all students
    return STUDENTS.filter(s => s.id.toUpperCase().startsWith(q));
  }

  function acShow() {
    const items = acFilter(fStudentId.value);
    acList.innerHTML = '';
    if (!items.length) {
      const d = document.createElement('div');
      d.className = 'ac-empty';
      d.textContent = 'No matching Student Id';
      acList.appendChild(d);
    } else {
      items.slice(0, 100).forEach((s, i) => {
        const d = document.createElement('div');
        d.className = 'ac-item' + (i === acIndex ? ' active' : '');
        d.dataset.id = s.id;
        const idSpan = document.createElement('span');
        idSpan.className = 'ac-id';
        idSpan.textContent = s.id;
        const nameSpan = document.createElement('span');
        nameSpan.className = 'ac-name';
        nameSpan.textContent = s.name || '';
        d.append(idSpan, nameSpan);
        d.addEventListener('mousedown', (e) => {
          e.preventDefault(); // keep focus on the input
          acPick(s.id);
        });
        acList.appendChild(d);
      });
    }
    acList.style.display = 'block';
  }

  function acHide() {
    acList.style.display = 'none';
    acIndex = -1;
  }

  function acPick(id) {
    fStudentId.value = id;
    acHide();
    studentValid = false;
    resetDisplayOnly();
    lookupStudent();
  }

  // typing -> filter the list (prefix match)
  fStudentId.addEventListener('input', () => {
    acIndex = -1;
    studentValid = false;
    resetDisplayOnly();
    acShow();
  });

  // empty field + click/focus -> show all students
  fStudentId.addEventListener('focus', () => {
    acIndex = -1;
    acShow();
  });

  // keyboard navigation: Up/Down move, Enter picks (or validates typed value), Esc closes
  fStudentId.addEventListener('keydown', (e) => {
    const items = acList.querySelectorAll('.ac-item');
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      if (items.length) {
        acIndex = (acIndex + 1) % items.length;
        items.forEach((el, i) => el.classList.toggle('active', i === acIndex));
      }
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      if (items.length) {
        acIndex = (acIndex - 1 + items.length) % items.length;
        items.forEach((el, i) => el.classList.toggle('active', i === acIndex));
      }
    } else if (e.key === 'Enter') {
      if (items.length && acIndex >= 0 && items[acIndex]) {
        e.preventDefault();
        acPick(items[acIndex].dataset.id);
      } else if (fStudentId.value.trim()) {
        // no highlight: validate whatever was typed
        e.preventDefault();
        studentValid = false;
        resetDisplayOnly();
        lookupStudent();
      }
    } else if (e.key === 'Escape') {
      acHide();
    }
  });

  // clicking away closes the dropdown; validate a fully-typed Id on blur
  fStudentId.addEventListener('blur', () => {
    setTimeout(() => {
      acHide();
      const v = fStudentId.value.trim();
      if (v && !studentValid) {
        resetDisplayOnly();
        lookupStudent();
      }
    }, 150);
  });

  async function lookupStudent() {
    const sid = fStudentId.value.trim();
    studentHasCase = false; // one-active-case rule: cleared until this lookup proves otherwise
    if (!sid) { // placeholder selected -> nothing to look up
      studentValid = false;
      resetDisplayOnly();
      return;
    }
    try {
      const res = await fetch('/admin/create-sen/student-info?student_id=' + encodeURIComponent(sid));
      const json = await res.json();
      if (!json.found) {
        studentValid = false;
        toast('❌ Student Id not found: ' + sid);
        resetDisplayOnly();
        return;
      }
      studentValid = true;
      const s = json.student;
      // one-active-SEN-case rule (2026-09-02): in create mode, warn + block when the
      // student already has a case (server also rejects on Save as the backstop)


      //studentHasCase = !IS_EDIT && !!json.has_case;
      studentHasCase = false;   // diable the one-active-SEN-case rule for now (2026-09-02)
      
      if (studentHasCase && !IS_VIEW) {
        // dialog (OK button) instead of a toast so the user cannot miss the block
        askAlert('⚠️ Existing SEN Case', 'Student ' + sid + ' already has SEN case ' + (json.existing_sen_id || '') + ' — only one case per student is allowed. Please edit the existing case instead.');
      }
      document.getElementById('dNameEng').value = s.student_name_eng ?? '';
      document.getElementById('dNameChn').value = s.student_name_chn ?? '';
      document.getElementById('dFaculty').value = s.faculty ?? '';
      document.getElementById('dDepartment').value = s.department ?? '';
      document.getElementById('dProgSubCode').value = s.prog_sub_code ?? '';
      document.getElementById('dProgTitle').value = s.prog_title ?? '';
      document.getElementById('dFundType').value = s.fund_type_code ?? '';
    document.getElementById('dStatus').value = s.student_status ?? '';
      fillTextArea('dTeachers', json.subject_teachers);
      fillTextArea('dAdvisors', json.academic_advisors);
      fillTextArea('dSubjects', json.subjects);
      // Programme Leader(s): display ALL PROG_LEADER advisors of this student (one per line)
      document.getElementById('fPL').value = (json.programme_leaders || [])
        .map(p => p.label || p.id)
        .join('\n');
      toast('✅ Student found: ' + (s.student_name_eng || sid));
    } catch (err) {
      toast('❌ Lookup failed: ' + err.message);
    }
  }

  function resetDisplayOnly() {
    ['dNameEng','dNameChn','dFaculty','dDepartment','dProgSubCode','dProgTitle','dFundType','dStatus'].forEach(id => {
      document.getElementById(id).value = '';
    });
    document.getElementById('fPL').value = '';
    fillTextArea('dTeachers', []);
    fillTextArea('dAdvisors', []);
    fillTextArea('dSubjects', []);
  }

  /* ---------- Save ---------- */
  const saveBtn = document.getElementById('saveBtn');
  if (saveBtn) saveBtn.addEventListener('click', async () => {
    const sid = fStudentId.value.trim();
    if (!sid) { toast('⚠️ Student Id is required'); fStudentId.focus(); return; }
    if (!studentValid) {
      toast('⚠️ Please enter a valid Student Id first (check it on blur)');
      fStudentId.focus();
      return;
    }
    // one-active-SEN-case rule (2026-09-02): block Save in create mode when the
    // student already has a case (server rejects too - this is just early UX)
    if (!IS_EDIT && studentHasCase) {
      askAlert('⚠️ Existing SEN Case', 'This student already has an SEN case — only one case per student is allowed. Please edit the existing case instead.');
      return;
    }

    // filename length check (Jackie 2026-08-28): originals over 100 chars are not
    // allowed to save - show a dialog and block (DB now 150 wide, but keep UX rule)
    const longDocs = currentVisibleDocs
      .map(n => displayName(n))
      .filter(n => n.length > 100);
    if (longDocs.length) {
      const msgEl = document.getElementById('confirmModalMsg');
      msgEl.style.whiteSpace = 'pre-line';
      askAlert('⚠️ Filename too long',
        'The following file name(s) are over 100 characters and cannot be saved:\n\n' +
        longDocs.map(n => '• ' + n + '  (' + n.length + ' characters)').join('\n') +
        '\n\nPlease remove them or re-upload with a shorter file name.');
      return;
    }

    // review the SEN details first; only save when the user confirms
    const confirmed = await askPreview();
    if (!confirmed) return;

    const fd = new FormData(document.getElementById('senForm'));
    const payload = {};
    for (const [k, v] of fd) payload[k] = v;
    // student_id is excluded from FormData when the select is disabled (lock after upload),
    // so add it explicitly
    payload.student_id = sid;
    if (IS_EDIT) {
      payload.sen_id = EDIT_SEN_ID;
      payload.removed_docs = removedDocs;
    }

    try {
      const res = await fetch('/admin/create-sen/save', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
        body: JSON.stringify(payload),
      });
      const json = await res.json();
      if (json.success) {
        const emailNote =
          json.email === 'sent'   ? ' — 📧 email sent to stakeholders'
        : json.email === 'failed' ? ' — ⚠️ saved, but email failed (see log)'
        : '';
        toast('✅ SEN case ' + json.sen_id + ' saved' + emailNote);
        formDirty = false;
        if (IS_EDIT) {
          // back to SEN Search after a short beat so the toast is visible
          setTimeout(() => { window.location.href = '/admin/sen-search?restore=1'; }, 900);
          return;
        }
        // advance the auto SEN_Id for the next case (after reset so it isn't wiped)
        const m = (json.sen_id || '').match(/(\d+)$/);
        if (m) {
          nextSenId = 'SEN-' + String(Number(m[1]) + 1).padStart(3, '0');
        }
        resetAll();
        document.getElementById('fSenId').value = nextSenId;
        setFormDisabled(true);
      } else {
        toast('❌ ' + (json.message || 'Save failed'));
      }
    } catch (err) {
      toast('❌ Save failed: ' + err.message);
    }
  });

  /* ---------- Cancel / Back ---------- */
  const backBtn = document.getElementById('backBtn');
  if (backBtn) {
    backBtn.addEventListener('click', () => { window.location.href = '/admin/sen-search?restore=1'; });
  }

  const cancelBtn = document.getElementById('cancelBtn');
  if (cancelBtn) cancelBtn.addEventListener('click', async () => {
    const ok = await askConfirm('Cancel', IS_EDIT ? 'Discard all changes and go back to SEN Search?' : 'Discard all changes and reset the form?');
    if (!ok) return;
    // delete staged uploads from the server too
    try {
      await fetch('/admin/create-sen/clear-staged', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
        body: JSON.stringify({ sen_id: document.getElementById('fSenId').value }),
      });
    } catch (e) { /* ignore */ }
    if (IS_EDIT) {
      // discard ALL changes: saved docs stay untouched, staged files already deleted
      window.location.href = '/admin/sen-search?restore=1';
      return;
    }
    resetAll();
    setFormDisabled(true);
    toast('Form reset');
  });

  /* ---------- edit / view mode: prefill docs + populate data ---------- */
  if (IS_EDIT || IS_VIEW) {
    // saved docs from tblSEN_Doc
    savedDocNames = @json($editDocs->pluck('Doc_Filename'));
    savedOriginalMap = @json($editDocs->mapWithKeys(fn($d) => [$d->Doc_Filename => $d->Doc_Filename_Original])->filter()->all());
    refreshDocList([]);
    fStudentId.disabled = true;
    if (IS_VIEW) {
      // view mode: keep the whole form locked, docs are view-only
      setFormDisabled(true);
    } else {
      // edit mode: enable the form (Student Id stays disabled - not changeable)
      setFormDisabled(false);
    }
    // populate the display-only student block + lists
    lookupStudent();
  }

  /* ---------- initial state ---------- */
  setFormDisabled(true);
  if (IS_EDIT && !IS_VIEW) { setFormDisabled(false); fStudentId.disabled = true; }
</script>

@endsection
