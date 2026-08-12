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
    background: var(--accent-grad);
    color: #fff; font-weight: 600; font-size: .85rem;
    border: 0; border-radius: 10px; padding: .5rem 1.2rem;
    box-shadow: 0 4px 14px rgba(var(--accent-rgb), .3);
  }
  .btn-search:hover { color: #fff; filter: brightness(1.08); }
  .btn-search i { color: #fff; }

  .btn-cancel { border: 1px solid var(--border); color: var(--text-muted); background: transparent; font-size: .85rem; font-weight: 600; border-radius: 10px; padding: .5rem 1.2rem; }
  .btn-cancel:hover { background: var(--bg-soft); color: var(--text); }
  .btn-cancel i { color: var(--text-muted); }
  .btn-cancel:hover i { color: var(--text); }

  #senGrid {
    height: 62vh;
    border: 1px solid var(--card-border);
    border-radius: var(--radius);
    overflow: hidden;
    --ag-background-color: var(--card-bg);
    --ag-foreground-color: var(--text);
    --ag-border-color: var(--card-border);
    --ag-header-background-color: color-mix(in srgb, var(--bg-soft) 70%, transparent);
    --ag-header-foreground-color: var(--text-faint);
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

  .btn-edit { border: 1px solid rgba(var(--accent-rgb), .45); color: var(--accent); background: transparent; font-size: .85rem; font-weight: 600; border-radius: 10px; padding: .5rem 1.2rem; }
  .btn-edit:hover { background: var(--accent-soft); color: var(--accent); }
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
        @foreach ($staff['programme_leader'] as $s)
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
      <label class="form-label" for="fSO">SEN Officer</label>
      <select class="form-select" id="fSO" name="sen_officer">
        <option value="">-- Select --</option>
        @foreach ($staff['sen_officer'] as $s)
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
          <option value="{{ $t }}">{{ $t }}</option>
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

  /* ---------- AG Grid ---------- */
  const gridOptions = {
    columnDefs: [
      { field: 'sen_id',                  headerName: 'SEN Id',       width: 95 },
      { field: 'student_id',              headerName: 'Student Id',     width: 120 },
      { field: 'student_name_eng',        headerName: 'Name (Eng)',     flex: 1, minWidth: 150 },
      { field: 'student_name_chn',        headerName: 'Name (Chn)',     width: 105 },
      { field: 'programme_leader',        headerName: 'Programme Leader', width: 130 },
      { field: 'department_admin_staff',  headerName: 'Dept Admin',     width: 115 },
      { field: 'counsellor',              headerName: 'Counsellor',     width: 115 },
      { field: 'sen_officer',             headerName: 'SEN Officer',    width: 115 },
      { field: 'undergraduate_studies_support_officer', headerName: 'USSO', width: 120 },
      { field: 'sen_type',                headerName: 'SEN Type',       width: 150 },
      { field: 'sen_detail',              headerName: 'SEN Detail',     flex: 1, minWidth: 140 },
      { field: 'special_support_required',        headerName: 'Support Required', flex: 1, minWidth: 140 },
      { field: 'special_examination_arrangement', headerName: 'Exam Arrangement', flex: 1, minWidth: 140 },
      { field: 'temporary_special_support',       headerName: 'Temp Support', width: 120 },
      {
        field: 'sen_id',
        headerName: 'Actions',
        width: 100,
        sortable: false,
        cellRenderer: params => {
          const btn = document.createElement('button');
          btn.className = 'btn-edit';
          btn.innerHTML = '<i class="bi bi-pencil-square me-1"></i>Edit';
          btn.addEventListener('click', () => {
            window.location.href = '/admin/create-sen?sen_id=' + encodeURIComponent(params.value);
          });
          return btn;
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
    toast('Criteria reset');
  });
</script>

@endsection
