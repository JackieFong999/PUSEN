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

  #subjectGrid .ag-root-wrapper { border: none; }

  #subjectGrid {
    height: 520px;
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
  #subjectGrid .ag-header-cell-text { font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; }
  #subjectGrid .ag-cell { display: flex; align-items: center; }
  #subjectGrid .ag-paging-panel { font-size: .8rem; color: var(--text-muted); border-top: 1px solid var(--card-border); }

  /* ---------- loading overlay ---------- */
  #subjectGrid .ag-overlay { background: color-mix(in srgb, var(--card-bg) 45%, transparent); }
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
</style>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
  <div>
    <h1 class="mb-0" style="font-size:1.25rem;">Subject/Lecture List</h1>
  </div>
  <div style="font-size:.75rem; color:var(--text-faint);">
    <i class="bi bi-database me-1"></i>{{ count($rows) }} record(s) &middot; 10 per page
  </div>
</div>

{{-- ============ CRITERIA BAR ============ --}}
<div class="stat-card mb-2">
  <form id="subjectSearchForm" class="row g-3 align-items-end">
    <div class="col-md-3">
      <label class="form-label" for="fAcademicYear">Academic Year</label>
      <select class="form-select" id="fAcademicYear" name="academic_year">
        <option value="">-- All --</option>
        @foreach ($options['academicYears'] as $y)
          <option value="{{ $y }}">{{ $y }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label" for="fSubjectCode">Subject Code</label>
      <input type="text" class="form-control" id="fSubjectCode" name="subject_code"
             list="subjectCodeList" placeholder="e.g. AF3111" autocomplete="off">
      <datalist id="subjectCodeList">
        @foreach ($options['subjectCodes'] as $c)
          <option value="{{ $c }}"></option>
        @endforeach
      </datalist>
    </div>
    <div class="col-md-3">
      <label class="form-label" for="fTeacher">Teacher Staff ID</label>
      <input type="text" class="form-control" id="fTeacher" name="teacher_staff_id"
             placeholder="e.g. taylor01" autocomplete="off">
    </div>
    <div class="col-md-3">
      <label class="form-label" for="fSubjectType">Subject Type</label>
      <select class="form-select" id="fSubjectType" name="subject_type">
        <option value="">-- All --</option>
        @foreach ($options['subjectTypes'] as $t)
          <option value="{{ $t }}">{{ $t }}</option>
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
<div id="subjectGrid" class="ag-theme-alpine-dark"></div>

<script src="https://cdn.jsdelivr.net/npm/ag-grid-community@31/dist/ag-grid-community.min.js"></script>
<script nonce="{{ $cspNonce }}">
  /* ---------- loading overlay component (must be defined before gridOptions) ---------- */
  class SubjectLoadingOverlay {
    init() {
      this.eGui = document.createElement('div');
      this.eGui.className = 'grid-loading-overlay';
      this.eGui.innerHTML = `
        <div class="spinner-border" role="status" aria-hidden="true"></div>
        <div>Loading subjects.</div>
      `;
    }
    getGui() { return this.eGui; }
  }

  /* ---------- AG Grid ---------- */
  const INITIAL_ROWS = @json($rows);
  let gridApi = null;

  const gridOptions = {
    columnDefs: [
      { field: 'academicYear', headerName: 'Academic Year', width: 130, cellRenderer: p => p.value ?? '—' },
      { field: 'semester', headerName: 'Semester', width: 100, cellRenderer: p => p.value ?? '—' },
      { field: 'subjectCode', headerName: 'Subject Code', flex: 1 },
      { field: 'teacherStaffId', headerName: 'Teacher Staff ID', width: 160, cellRenderer: p => p.value ?? '—' },
      { field: 'subjectType', headerName: 'Subject Type', flex: 1, cellRenderer: p => p.value ?? '—' },
    ],
    rowData: INITIAL_ROWS,
    pagination: true,
    paginationPageSize: 10,
    paginationPageSizeSelector: false,
    defaultColDef: { sortable: true, resizable: true },
    getRowId: p => p.data.academicYear + '-' + p.data.semester + '-' + p.data.subjectCode + '-' + p.data.teacherStaffId,
    onGridReady: p => { gridApi = p.api; },
    suppressCellFocus: true,
    loadingOverlayComponent: SubjectLoadingOverlay,
  };

  const gridEl = document.getElementById('subjectGrid');
  agGrid.createGrid(gridEl, gridOptions);

  /* ---------- theme sync ---------- */
  function applyGridTheme() {
    const dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
    gridEl.classList.toggle('ag-theme-alpine-dark', dark);
    gridEl.classList.toggle('ag-theme-alpine', !dark);
  }
  applyGridTheme();
  document.getElementById('themeToggle')?.addEventListener('click', () => setTimeout(applyGridTheme, 60));

  /* ---------- search ---------- */
  document.getElementById('subjectSearchForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    const params = new URLSearchParams();
    for (const [k, v] of fd) {
      if (String(v).trim() !== '') params.append(k, String(v).trim());
    }
    try {
      gridApi.showLoadingOverlay();
      const res = await fetch('/admin/subject-list/search?' + params.toString(), { headers: { 'Accept': 'application/json' } });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const rows = await res.json();
      gridApi.setGridOption('rowData', rows);
    } catch (err) {
      toast('❌ Search failed: ' + err.message);
    } finally {
      gridApi.hideOverlay();
    }
  });

  /* ---------- reset ---------- */
  document.getElementById('resetBtn').addEventListener('click', () => {
    document.getElementById('subjectSearchForm').reset();
    gridApi.setGridOption('rowData', INITIAL_ROWS);
  });
</script>

@endsection
