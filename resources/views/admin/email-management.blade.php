@extends('layouts.app')

@push('head')
  <link href="https://cdn.jsdelivr.net/npm/ag-grid-community@31/styles/ag-grid.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/ag-grid-community@31/styles/ag-theme-alpine.css" rel="stylesheet">
@endpush

@section('content')

<style>
  .form-label { font-size: .78rem; font-weight: 600; color: var(--text-muted); margin-bottom: .35rem; }
  .form-control, .form-select {
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

  .btn-search {
    background: #9B2331; color: #fff; font-weight: 600; font-size: .85rem;
    border: 1px solid #7d1d29; border-radius: 10px; padding: .5rem 1.2rem;
    box-shadow: 0 4px 14px rgba(155, 35, 49, .3);
  }
  .btn-search:hover { background: #d04553; border-color: #a02d38; color: #fff; }
  .btn-cancel { border: 1px solid #7d1d29; color: #fff; background: #9B2331; font-size: .85rem; font-weight: 600; border-radius: 10px; padding: .5rem 1.2rem; }
  .btn-cancel:hover { background: #d04553; color: #fff; }

  .radio-group { display: flex; flex-direction: column; gap: .45rem; padding-top: .2rem; }
  .radio-group .form-check { padding-left: 1.6em; }
  .radio-group .form-check-input { margin-top: .22em; }
  .radio-group .form-check-label { font-size: .9rem; color: var(--text); }

  /* autocomplete dropdown (reuses the create-sen look) */
  .ac-box { position: relative; }
  .ac-list {
    position: absolute; z-index: 1050; top: 100%; left: 0; right: 0;
    background: var(--card-bg); border: 1px solid var(--border); border-radius: 8px;
    max-height: 220px; overflow-y: auto; display: none;
    box-shadow: 0 8px 24px rgba(0,0,0,.18);
  }
  .ac-item { padding: .45rem .7rem; font-size: .85rem; cursor: pointer; color: var(--text); }
  .ac-item:hover, .ac-item.active { background: var(--accent-soft); }
  .ac-item .ac-id { font-weight: 600; margin-right: .4rem; }
  .ac-item .ac-name { color: var(--text-muted); font-size: .8rem; }
  .ac-empty { padding: .5rem .7rem; font-size: .82rem; color: var(--text-faint); }

  #emailGrid { height: 62vh; border: 1px solid var(--card-border); border-radius: var(--radius); overflow: hidden;
    --ag-background-color: var(--card-bg); --ag-foreground-color: var(--text);
    --ag-border-color: var(--card-border); --ag-header-background-color: var(--bg-soft); }
  #emailGrid .ag-header-cell-text { font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; }
  #emailGrid .ag-cell { display: flex; align-items: center; }
  #emailGrid .ag-paging-panel { font-size: .8rem; color: var(--text-muted); }

  /* grid delete button — same style as the SEN Type Delete button (.btn-del) */
  .btn-del {
    border: 1px solid #7d1d29; color: #fff; background: #9B2331;
    font-size: .78rem; font-weight: 600;
    border-radius: 10px; padding: .38rem .9rem;
    line-height: 1.4; /* stop AG Grid cell line-height from inflating the button */
    white-space: nowrap;
  }
  .btn-del:hover { background: #d04553; border-color: #a02d38; color: #fff; }
  .btn-del i { color: #fff; }

  .dev-note {
    font-size: .75rem; color: var(--text-faint); margin-top: .3rem;
  }
  .dev-note b { color: var(--accent); }
</style>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-3" style="margin-top:-1.5rem; margin-bottom:.75rem;">
  <div>
    <h1 class="mb-0" style="font-size:1.25rem;">Email Management</h1>
  </div>
</div>

<div class="form-card mb-3">
  <div class="card-head">Recipient</div>
  <div class="card-body">
    <div class="radio-group">
      <div class="form-check">
        <input class="form-check-input" type="radio" name="recipientType" id="rtTeacher" value="subject_teacher" checked>
        <label class="form-check-label" for="rtTeacher">Send email to Subject teacher <span class="text-muted" style="font-size:.78rem;">(template ET-003)</span></label>
      </div>
      <div class="form-check">
        <input class="form-check-input" type="radio" name="recipientType" id="rtStudent" value="student">
        <label class="form-check-label" for="rtStudent">Send email to Student <span class="text-muted" style="font-size:.78rem;">(template ET-004)</span></label>
      </div>
      <div class="form-check">
        <input class="form-check-input" type="radio" name="recipientType" id="rtBoth" value="both">
        <label class="form-check-label" for="rtBoth">Both</label>
      </div>
    </div>
    @if ($devOverrideOn)
      <div class="dev-note">Development mode: all recipients will be sent to <b>{{ $devEmail }}</b> only.</div>
    @endif
  </div>
</div>

<div class="form-card mb-3">
  <div class="card-head">SEN Case Selection</div>
  <div class="card-body">
    <div class="radio-group mb-3">
      <div class="form-check">
        <input class="form-check-input" type="radio" name="caseScope" id="csAll" value="all">
        <label class="form-check-label" for="csAll">All SEN case</label>
      </div>
      <div class="form-check">
        <input class="form-check-input" type="radio" name="caseScope" id="csSpecific" value="specific">
        <label class="form-check-label" for="csSpecific">Specific SEN case</label>
      </div>
    </div>

    <div id="specificArea" style="display:none;">
      <div class="row g-3">
        <div class="col-md-5">
          <label class="form-label" for="fSenCase">SEN case</label>
          <div class="d-flex gap-2">
            <div class="ac-box flex-grow-1">
              <input type="text" class="form-control" id="fSenCase" placeholder="Type SEN case to search…" autocomplete="off">
              <div class="ac-list" id="senCaseAc"></div>
            </div>
            <button type="button" class="btn btn-cancel" id="addSenCaseBtn" style="white-space:nowrap;"><i class="bi bi-plus-lg me-1"></i>Add</button>
          </div>
          <div class="dev-note" id="senCaseNote"></div>
        </div>
        <div class="col-md-5">
          <label class="form-label" for="fStudentNo">Student number</label>
          <div class="d-flex gap-2">
            <div class="ac-box flex-grow-1">
              <input type="text" class="form-control" id="fStudentNo" placeholder="Type Student Id / name to search…" autocomplete="off">
              <div class="ac-list" id="studentAc"></div>
            </div>
            <button type="button" class="btn btn-cancel" id="addStudentBtn" style="white-space:nowrap;"><i class="bi bi-plus-lg me-1"></i>Add</button>
          </div>
          <div class="dev-note" id="studentNote"></div>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- ============ AG GRID ============ --}}
<div id="emailGrid" class="ag-theme-alpine-dark"></div>

{{-- ============ SEND ============ --}}
<div class="d-flex justify-content-end mt-3">
  <button type="button" id="sendBtn" class="btn btn-search"><i class="bi bi-send me-1"></i>Send Email</button>
</div>

{{-- ============ CONFIRM SEND DIALOG ============ --}}
<div class="modal fade" id="confirmSendModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title" style="font-size:.95rem;">Confirm Send</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="confirmSendMsg" style="font-size:.85rem;"></div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-search" id="confirmSendYes">Send</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/ag-grid-community@31/dist/ag-grid-community.min.js"></script>
<script nonce="{{ $cspNonce }}">
  const DEV_OVERRIDE = @json($devEmail);
  const STUDENT_EMAIL = @json($studentEmail);

  /* ---------- AG Grid ---------- */
  const gridOptions = {
    columnDefs: [
      { field: 'sen_id',           headerName: 'SEN Case No', width: 110 },
      { field: 'student_id',       headerName: 'Student ID',  width: 120 },
      { field: 'student_name_eng', headerName: 'Student Name (Eng)', flex: 1, minWidth: 160 },
      { field: 'student_name_chn', headerName: 'Student Name (Chn)', width: 130 },
      { field: 'subject_teacher',  headerName: 'Subject Teacher',   flex: 1, minWidth: 180 },
      { field: 'sen_type',         headerName: 'SEN_Type',    flex: 1, minWidth: 180 },
      {
        field: 'sen_id',
        headerName: 'Action',
        width: 110,
        sortable: false,
        pinned: 'right',
        cellRenderer: params => {
          const btn = document.createElement('button');
          btn.className = 'btn-del';
          btn.innerHTML = '<i class="bi bi-trash me-1"></i>Delete';
          btn.addEventListener('click', () => {
            gridApi.applyTransaction({ remove: [params.data] });
          });
          return btn;
        },
      },
    ],
    rowData: [],
    pagination: true,
    paginationPageSize: 10,
    paginationPageSizeSelector: false,
    defaultColDef: { sortable: true, resizable: true },
    getRowId: p => p.data.sen_id,
    suppressCellFocus: true,
  };
  let gridApi = agGrid.createGrid(document.getElementById('emailGrid'), gridOptions);

  function applyGridTheme() {
    const el = document.getElementById('emailGrid');
    const dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
    el.classList.toggle('ag-theme-alpine-dark', dark);
    el.classList.toggle('ag-theme-alpine', !dark);
  }
  applyGridTheme();
  document.getElementById('themeToggle')?.addEventListener('click', () => setTimeout(applyGridTheme, 60));

  /* ---------- scope radios ---------- */
  document.querySelectorAll('input[name="caseScope"]').forEach(r => {
    r.addEventListener('change', () => {
      const specific = document.getElementById('csSpecific').checked;
      document.getElementById('specificArea').style.display = specific ? 'block' : 'none';
      if (!specific) loadAllCases();
    });
  });

  function selectedRows() {
    const rows = [];
    gridApi.forEachNode(n => rows.push(n.data));
    return rows;
  }

  async function loadAllCases() {
    try {
      const res = await fetch('/admin/email-management/data?all=1');
      const rows = await res.json();
      gridApi.setGridOption('rowData', rows);
      toast('Loaded ' + rows.length + ' SEN case(s)');
    } catch (err) {
      toast('❌ Failed to load cases: ' + err.message);
    }
  }

  /* ---------- generic autocomplete ---------- */
  function attachAc(inputId, acId, fetcher, pick) {
    const input = document.getElementById(inputId);
    const ac = document.getElementById(acId);
    let items = [];
    let acIndex = -1;

    async function show() {
      const q = input.value.trim();
      try {
        const res = await fetch(fetcher(q));
        items = await res.json();
      } catch { items = []; }
      ac.innerHTML = '';
      if (!items.length) {
        const d = document.createElement('div');
        d.className = 'ac-empty';
        d.textContent = 'No matching records';
        ac.appendChild(d);
      } else {
        items.slice(0, 50).forEach((it, i) => {
          const d = document.createElement('div');
          d.className = 'ac-item' + (i === acIndex ? ' active' : '');
          let value, label;
          if (typeof it === 'string') {
            value = it;
            label = it;
            d.innerHTML = '<span class="ac-id">' + escapeHtml(it) + '</span>';
          } else {
            value = it.id;
            label = it.label || it.id;
            d.innerHTML = '<span class="ac-id">' + escapeHtml(it.id) + '</span><span class="ac-name">' + escapeHtml(it.label || '') + '</span>';
          }
          d.dataset.value = value;
          d.dataset.label = label;
          d.addEventListener('mousedown', (e) => {
            e.preventDefault(); // keep focus on the input
            input.value = label; // show the selection in the text box
            pick(value);
          });
          ac.appendChild(d);
        });
      }
      ac.style.display = 'block';
    }
    function hide() { ac.style.display = 'none'; acIndex = -1; }
    function move(dir) {
      const els = ac.querySelectorAll('.ac-item');
      if (!els.length) return;
      acIndex = (acIndex + dir + els.length) % els.length;
      els.forEach((el, i) => el.classList.toggle('active', i === acIndex));
      els[acIndex].scrollIntoView({ block: 'nearest' });
    }
    input.addEventListener('input', () => { acIndex = -1; show(); });
    input.addEventListener('focus', () => show());
    input.addEventListener('keydown', (e) => {
      if (e.key === 'ArrowDown') { e.preventDefault(); move(1); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); move(-1); }
      else if (e.key === 'Enter') {
        e.preventDefault();
        const el = ac.querySelector('.ac-item.active');
        if (el) {
          input.value = el.dataset.label || el.dataset.value; // show in text box
          pick(el.dataset.value);
        }
        else hide();
      } else if (e.key === 'Escape') hide();
    });
    input.addEventListener('blur', () => setTimeout(hide, 150));
  }

  function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  }

  /* ---------- add by SEN case ---------- */
  let pendingSenCase = '';
  attachAc('fSenCase', 'senCaseAc',
    q => '/admin/email-management/case-search?q=' + encodeURIComponent(q),
    v => { pendingSenCase = v; document.getElementById('senCaseNote').textContent = 'Selected: ' + v; });
  document.getElementById('addSenCaseBtn').addEventListener('click', async () => {
    const v = pendingSenCase || document.getElementById('fSenCase').value.trim();
    if (!v) { toast('⚠️ Type or select a SEN case first'); return; }
    await addCases([v]);
    pendingSenCase = '';
    document.getElementById('fSenCase').value = '';
    document.getElementById('senCaseNote').textContent = '';
  });

  /* ---------- add by student number ---------- */
  let pendingStudent = '';
  attachAc('fStudentNo', 'studentAc',
    q => '/admin/email-management/student-search?q=' + encodeURIComponent(q),
    v => { pendingStudent = v; document.getElementById('studentNote').textContent = 'Selected: ' + v; });
  document.getElementById('addStudentBtn').addEventListener('click', async () => {
    const v = pendingStudent || document.getElementById('fStudentNo').value.trim();
    if (!v) { toast('⚠️ Type or select a student number first'); return; }
    await addStudents([v]);
    pendingStudent = '';
    document.getElementById('fStudentNo').value = '';
    document.getElementById('studentNote').textContent = '';
  });

  /* ---------- add rows (dedupe by SEN id) ---------- */
  function existingIds() {
    const ids = {};
    gridApi.forEachNode(n => { ids[n.data.sen_id] = true; });
    return ids;
  }

  async function addCases(senIds) {
    const dup = senIds.filter(id => existingIds()[id]);
    if (dup.length) toast('⚠️ Already in list: ' + dup.join(', '));
    const fresh = senIds.filter(id => !existingIds()[id]);
    if (!fresh.length) return;
    try {
      const res = await fetch('/admin/email-management/data', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
        body: JSON.stringify({ sen_ids: fresh }),
      });
      const rows = await res.json();
      if (!rows.length) { toast('⚠️ SEN case not found'); return; }
      gridApi.applyTransaction({ add: rows });
    } catch (err) {
      toast('❌ Failed: ' + err.message);
    }
  }

  async function addStudents(studentIds) {
    try {
      const res = await fetch('/admin/email-management/data', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
        body: JSON.stringify({ student_ids: studentIds }),
      });
      const rows = await res.json();
      if (!rows.length) { toast('⚠️ No SEN cases for that student'); return; }
      const dup = rows.filter(r => existingIds()[r.sen_id]);
      const fresh = rows.filter(r => !existingIds()[r.sen_id]);
      if (dup.length) toast('⚠️ Already in list: ' + dup.map(r => r.sen_id).join(', '));
      if (fresh.length) { gridApi.applyTransaction({ add: fresh }); }
    } catch (err) {
      toast('❌ Failed: ' + err.message);
    }
  }

  /* ---------- send ---------- */
  const confirmModal = document.getElementById('confirmSendModal');
  document.getElementById('sendBtn').addEventListener('click', () => {
    const rows = selectedRows();
    if (!rows.length) { toast('⚠️ No SEN cases in the list'); return; }
    const type = document.querySelector('input[name="recipientType"]:checked').value;
    const typeLabel =
      type === 'subject_teacher' ? 'Subject teacher (ET-003)'
      : type === 'student' ? 'Student (ET-004)'
      : 'Both (ET-003 + ET-004)';
    const recipient = DEV_OVERRIDE !== '' ? DEV_OVERRIDE : (type === 'subject_teacher' ? 'the subject teacher(s)' : STUDENT_EMAIL);
    document.getElementById('confirmSendMsg').innerHTML =
      'Send <b>' + rows.length + '</b> SEN case(s) to <b>' + typeLabel + '</b>?<br>' +
      'Recipient: <b>' + escapeHtml(recipient) + '</b>';
    bootstrap.Modal.getOrCreateInstance(confirmModal).show();
  });

  document.getElementById('confirmSendYes').addEventListener('click', async () => {
    bootstrap.Modal.getOrCreateInstance(confirmModal).hide();
    const type = document.querySelector('input[name="recipientType"]:checked').value;
    const senIds = selectedRows().map(r => r.sen_id);
    const btn = document.getElementById('sendBtn');
    btn.disabled = true;
    try {
      const res = await fetch('/admin/email-management/send', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
        body: JSON.stringify({ type, sen_ids: senIds }),
      });
      const json = await res.json();
      if (json.success) {
        toast('✅ Sent ' + json.sent + '/' + json.recipients + ' email(s)' + (json.failed ? ' — ⚠️ ' + json.failed + ' failed (see log)' : ''));
      } else {
        toast('❌ ' + (json.message || 'Send failed'));
      }
    } catch (err) {
      toast('❌ Send failed: ' + err.message);
    } finally {
      btn.disabled = false;
    }
  });
</script>

@endsection
