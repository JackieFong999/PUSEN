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
  /* override global .stat-card i rule: icon must match button text color */
  .btn-search i { color: #fff; }

  .btn-cancel { border: 1px solid var(--border); color: var(--text-muted); background: transparent; font-size: .85rem; font-weight: 600; border-radius: 10px; padding: .5rem 1.2rem; }
  .btn-cancel:hover { background: var(--bg-soft); color: var(--text); }
  .btn-cancel i { color: var(--text-muted); }
  .btn-cancel:hover i { color: var(--text); }

  #studentGrid {
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
  #studentGrid .ag-header-cell-text { font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; }
  #studentGrid .ag-cell { display: flex; align-items: center; }
  #studentGrid .ag-paging-panel { font-size: .8rem; color: var(--text-muted); border-top: 1px solid var(--card-border); }

  /* ---------- loading overlay ---------- */
  #studentGrid .ag-overlay {
    background: color-mix(in srgb, var(--card-bg) 45%, transparent);
  }
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

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-3" style="margin-top:-1.5rem; margin-bottom:.75rem;">
  <div>
    <h1 class="mb-0" style="font-size:1.25rem;">Student List</h1>
  </div>
</div>

{{-- ============ CRITERIA BAR ============ --}}
<div class="stat-card mb-2">
  <form id="studentSearchForm" class="row g-3 align-items-end">
    <div class="col-md-3">
      <label class="form-label" for="fNameEng">Student Name (Eng)</label>
      <input type="text" class="form-control" id="fNameEng" name="student_name_eng" placeholder="e.g. CHEN">
    </div>
    <div class="col-md-3">
      <label class="form-label" for="fNameChn">Student Name (Chn)</label>
      <input type="text" class="form-control" id="fNameChn" name="student_name_chn" placeholder="e.g. 陳">
    </div>
    <div class="col-md-3">
      <label class="form-label" for="fFaculty">Faculty</label>
      <select class="form-select" id="fFaculty" name="faculty">
        <option value="">-- Select Faculty --</option>
        @foreach ($faculties as $f)
          <option value="{{ $f }}">{{ $f }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label" for="fDepartment">Department</label>
      <select class="form-select" id="fDepartment" name="department">
        <option value="">-- Select Department --</option>
        @foreach ($departments as $d)
          <option value="{{ $d }}">{{ $d }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label" for="fFundType">Fund Type</label>
      <select class="form-select" id="fFundType" name="fund_type_code">
        <option value="">-- Select Fund Type --</option>
        @foreach ($fundTypes as $ft)
          <option value="{{ $ft->Fund_Type_Code }}">{{ $ft->Fund_Type_Code }} — {{ $ft->Fund_Status }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label" for="fStatus">Student Status</label>
      <select class="form-select" id="fStatus" name="student_status">
        <option value="">-- Select Status --</option>
        @foreach ($studentStatuses as $s)
          <option value="{{ $s }}">{{ $s }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-6 d-flex gap-2">
      <button type="submit" class="btn btn-search"><i class="bi bi-search me-1"></i>Search</button>
      <button type="button" id="resetBtn" class="btn btn-cancel"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset</button>
    </div>
  </form>
</div>

{{-- ============ AG GRID ============ --}}
<div id="studentGrid" class="ag-theme-alpine-dark"></div>

<script src="https://cdn.jsdelivr.net/npm/ag-grid-community@31/dist/ag-grid-community.min.js"></script>
<script>
  /* ---------- loading overlay component (must be defined before gridOptions) ---------- */
  class StudentLoadingOverlay {
    init() {
      this.eGui = document.createElement('div');
      this.eGui.className = 'grid-loading-overlay';
      this.eGui.innerHTML = `
        <div class="spinner-border" role="status" aria-hidden="true"></div>
        <div>Loading students…</div>
      `;
    }
    getGui() { return this.eGui; }
  }

  /* ---------- AG Grid ---------- */
  const gridOptions = {
    columnDefs: [
      { field: 'student_id',       headerName: 'Student Id',       width: 120 },
      { field: 'student_name_eng', headerName: 'Name (Eng)',       flex: 1, minWidth: 160 },
      { field: 'student_name_chn', headerName: 'Name (Chn)',       width: 110 },
      { field: 'faculty',          headerName: 'Faculty',          width: 110 },
      { field: 'department',       headerName: 'Department',       width: 135 },
      { field: 'prog_sub_code',    headerName: 'Prog Sub Code',    width: 130 },
      { field: 'prog_title',       headerName: 'Prog Title',       flex: 1.4, minWidth: 220 },
      {
        field: 'fund_type_code',
        headerName: 'Fund Type',
        width: 150,
        valueFormatter: p => p.value ? `${p.value} — ${p.data.fund_type_desc ?? ''}`.trim() : '',
      },
      { field: 'student_status',   headerName: 'Student Status',   width: 140 },
    ],
    rowData: [],
    pagination: true,
    paginationPageSize: 8,
    paginationPageSizeSelector: false,
    defaultColDef: { sortable: true, resizable: true },
    getRowId: p => p.data.id,
    onGridReady: (params) => { gridApi = params.api; },
    suppressCellFocus: true,
    loadingOverlayComponent: StudentLoadingOverlay,
  };
  let gridApi;
  agGrid.createGrid(document.getElementById('studentGrid'), gridOptions);

  /* ---------- theme sync with app toggle ---------- */
  function applyGridTheme() {
    const el = document.getElementById('studentGrid');
    const dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
    el.classList.toggle('ag-theme-alpine-dark', dark);
    el.classList.toggle('ag-theme-alpine', !dark);
  }
  applyGridTheme();
  document.getElementById('themeToggle')?.addEventListener('click', () => setTimeout(applyGridTheme, 60));

  /* ---------- Search ---------- */
  document.getElementById('studentSearchForm').addEventListener('submit', async e => {
    e.preventDefault();
    const fd = new FormData(e.target);
    const params = new URLSearchParams();
    for (const [k, v] of fd) { if (v !== '') params.set(k, v); }
    gridApi.showLoadingOverlay(); // spinner in the middle of the grid while fetching
    try {
      const res = await fetch('/admin/student-list/search?' + params.toString());
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const rows = await res.json();
      gridApi.setGridOption('rowData', rows);
    } catch (err) {
      toast('❌ Search failed: ' + err.message);
    } finally {
      gridApi.hideOverlay();
    }
  });

  /* ---------- Reset ---------- */
  document.getElementById('resetBtn').addEventListener('click', () => {
    document.getElementById('studentSearchForm').reset();
    gridApi.setGridOption('rowData', []);
    toast('Criteria reset');
  });
</script>

@endsection
