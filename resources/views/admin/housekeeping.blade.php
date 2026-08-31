@extends('layouts.app')

@push('head')
  <link href="https://cdn.jsdelivr.net/npm/ag-grid-community@31/styles/ag-grid.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/ag-grid-community@31/styles/ag-theme-alpine.css" rel="stylesheet">
@endpush

@section('content')

<style>
  .table { --bs-table-bg: transparent; color: var(--text); }
  .table thead th {
    font-size: .72rem;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: var(--text-faint);
    font-weight: 700;
    border-bottom: 1px solid var(--card-border);
    background: color-mix(in srgb, var(--bg-soft) 70%, transparent);
    padding: .8rem 1.1rem;
    white-space: nowrap;
  }
  .table tbody td {
    border-color: var(--border);
    padding: .85rem 1.1rem;
    font-size: .875rem;
    color: var(--text);
    vertical-align: middle;
  }
  .table-hover tbody tr:hover { background: var(--accent-soft); }
  .mono { font-family: ui-monospace, SFMono-Regular, Consolas, monospace; font-size: .8rem; }
  .btn-hk {
    background: #9B2331; border: 1px solid #7d1d29; color: #fff;
    font-weight: 600; font-size: .85rem; border-radius: 10px; padding: .5rem 1.2rem;
  }
  .btn-hk:hover { background: #d04553; border-color: #a02d38; color: #fff; }
  .btn-hk:disabled { opacity: .45; filter: none; }
  .criteria li { font-size: .85rem; margin-bottom: .25rem; }
  .num-ok  { color: var(--success); }
  .num-bad { color: var(--danger); }
  .num-dup { color: #fbbf24; }
  /* wider confirm dialog so the student list table fits comfortably */
  .modal-dialog-hk { max-width: 820px; }
  .modal-dialog-hk .modal-body { overflow-wrap: anywhere; }

  /* ---------- criteria bar ---------- */
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
  .form-select option { background: var(--card-bg); color: var(--text); }
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

  /* ---------- AG Grid ---------- */
  #hkGrid {
    height: 60vh;
    border: 1px solid var(--card-border);
    border-radius: var(--radius);
    overflow: hidden;
    --ag-background-color: var(--card-bg);
    --ag-foreground-color: var(--text);
    --ag-border-color: var(--card-border);
    --ag-header-background-color: color-mix(in srgb, var(--bg-soft) 70%, transparent);
    --ag-header-foreground-color: #000;
    --ag-row-hover-color: var(--accent-soft);
    --ag-selected-row-background-color: var(--accent-soft);
    --ag-odd-row-background-color: transparent;
    --ag-font-family: 'Inter', system-ui, sans-serif;
    --ag-font-size: 13px;
    --ag-cell-horizontal-padding: .9rem;
  }
  #hkGrid .ag-root-wrapper { border: none; }
  #hkGrid .ag-header-cell-text { font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; }
  #hkGrid .ag-cell { display: flex; align-items: center; }
  #hkGrid .ag-paging-panel { font-size: .8rem; color: var(--text-muted); border-top: 1px solid var(--card-border); }
  #hkGrid .ag-overlay { background: color-mix(in srgb, var(--card-bg) 45%, transparent); }
  .mono-cell { font-family: ui-monospace, SFMono-Regular, Consolas, monospace; font-size: .8rem; }
  .remarks-cell { font-size: .78rem; color: var(--text-muted); }
  .grid-loading-overlay {
    display: flex; flex-direction: column; align-items: center; gap: .65rem;
    font-size: .82rem; font-weight: 600; color: var(--text-muted);
    padding: 1.25rem 1.75rem;
    background: color-mix(in srgb, var(--card-bg) 92%, transparent);
    border: 1px solid var(--card-border);
    border-radius: 14px;
    box-shadow: 0 12px 32px rgba(0,0,0,.18);
  }
  .grid-loading-overlay .spinner-border {
    width: 2.1rem; height: 2.1rem;
    border-width: .26em;
    color: var(--accent);
  }
</style>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
  <div>
    <h1 class="mb-0" style="font-size:1.25rem;">Housekeeping</h1>
    <div class="text-muted" style="font-size:.85rem;">
      Permanent data cleanup for students who have left the university.
      Records are written to the <span class="mono">tblHK_*</span> log tables (the backup) before anything is deleted.
    </div>
  </div>
</div>

<div class="stat-card p-0 overflow-hidden">
  <div class="p-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
      <div style="min-width: 280px; flex: 1;">
        <div class="fw-semibold" style="font-size:.95rem;">Housekeeping for Student</div>
        <div class="text-muted mt-1" style="font-size:.8rem;">A student qualifies when ALL of the following are true:</div>
        <ul class="criteria mt-2 mb-0">
          <li><i class="bi bi-check2-circle me-1" style="color:var(--success);"></i><span class="mono">tblStudent.Student_Status</span> is <b>COMPLETED</b>, <b>LEFT</b> or <b>PASSED AWAY</b></li>
          <li><i class="bi bi-check2-circle me-1" style="color:var(--success);"></i><span class="mono">tblStudent.updated_at</span> is <b>strictly older than 3 years</b> (UTC)</li>
        </ul>
        <div class="text-muted mt-2" style="font-size:.8rem;">
          Deletes: all SEN cases (<span class="mono">tblSEN</span>) + attached documents (<span class="mono">tblSEN_Doc</span>) + physical files on the server +
          advisor assignments (<span class="mono">tblAdvisor_Student</span>) + subject registrations (<span class="mono">tblStudent_Reg</span>) + the student (<span class="mono">tblStudent</span>).
        </div>
        <div class="mt-2" style="font-size:.78rem; color: var(--danger);">
          <i class="bi bi-exclamation-triangle-fill me-1"></i>Permanent — no archive, no recovery. One student is processed at a time.
        </div>
      </div>
      <div class="d-flex flex-column gap-2">
        <button type="button" id="hkBtn" class="btn btn-hk">
          <i class="bi bi-person-x me-1"></i>Check &amp; Delete
        </button>
      </div>
    </div>
  </div>
</div>

{{-- ============ RUN LOG (AG GRID) ============ --}}
<div class="stat-card p-0 overflow-hidden mt-4">
  <div class="p-3" style="border-bottom:1px solid var(--card-border);">
    <div class="fw-semibold" style="font-size:.95rem;">Housekeeping Run Log</div>
  </div>
  <div class="p-3">
    {{-- criteria bar --}}
    <form id="hkSearchForm" class="row g-3 align-items-end">
      <div class="col-md-4">
        <label class="form-label" for="fStudentId">Student ID</label>
        <input type="text" class="form-control" id="fStudentId" name="student_id" list="studentIdList" placeholder="e.g. 25000045G">
        <datalist id="studentIdList">
          @foreach ($studentIds as $sid)
            <option value="{{ $sid }}"></option>
          @endforeach
        </datalist>
      </div>
      <div class="col-md-3">
        <label class="form-label" for="fFrom">Deleted At From</label>
        <input type="date" class="form-control" id="fFrom" name="delete_at_from">
      </div>
      <div class="col-md-3">
        <label class="form-label" for="fTo">Deleted At To</label>
        <input type="date" class="form-control" id="fTo" name="delete_at_to">
      </div>
      <div class="col-md-2 d-flex gap-2">
        <button type="submit" class="btn btn-search"><i class="bi bi-search me-1"></i>Search</button>
        <button type="button" id="hkResetBtn" class="btn btn-cancel"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset</button>
      </div>
    </form>
  </div>
  <div class="p-3 pt-0">
    <div id="hkGrid" class="ag-theme-alpine-dark"></div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/ag-grid-community@31/dist/ag-grid-community.min.js"></script>
<script>
  /* ---------- loading overlay component ---------- */
  class HkLoadingOverlay {
    init() {
      this.eGui = document.createElement('div');
      this.eGui.className = 'grid-loading-overlay';
      this.eGui.innerHTML = `
        <div class="spinner-border" role="status" aria-hidden="true"></div>
        <div>Loading housekeeping log…</div>
      `;
    }
    getGui() { return this.eGui; }
  }

  /* ---------- AG Grid ---------- */
  const hkGridOptions = {
    columnDefs: [
      { field: 'id',             headerName: 'Run',         width: 95,  cellClass: 'mono-cell',
        valueFormatter: p => '#' + p.value },
      { field: 'student_id',     headerName: 'Student Id',  width: 130, cellClass: 'mono-cell' },
      { field: 'name_eng',       headerName: 'Name (Eng)',  flex: 1, minWidth: 150 },
      { field: 'name_chn',       headerName: 'Name (Chn)',  width: 110 },
      { field: 'student_status', headerName: 'Status',      width: 125 },
      { field: 'sen_count',      headerName: 'SEN',         width: 70,  cellStyle: { textAlign: 'right' } },
      { field: 'doc_count',      headerName: 'Docs',        width: 70,  cellStyle: { textAlign: 'right' } },
      { field: 'delete_at_hk',   headerName: 'Deleted At (HK)', width: 170, cellClass: 'mono-cell' },
      { field: 'delete_by',      headerName: 'By',          width: 100, cellClass: 'mono-cell' },
      { field: 'remarks',        headerName: 'Remarks',     flex: 1.4, minWidth: 200, cellClass: 'remarks-cell' },
    ],
    rowData: [],
    pagination: true,
    paginationPageSize: 10,
    paginationPageSizeSelector: false,
    defaultColDef: { sortable: true, resizable: true },
    getRowId: p => String(p.data.id),
    suppressCellFocus: true,
    loadingOverlayComponent: HkLoadingOverlay,
    onGridReady: (params) => {
      hkGridApi = params.api;
      loadHkRuns(); // auto-load all runs on page open (mirrors the old recent-runs table)
    },
  };
  let hkGridApi;
  agGrid.createGrid(document.getElementById('hkGrid'), hkGridOptions);

  /* ---------- theme sync ---------- */
  function applyHkGridTheme() {
    const el = document.getElementById('hkGrid');
    const dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
    el.classList.toggle('ag-theme-alpine-dark', dark);
    el.classList.toggle('ag-theme-alpine', !dark);
  }
  applyHkGridTheme();
  document.getElementById('themeToggle')?.addEventListener('click', () => setTimeout(applyHkGridTheme, 60));

  /* ---------- load / search ---------- */
  async function loadHkRuns() {
    const params = new URLSearchParams();
    const fd = new FormData(document.getElementById('hkSearchForm'));
    for (const [k, v] of fd) { if (v !== '') params.set(k, v); }
    hkGridApi.showLoadingOverlay();
    try {
      const res = await fetch('/admin/housekeeping/runs/search?' + params.toString());
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const rows = await res.json();
      hkGridApi.setGridOption('rowData', rows);
    } catch (err) {
      toast('❌ Search failed: ' + err.message);
    } finally {
      hkGridApi.hideOverlay();
    }
  }

  document.getElementById('hkSearchForm').addEventListener('submit', e => {
    e.preventDefault();
    loadHkRuns();
  });

  document.getElementById('hkResetBtn').addEventListener('click', () => {
    document.getElementById('hkSearchForm').reset();
    loadHkRuns();
    toast('Criteria reset');
  });
</script>

{{-- ============ CONFIRM DIALOG ============ --}}
<div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-hk">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title" id="confirmModalTitle" style="font-size:.95rem;">Confirm Housekeeping</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="confirmModalMsg" style="font-size:.85rem;"></div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-cancel" id="confirmNo">Cancel</button>
        <button type="button" class="btn btn-hk" id="confirmYes">Delete Permanently</button>
      </div>
    </div>
  </div>
</div>

{{-- ============ RESULT DIALOG ============ --}}
<div class="modal fade" id="resultModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title" id="resultModalTitle" style="font-size:.95rem;">Housekeeping Result</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="resultModalBody" style="font-size:.85rem;"></div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-search" data-bs-dismiss="modal">OK</button>
      </div>
    </div>
  </div>
</div>

<script>
  const CSRF = '{{ csrf_token() }}';
  const hkBtn = document.getElementById('hkBtn');
  const confirmModalEl = document.getElementById('confirmModal');
  const resultModalEl  = document.getElementById('resultModal');
  let confirmResolve = null;

  function closeConfirm(answer) {
    bootstrap.Modal.getOrCreateInstance(confirmModalEl).hide();
    if (confirmResolve) { confirmResolve(answer); confirmResolve = null; }
  }
  document.getElementById('confirmYes').addEventListener('click', () => closeConfirm(true));
  document.getElementById('confirmNo').addEventListener('click', () => closeConfirm(false));
  confirmModalEl.addEventListener('hidden.bs.modal', () => {
    if (confirmResolve) { confirmResolve(false); confirmResolve = null; }
  });

  function askConfirm(html) {
    return new Promise(resolve => {
      document.getElementById('confirmModalMsg').innerHTML = html;
      confirmResolve = resolve;
      bootstrap.Modal.getOrCreateInstance(confirmModalEl).show();
    });
  }

  function showResult(title, html) {
    document.getElementById('resultModalTitle').textContent = title;
    document.getElementById('resultModalBody').innerHTML = html;
    bootstrap.Modal.getOrCreateInstance(resultModalEl).show();
  }

  function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[c]));
  }

  hkBtn.addEventListener('click', async () => {
    hkBtn.disabled = true;
    hkBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Checking…';

    try {
      // ---- 1. preview counts ----
      const pRes = await fetch('/admin/housekeeping/student/preview', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: JSON.stringify({}),
      });
      const p = await pRes.json();

      if (!p.success) {
        showResult('Housekeeping Error', `<div class="fw-semibold">${esc(p.message ?? 'Unknown error')}</div>`);
        return;
      }

      if (p.students === 0) {
        showResult('Nothing to Do', `
          <div class="d-flex align-items-start gap-2">
            <i class="bi bi-check-circle-fill mt-1" style="color:var(--success);"></i>
            <div>
              <div class="fw-semibold">No qualifying students found.</div>
              <div class="text-muted mt-1" style="font-size:.8rem;">
                Students with status COMPLETED / LEFT / PASSED AWAY and
                <span class="mono">updated_at</span> older than 3 years will appear here.
              </div>
            </div>
          </div>
        `);
        return;
      }

      // ---- 2. confirm with counts + student list ----
      const rows = (p.students_list ?? []).map(s => `
        <tr>
          <td class="mono">${esc(s.student_id)}</td>
          <td>${esc(s.name_eng) || '—'}</td>
          <td>${esc(s.name_chn) || '—'}</td>
          <td>${esc(s.status)}</td>
          <td class="mono" style="white-space:nowrap;">${esc(s.updated_at_hk) || '—'}</td>
        </tr>`).join('');

      const ok = await askConfirm(`
        <div class="fw-semibold mb-2">This will permanently delete:</div>
        <div class="mb-1"><span class="num-ok fw-bold" style="font-size:1rem;">${p.students}</span> student record(s)</div>
        <div class="mb-1"><span class="num-ok fw-bold" style="font-size:1rem;">${p.sen}</span> SEN case(s)</div>
        <div class="mb-1"><span class="num-ok fw-bold" style="font-size:1rem;">${p.docs}</span> document record(s) + their physical files on the server</div>
        <div class="mb-1"><span class="num-dup fw-bold" style="font-size:1rem;">${p.advisor}</span> advisor assignment(s)</div>
        <div class="mb-1"><span class="num-dup fw-bold" style="font-size:1rem;">${p.reg}</span> subject registration(s)</div>
        <div class="mt-3">
          <div class="fw-semibold mb-1" style="font-size:.8rem;">Qualifying students — please verify:</div>
          <div style="max-height:240px; overflow-y:auto; border:1px solid var(--border); border-radius:8px;">
            <table class="table table-sm mb-0" style="font-size:.75rem;">
              <thead>
                <tr>
                  <th>Student ID</th>
                  <th>English Name</th>
                  <th>Chinese Name</th>
                  <th>Status</th>
                  <th>Updated At (HK)</th>
                </tr>
              </thead>
              <tbody>${rows}</tbody>
            </table>
          </div>
        </div>
        <div class="mt-2" style="color:var(--danger);">
          <i class="bi bi-exclamation-triangle-fill me-1"></i>No archive, no recovery. Log rows are written first as the backup.
        </div>
      `);
      if (!ok) return;

      // ---- 3. run ----
      hkBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Deleting…';
      const rRes = await fetch('/admin/housekeeping/student/run', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
        body: JSON.stringify({}),
      });
      const r = await rRes.json();

      if (!r.success) {
        showResult('Housekeeping Error', `<div class="fw-semibold">${esc(r.message ?? 'Unknown error')}</div>`);
        return;
      }

      const failRows = (r.details ?? []).filter(d => d.error)
        .map(d => `<div class="text-muted" style="font-size:.78rem;">
                     <span class="mono">${esc(d.student)}</span> — ${esc(d.error)}</div>`)
        .join('');

      showResult('Housekeeping Completed', `
        <div class="mb-1"><span class="num-ok fw-bold" style="font-size:1.05rem;">${r.students_processed}</span> student(s) processed</div>
        <div class="mb-1"><span class="num-ok fw-bold" style="font-size:1.05rem;">${r.sen_deleted}</span> SEN case(s) deleted</div>
        <div class="mb-1"><span class="num-ok fw-bold" style="font-size:1.05rem;">${r.docs_deleted}</span> document record(s) deleted</div>
        <div class="mb-1"><span class="num-ok fw-bold" style="font-size:1.05rem;">${r.files_deleted}</span> file(s) deleted from server
          ${r.files_missing ? ` <span class="text-muted">(${r.files_missing} already missing)</span>` : ''}
          ${r.files_failed ? ` <span style="color:var(--danger);">(${r.files_failed} failed)</span>` : ''}
        </div>
        <div class="mb-1"><span class="num-dup fw-bold" style="font-size:1.05rem;">${r.advisor_deleted}</span> advisor assignment(s) deleted</div>
        <div class="mb-1"><span class="num-dup fw-bold" style="font-size:1.05rem;">${r.reg_deleted}</span> subject registration(s) deleted</div>
        ${r.students_failed ? `
          <div class="mt-2 mb-1" style="color:var(--danger);"><span class="fw-bold">${r.students_failed}</span> student(s) failed — records kept, log rows written, re-run after fixing:</div>
          ${failRows}
        ` : ''}
        <div class="text-muted mt-2" style="font-size:.78rem;"><i class="bi bi-journal-check me-1"></i>Audit rows written to tblHK_Student_Log / tblHK_SEN_Log / tblHK_SEN_Doc_Log.</div>
      `);
      resultModalEl.addEventListener('hidden.bs.modal', () => location.reload(), { once: true });
    } catch (e) {
      showResult('Housekeeping Error', `<div class="fw-semibold">Request failed: ${esc(e.message)}</div>`);
    } finally {
      hkBtn.disabled = false;
      hkBtn.innerHTML = '<i class="bi bi-person-x me-1"></i>Check &amp; Delete';
    }
  });
</script>

@endsection
