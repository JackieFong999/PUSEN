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
  .btn-cancel:hover i { color: #fff; }

  #senGrid .ag-root-wrapper { border: none; }

  #senGrid {
    height: 62vh;
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
  #senGrid .ag-header-cell-text { font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; }
  #senGrid .ag-cell { display: flex; align-items: center; }
  #senGrid .ag-paging-panel { font-size: .8rem; color: var(--text-muted); border-top: 1px solid var(--card-border); }

  /* ---------- loading overlay ---------- */
  #senGrid .ag-overlay { background: color-mix(in srgb, var(--card-bg) 45%, transparent); }
  .grid-loading-overlay {
    display: flex; flex-direction: column; align-items: center; gap: .65rem;
    font-size: .82rem; font-weight: 600; color: var(--text-muted);
    padding: 1.25rem 1.75rem;
    background: color-mix(in srgb, var(--card-bg) 92%, transparent);
    border: 1px solid var(--card-border);
    border-radius: 14px;
    box-shadow: 0 12px 32px rgba(0,0,0,.18);
  }
  .grid-loading-overlay .spinner-border { width: 2.1rem; height: 2.1rem; border-width: .26em; color: var(--accent); }

  .btn-edit { border: 1px solid #7d1d29; color: #fff; background: #9B2331; font-size: .85rem; font-weight: 600; border-radius: 10px; padding: .5rem 1.2rem; }
  .btn-edit:hover { background: #d04553; border-color: #a02d38; color: #fff; }
  .btn-edit-sm { font-size: .78rem; padding: .38rem .9rem; border-radius: 10px; line-height: 1.4; white-space: nowrap; }
</style>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-3" style="margin-top:-1.5rem; margin-bottom:.75rem;">
  <div>
    <h1 class="mb-0" style="font-size:1.25rem;">SEN Search</h1>
  </div>
</div>

{{-- ============ CRITERIA BAR ============ --}}
<div class="stat-card mb-2">
  <form id="senSearchForm" class="row g-3 align-items-end">
    <div class="col-md-3">
      <label class="form-label" for="fStudentId">Student Id</label>
      <input type="text" class="form-control" id="fStudentId" name="student_id" placeholder="e.g. 25111111G">
    </div>
    <div class="col-md-3">
      <label class="form-label" for="fNameEng">Student Name (Eng)</label>
      <input type="text" class="form-control" id="fNameEng" name="student_name_eng" placeholder="e.g. CHEN">
    </div>
    <div class="col-md-3">
      <label class="form-label" for="fNameChn">Student Name (Chn)</label>
      <input type="text" class="form-control" id="fNameChn" name="student_name_chn" placeholder="e.g. 陳">
    </div>
    <div class="col-md-3">
      <label class="form-label" for="fPL">Programme Leader</label>
      <select class="form-select" id="fPL" name="programme_leader">
        <option value="">-- Select --</option>
        @foreach ($plStaff as $s)
          <option value="{{ $s->Staff_Id }}">{{ $s->Staff_Id }} — {{ $s->Staff_Name }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label" for="fDA">Department Admin Staff</label>
      <select class="form-select" id="fDA" name="department_admin_staff">
        <option value="">-- Select --</option>
        @foreach ($staff['department_admin_staff'] as $s)
          <option value="{{ $s->Staff_Id }}">{{ $s->Staff_Id }} — {{ $s->Staff_Name }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label" for="fC">Counsellor</label>
      <select class="form-select" id="fC" name="counsellor">
        <option value="">-- Select --</option>
        @foreach ($staff['counsellor'] as $s)
          <option value="{{ $s->Staff_Id }}">{{ $s->Staff_Id }} — {{ $s->Staff_Name }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label" for="fUSSO">Undergraduate Studies Support Officer</label>
      <select class="form-select" id="fUSSO" name="undergraduate_studies_support_officer">
        <option value="">-- Select --</option>
        @foreach ($staff['undergraduate_studies_support_officer'] as $s)
          <option value="{{ $s->Staff_Id }}">{{ $s->Staff_Id }} — {{ $s->Staff_Name }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label" for="fSenType">SEN Type</label>
      <select class="form-select" id="fSenType" name="sen_type">
        <option value="">-- Select --</option>
        @foreach ($senTypes as $t)
          <option value="{{ $t->Id }}">{{ $t->SEN_Type }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label" for="fDetail">SEN Detail</label>
      <input type="text" class="form-control" id="fDetail" name="sen_detail" placeholder="e.g. ADHD">
    </div>
    <div class="col-md-6 d-flex gap-2">
      <button type="submit" class="btn btn-search"><i class="bi bi-search me-1"></i>Search</button>
      <button type="button" id="exportBtn" class="btn btn-cancel" title="Export to Excel"><i class="bi bi-box-arrow-up me-1"></i>Export</button>
      <button type="button" id="resetBtn" class="btn btn-cancel"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset</button>
    </div>
  </form>
</div>

{{-- ============ AG GRID ============ --}}
<div id="senGrid" class="ag-theme-alpine-dark"></div>

{{-- ============ NO RESULT DIALOG ============ --}}
<div class="modal fade" id="noResultModal" tabindex="-1" aria-labelledby="noResultModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:400px;">
    <div class="modal-content">
      <div class="modal-body" style="padding:1.4rem 1.5rem;">
        <div class="d-flex align-items-center gap-3">
          <i class="bi bi-search" style="font-size:1.5rem;color:var(--accent-solid);"></i>
          <div>
            <div style="font-weight:700;color:var(--text);font-size:.95rem;">No records found</div>
            <div style="font-size:.82rem;color:var(--text-muted);margin-top:.15rem;">No SEN cases match your search criteria. Please try different keywords.</div>
          </div>
        </div>
      </div>
      <div class="modal-footer" style="border-top:1px solid var(--card-border);padding:.7rem 1.5rem;">
        <button type="button" class="btn btn-save" data-bs-dismiss="modal" style="min-width:90px;">OK</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/ag-grid-community@31/dist/ag-grid-community.min.js"></script>
<script>
  /* ---------- loading overlay component (must be defined before gridOptions) ---------- */
  class SenLoadingOverlay {
    init() {
      this.eGui = document.createElement('div');
      this.eGui.className = 'grid-loading-overlay';
      this.eGui.innerHTML = `
        <div class="spinner-border" role="status" aria-hidden="true"></div>
        <div>Loading SEN cases…</div>
      `;
    }
    getGui() { return this.eGui; }
  }

  /* ---------- date formatting (MySQL datetime -> dd/mm/yyyy hh:mm) ---------- */
  function formatUpdateDate(v) {
    if (!v) return '—';
    const m = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/.exec(String(v));
    if (!m) return v;
    return m[3] + '/' + m[2] + '/' + m[1] + ' ' + m[4] + ':' + m[5];
  }
  @php $canEditSen = in_array(Auth::user()?->Role_Id, ['SA', 'AU'], true); @endphp
  const CAN_EDIT_SEN = {{ $canEditSen ? 'true' : 'false' }};

  const gridOptions = {
    columnDefs: [
      { field: 'sen_id',                  headerName: 'SEN Id',       width: 95 },
      { field: 'student_id',              headerName: 'Student Id',     width: 120 },
      { field: 'student_name_eng',        headerName: 'Name (Eng)',     flex: 1, minWidth: 150 },
      { field: 'student_name_chn',        headerName: 'Name (Chn)',     width: 105 },
      { field: 'programme_leader',        headerName: 'Programme Leader', width: 130 },
      { field: 'department_admin_staff',  headerName: 'Dept Admin',     width: 115 },
      { field: 'counsellor',              headerName: 'Counsellor',     width: 115 },
      { field: 'undergraduate_studies_support_officer', headerName: 'USSO', width: 120 },
      { field: 'sen_type',                headerName: 'SEN Type',       width: 150 },
      { field: 'sen_detail',              headerName: 'SEN Detail',     flex: 1, minWidth: 140 },
      { field: 'special_support_required',        headerName: 'Support Required', flex: 1, minWidth: 140 },
      { field: 'special_examination_arrangement', headerName: 'Exam Arrangement', flex: 1, minWidth: 140 },
      { field: 'temporary_special_support',       headerName: 'Temp Support', width: 120 },
      { field: 'updated_at', headerName: 'Update Date', width: 150, sort: 'desc', valueFormatter: p => formatUpdateDate(p.value) },
      {
        field: 'sen_id',
        headerName: 'Actions',
        width: 175,
        sortable: false,
        pinned: 'right',
        cellRenderer: params => {
          const wrap = document.createElement('div');
          wrap.className = 'd-flex gap-1';

          // Edit is only shown to roles that may actually edit (SA/AU);
          // restricted roles (KS etc.) get View only.
          if (CAN_EDIT_SEN) {
            const editBtn = document.createElement('button');
            editBtn.className = 'btn-edit btn-edit-sm';
            editBtn.innerHTML = '<i class="bi bi-pencil-square me-1"></i>Edit';
            editBtn.addEventListener('click', () => {
              window.location.href = '/admin/create-sen?sen_id=' + encodeURIComponent(params.value);
            });
            wrap.appendChild(editBtn);
          }

          const viewBtn = document.createElement('button');
          viewBtn.className = 'btn-edit btn-edit-sm';
          viewBtn.innerHTML = '<i class="bi bi-eye me-1"></i>View';
          viewBtn.addEventListener('click', () => {
            window.location.href = '/admin/create-sen?sen_id=' + encodeURIComponent(params.value) + '&mode=view';
          });

          wrap.appendChild(viewBtn);
          return wrap;
        },
      },
    ],
    rowData: [],
    pagination: true,
    paginationPageSize: 8,
    paginationPageSizeSelector: false,
    defaultColDef: { sortable: true, resizable: true },
    getRowId: p => p.data.sen_id,
    onGridReady: (params) => { gridApi = params.api; },
    suppressCellFocus: true,
    loadingOverlayComponent: SenLoadingOverlay,
  };
  let gridApi;
  agGrid.createGrid(document.getElementById('senGrid'), gridOptions);

  /* ---------- theme sync ---------- */
  function applyGridTheme() {
    const el = document.getElementById('senGrid');
    const dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
    el.classList.toggle('ag-theme-alpine-dark', dark);
    el.classList.toggle('ag-theme-alpine', !dark);
  }
  applyGridTheme();
  document.getElementById('themeToggle')?.addEventListener('click', () => setTimeout(applyGridTheme, 60));

  /* ---------- Search ---------- */
  let lastResultCount = 0; // total rows of the last search (for the Export guard)
  document.getElementById('senSearchForm').addEventListener('submit', async e => {
    e.preventDefault();
    const fd = new FormData(e.target);
    const params = new URLSearchParams();
    for (const [k, v] of fd) { if (v !== '') params.set(k, v); }
    gridApi.showLoadingOverlay();
    try {
      const res = await fetch('/admin/sen-search/search?' + params.toString());
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const rows = await res.json();
      lastResultCount = rows.length;
      gridApi.setGridOption('rowData', rows);
      // no data -> dialog with an OK button (2026-08-19)
      if (!rows.length) {
        bootstrap.Modal.getOrCreateInstance(document.getElementById('noResultModal')).show();
      }
    } catch (err) {
      toast('❌ Search failed: ' + err.message);
    } finally {
      gridApi.hideOverlay();
    }
  });

  /* ---------- Export ---------- */
  document.getElementById('exportBtn').addEventListener('click', () => {
    if (!lastResultCount) {
      toast('⚠️ No search results to export');
      return;
    }
    const fd = new FormData(document.getElementById('senSearchForm'));
    const params = new URLSearchParams();
    for (const [k, v] of fd) { if (v !== '') params.set(k, v); }
    // anchor with download attribute -> the layout's loading overlay ignores it
    const a = document.createElement('a');
    a.href = '/admin/sen-search/export?' + params.toString();
    a.download = '';
    document.body.appendChild(a);
    a.click();
    a.remove();
  });

  /* ---------- Reset ---------- */
  document.getElementById('resetBtn').addEventListener('click', () => {
    document.getElementById('senSearchForm').reset();
    gridApi.setGridOption('rowData', []);
    lastResultCount = 0;
  });
</script>

@endsection
