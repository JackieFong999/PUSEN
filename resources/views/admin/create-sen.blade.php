@extends('layouts.app')

@section('content')

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

  .btn-search {
    background: var(--accent-grad);
    color: #fff; font-weight: 600; font-size: .85rem;
    border: 0; border-radius: 10px; padding: .5rem 1.4rem;
    box-shadow: 0 4px 14px rgba(var(--accent-rgb), .3);
  }
  .btn-search:hover { color: #fff; filter: brightness(1.08); }
  .btn-search i { color: #fff; }

  .btn-cancel { border: 1px solid var(--border); color: var(--text-muted); background: transparent; }
  .btn-cancel:hover { background: var(--bg-soft); color: var(--text); }
  .btn-cancel i { color: var(--text-muted); }
  .btn-cancel:hover i { color: var(--text); }

  .btn-create { background: var(--accent-grad); color: #fff; font-weight: 600; font-size: .85rem; border: 0; border-radius: 10px; padding: .5rem 1.2rem; box-shadow: 0 4px 14px rgba(var(--accent-rgb), .3); }
  .btn-create:hover { color: #fff; filter: brightness(1.08); }
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

  .modal-content { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 14px; }
  .modal-content .modal-title { font-size: 1rem; font-weight: 600; color: var(--text); }
  .modal-content .modal-body { font-size: .88rem; color: var(--text); }
</style>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-3" style="margin-top:-1.5rem; margin-bottom:.75rem;">
  <div>
    <h1 class="mb-0" style="font-size:1.25rem;">Create SEN</h1>
  </div>
</div>

{{-- ============ CREATE BUTTON ============ --}}
<div class="mb-3">
  <button type="button" id="createCaseBtn" class="btn btn-create"><i class="bi bi-plus-lg me-1"></i>Create SEN Case</button>
</div>

{{-- ============ FORM ============ --}}
<form id="senForm" autocomplete="off">

  <div class="form-card mb-3">
    <div class="card-head">SEN Case</div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-3">
          <label class="form-label" for="fSenId">SEN Id</label>
          <input type="text" class="form-control display-only" id="fSenId" value="{{ $nextSenId }}" readonly disabled>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="fStudentId">Student Id <span class="text-danger">*</span></label>
          <select class="form-select" id="fStudentId" name="student_id" disabled>
            <option value="">-- Select Student (Active) --</option>
            @foreach ($students as $st)
              <option value="{{ $st->Student_Id }}">{{ $st->Student_Id }} — {{ $st->Student_Name_Eng }}</option>
            @endforeach
          </select>
        </div>
      </div>

      <div class="row g-3 mt-1">
        <div class="col-md-4">
          <label class="form-label" for="fPL">Programme Leader</label>
          <select class="form-select" id="fPL" name="programme_leader" disabled>
            <option value="">-- Select --</option>
            @foreach ($staff['programme_leader'] as $s)
              <option value="{{ $s->Staff_Id }}">{{ $s->Staff_Id }} — {{ $s->Staff_Name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="fDA">Department Admin Staff</label>
          <select class="form-select" id="fDA" name="department_admin_staff" disabled>
            <option value="">-- Select --</option>
            @foreach ($staff['department_admin_staff'] as $s)
              <option value="{{ $s->Staff_Id }}">{{ $s->Staff_Id }} — {{ $s->Staff_Name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="fC">Counsellor</label>
          <select class="form-select" id="fC" name="counsellor" disabled>
            <option value="">-- Select --</option>
            @foreach ($staff['counsellor'] as $s)
              <option value="{{ $s->Staff_Id }}">{{ $s->Staff_Id }} — {{ $s->Staff_Name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="fSO">SEN Officer</label>
          <select class="form-select" id="fSO" name="sen_officer" disabled>
            <option value="">-- Select --</option>
            @foreach ($staff['sen_officer'] as $s)
              <option value="{{ $s->Staff_Id }}">{{ $s->Staff_Id }} — {{ $s->Staff_Name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="fUSSO">Undergraduate Studies Support Officer</label>
          <select class="form-select" id="fUSSO" name="undergraduate_studies_support_officer" disabled>
            <option value="">-- Select --</option>
            @foreach ($staff['undergraduate_studies_support_officer'] as $s)
              <option value="{{ $s->Staff_Id }}">{{ $s->Staff_Id }} — {{ $s->Staff_Name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="fSenType">SEN Type</label>
          <select class="form-select" id="fSenType" name="sen_type" disabled>
            <option value="">-- Select --</option>
            @foreach ($senTypes as $t)
              <option value="{{ $t }}">{{ $t }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="fTemp">Temporary Special Support</label>
          <input type="text" class="form-control" id="fTemp" name="temporary_special_support" disabled>
        </div>
        <div class="col-12">
          <label class="form-label" for="fDetail">SEN Detail</label>
          <textarea class="form-control" id="fDetail" name="sen_detail" rows="2" disabled></textarea>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="fSupport">Special Support Required</label>
          <textarea class="form-control" id="fSupport" name="special_support_required" rows="2" disabled></textarea>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="fExam">Special Examination Arrangement</label>
          <textarea class="form-control" id="fExam" name="special_examination_arrangement" rows="2" disabled></textarea>
        </div>
      </div>
    </div>
  </div>

  {{-- ============ STUDENT INFO (display only) ============ --}}
  <div class="form-card mb-3">
    <div class="card-head">Student Information <span class="text-muted" style="text-transform:none;letter-spacing:0;">(display only)</span></div>
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
        <div class="col-md-2">
          <label class="form-label" for="dStatus">Student Status</label>
          <input type="text" class="form-control display-only" id="dStatus" readonly disabled>
        </div>
      </div>

      <div class="row g-3 mt-1">
        <div class="col-md-4">
          <label class="form-label" for="dTeachers">Subject Teacher (display only)</label>
          <select class="form-select display-list display-only" id="dTeachers" multiple size="4" disabled>
            <option value="">— none —</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="dAdvisors">Academic Advisor (display only)</label>
          <select class="form-select display-list display-only" id="dAdvisors" multiple size="4" disabled>
            <option value="">— none —</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="dSubjects">Subject (display only)</label>
          <select class="form-select display-list display-only" id="dSubjects" multiple size="4" disabled>
            <option value="">— none —</option>
          </select>
        </div>
      </div>
    </div>
  </div>

  {{-- ============ DOCUMENT UPLOAD ============ --}}
  <div class="form-card mb-3">
    <div class="card-head">Documents <span class="text-muted" style="text-transform:none;letter-spacing:0;">(upload)</span></div>
    <div class="card-body">
      <div class="d-flex align-items-center gap-2 mb-2">
        <button type="button" id="chooseFilesBtn" class="btn btn-cancel"><i class="bi bi-paperclip me-1"></i>Choose Files</button>
        <span class="text-muted" style="font-size:.75rem;">PDF only &middot; max 1 MB each</span>
      </div>
      <input type="file" id="docFileInput" accept=".pdf,application/pdf" multiple hidden>
      <label class="form-label d-flex justify-content-between align-items-center mb-1">
        <span>Uploaded Documents <span class="text-muted fw-normal">(display only)</span> &mdash; limited to 20 documents</span>
        <span class="badge-soft" id="docCountLabel">0 / 20</span>
      </label>
      <select class="form-select display-list display-only mb-2" id="dDocs" multiple size="4" disabled>
        <option value="">— none —</option>
      </select>
      <button type="button" id="removeDocBtn" class="btn btn-cancel" disabled><i class="bi bi-trash me-1"></i>Remove</button>
    </div>
  </div>

  {{-- ============ ACTIONS ============ --}}
  <div class="d-flex gap-2">
    <button type="button" id="saveBtn" class="btn btn-search"><i class="bi bi-check-lg me-1"></i>Save</button>
    <button type="button" id="cancelBtn" class="btn btn-cancel"><i class="bi bi-x-lg me-1"></i>Cancel</button>
  </div>
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
        <button type="button" class="btn btn-cancel" id="confirmNo">Cancel</button>
        <button type="button" class="btn btn-search" id="confirmYes">Confirm</button>
      </div>
    </div>
  </div>
</div>

<script>
  const confirmModalEl = document.getElementById('confirmModal');
  let confirmResolve = null;

  function askConfirm(title, message) {
    return new Promise(resolve => {
      document.getElementById('confirmModalTitle').textContent = title;
      document.getElementById('confirmModalMsg').textContent = message;
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
  confirmModalEl.addEventListener('hidden.bs.modal', () => { if (confirmResolve) { confirmResolve(false); confirmResolve = null; } });

  /* ---------- state ---------- */
  let studentValid = false;
  let nextSenId = '{{ $nextSenId }}'; // advanced locally after each save

  const editableSelectors = '#senForm .form-control, #senForm .form-select, #senForm textarea';

  function setFormDisabled(disabled) {
    document.querySelectorAll(editableSelectors).forEach(el => el.disabled = disabled);
    document.getElementById('saveBtn').disabled = disabled;
    document.getElementById('cancelBtn').disabled = disabled;
    document.getElementById('chooseFilesBtn').disabled = disabled;
    document.getElementById('removeDocBtn').disabled = true; // re-evaluated on list selection
  }

  function resetAll() {
    document.getElementById('senForm').reset();
    ['dNameEng','dNameChn','dFaculty','dDepartment','dProgSubCode','dProgTitle','dFundType','dStatus'].forEach(id => {
      document.getElementById(id).value = '';
    });
    fillList('dTeachers', []);
    fillList('dAdvisors', []);
    fillList('dSubjects', []);
    fillList('dDocs', []);
    document.getElementById('docCountLabel').textContent = '0 / 20';
    document.getElementById('docFileInput').value = '';
    studentValid = false;
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

  /* ---------- [+ Create SEN Case] ---------- */
  document.getElementById('createCaseBtn').addEventListener('click', () => {
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

  /* ================= Document upload ================= */
  const docFileInput = document.getElementById('docFileInput');
  const dDocs = document.getElementById('dDocs');

  document.getElementById('chooseFilesBtn').addEventListener('click', () => docFileInput.click());

  docFileInput.addEventListener('change', async () => {
    const files = [...docFileInput.files];
    if (!files.length) return;
    const senId = document.getElementById('fSenId').value;
    for (const file of files) {
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
          fillList('dDocs', json.files);
          document.getElementById('docCountLabel').textContent = json.files.length + ' / 20';
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

  // enable Remove only when a file is selected in the list
  dDocs.addEventListener('change', () => {
    document.getElementById('removeDocBtn').disabled = !dDocs.selectedOptions.length;
  });

  document.getElementById('removeDocBtn').addEventListener('click', async () => {
    const sel = dDocs.selectedOptions[0];
    if (!sel || !sel.value) return;
    const senId = document.getElementById('fSenId').value;
    try {
      const res = await fetch('/admin/create-sen/remove-doc', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
        body: JSON.stringify({ sen_id: senId, filename: sel.value }),
      });
      const json = await res.json();
      if (json.success) {
        fillList('dDocs', json.files);
        document.getElementById('docCountLabel').textContent = json.files.length + ' / 20';
        document.getElementById('removeDocBtn').disabled = true;
        toast('🗑️ Removed ' + sel.value);
      } else {
        toast('❌ ' + (json.message || 'Remove failed'));
      }
    } catch (err) {
      toast('❌ Remove failed: ' + err.message);
    }
  });

  /* ---------- Student Id lookup ---------- */
  const fStudentId = document.getElementById('fStudentId');

  // clear display-only block when selection changes, then look the student up
  fStudentId.addEventListener('change', () => {
    studentValid = false;
    resetDisplayOnly();
    lookupStudent();
  });

  async function lookupStudent() {
    const sid = fStudentId.value.trim();
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
      document.getElementById('dNameEng').value = s.student_name_eng ?? '';
      document.getElementById('dNameChn').value = s.student_name_chn ?? '';
      document.getElementById('dFaculty').value = s.faculty ?? '';
      document.getElementById('dDepartment').value = s.department ?? '';
      document.getElementById('dProgSubCode').value = s.prog_sub_code ?? '';
      document.getElementById('dProgTitle').value = s.prog_title ?? '';
      document.getElementById('dFundType').value = s.fund_type_code ?? '';
      document.getElementById('dStatus').value = s.student_status ?? '';
      fillList('dTeachers', json.subject_teachers);
      fillList('dAdvisors', json.academic_advisors);
      fillList('dSubjects', json.subjects);
      toast('✅ Student found: ' + (s.student_name_eng || sid));
    } catch (err) {
      toast('❌ Lookup failed: ' + err.message);
    }
  }

  function resetDisplayOnly() {
    ['dNameEng','dNameChn','dFaculty','dDepartment','dProgSubCode','dProgTitle','dFundType','dStatus'].forEach(id => {
      document.getElementById(id).value = '';
    });
    fillList('dTeachers', []);
    fillList('dAdvisors', []);
    fillList('dSubjects', []);
  }

  /* ---------- Save ---------- */
  document.getElementById('saveBtn').addEventListener('click', async () => {
    const sid = fStudentId.value.trim();
    if (!sid) { toast('⚠️ Student Id is required'); fStudentId.focus(); return; }
    if (!studentValid) {
      toast('⚠️ Please enter a valid Student Id first (check it on blur)');
      fStudentId.focus();
      return;
    }
    const ok = await askConfirm('Save SEN case', 'Save this SEN case for student ' + sid + '?');
    if (!ok) return;

    const fd = new FormData(document.getElementById('senForm'));
    const payload = {};
    for (const [k, v] of fd) payload[k] = v;
    // student_id is excluded from FormData when the select is disabled (lock after upload),
    // so add it explicitly
    payload.student_id = sid;

    try {
      const res = await fetch('/admin/create-sen/save', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
        body: JSON.stringify(payload),
      });
      const json = await res.json();
      if (json.success) {
        toast('✅ SEN case ' + json.sen_id + ' saved');
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

  /* ---------- Cancel ---------- */
  document.getElementById('cancelBtn').addEventListener('click', async () => {
    const ok = await askConfirm('Cancel', 'Discard all changes and reset the form?');
    if (!ok) return;
    // delete staged uploads from the server too
    try {
      await fetch('/admin/create-sen/clear-staged', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
        body: JSON.stringify({ sen_id: document.getElementById('fSenId').value }),
      });
    } catch (e) { /* ignore */ }
    resetAll();
    setFormDisabled(true);
    toast('Form reset');
  });

  /* ---------- initial state ---------- */
  setFormDisabled(true);
</script>

@endsection
