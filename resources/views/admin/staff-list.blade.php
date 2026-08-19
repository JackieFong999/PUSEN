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
  /* override global .stat-card i rule: icon must match button text color */
  .btn-search i { color: #fff; }

  #staffGrid .ag-root-wrapper { border: none; }

  #staffGrid {
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
  #staffGrid .ag-header-cell-text { font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; }
  #staffGrid .ag-cell { display: flex; align-items: center; }
  #staffGrid .ag-paging-panel { font-size: .8rem; color: var(--text-muted); border-top: 1px solid var(--card-border); }

  /* ---------- loading overlay ---------- */
  #staffGrid .ag-overlay {
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

  .row-actions .btn { font-size: .75rem; font-weight: 600; border-radius: 8px; padding: .28rem .75rem; }
  .btn-edit { border: 1px solid #7d1d29; color: #fff; background: #9B2331; font-size: .85rem; font-weight: 600; border-radius: 10px; padding: .5rem 1.2rem; }
  .btn-edit:hover { background: #d04553; border-color: #a02d38; color: #fff; }
  .btn-save { background: #9B2331; border: 1px solid #7d1d29; color: #fff; font-size: .85rem; font-weight: 600; border-radius: 10px; padding: .5rem 1.2rem; }
  .btn-save:hover { background: #d04553; border-color: #a02d38; color: #fff; }
  .btn-cancel { border: 1px solid #7d1d29; color: #fff; background: #9B2331; font-size: .85rem; font-weight: 600; border-radius: 10px; padding: .5rem 1.2rem; }
  .btn-cancel:hover { background: #d04553; border-color: #a02d38; color: #fff; }
  .btn-cancel i { color: #fff; }
  .btn-cancel:hover i { color: #fff; }
</style>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-3" style="margin-top:-1.5rem; margin-bottom:.75rem;">
  <div>
    <h1 class="mb-0" style="font-size:1.25rem;">Staff List</h1>
  </div>
</div>

{{-- ============ CRITERIA BAR ============ --}}
<div class="stat-card mb-2">
  <form id="staffSearchForm" class="row g-3 align-items-end">
    <div class="col-md-3">
      <label class="form-label" for="fStaffId">Staff Id</label>
      <input type="text" class="form-control" id="fStaffId" name="staff_id" placeholder="e.g. alex01">
    </div>
    <div class="col-md-3">
      <label class="form-label" for="fStaffName">Staff Name</label>
      <input type="text" class="form-control" id="fStaffName" name="staff_name" placeholder="e.g. Alex Wong">
    </div>
    <div class="col-md-3">
      <label class="form-label" for="fDisplayName">Display Name</label>
      <input type="text" class="form-control" id="fDisplayName" name="display_name" placeholder="e.g. Alex">
    </div>
    <div class="col-md-3">
      <label class="form-label" for="fRoleId">Role Id</label>
      <select class="form-select" id="fRoleId" name="role_id">
        <option value="">-- Select Role --</option>
        @foreach ($roles as $r)
          <option value="{{ $r->Role_Id }}">{{ $r->Role_Id }} — {{ $r->Role_Desc }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label" for="fTargetUserId">Target User Id</label>
      <select class="form-select" id="fTargetUserId" name="target_user_id">
        <option value="">-- Select Target User --</option>
        @foreach ($targetUsers as $tu)
          <option value="{{ $tu->Target_User_Id }}">{{ $tu->Target_User_Id }} — {{ $tu->Target_User_Desc }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label" for="fStatus">Status</label>
      <select class="form-select" id="fStatus" name="status">
        <option value="">-- Select Status --</option>
        <option value="0">0 — Enable</option>
        <option value="1">1 — Disable</option>
      </select>
    </div>
    <div class="col-md-6 d-flex gap-2">
      <button type="submit" class="btn btn-search"><i class="bi bi-search me-1"></i>Search</button>
      <button type="button" id="resetBtn" class="btn btn-cancel"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset</button>
    </div>
  </form>
</div>

{{-- ============ AG GRID ============ --}}
<div id="staffGrid" class="ag-theme-alpine-dark"></div>

<script>
  const csrfToken = '{{ csrf_token() }}';
</script>
<script src="https://cdn.jsdelivr.net/npm/ag-grid-community@31/dist/ag-grid-community.min.js"></script>
<script>
  const statusMap  = { 0: 'Enable', 1: 'Disable' };
  const statusRev  = { Enable: 0, Disable: 1 };

  /* ---------- loading overlay component (must be defined before gridOptions) ---------- */
  class StaffLoadingOverlay {
    init() {
      this.eGui = document.createElement('div');
      this.eGui.className = 'grid-loading-overlay';
      this.eGui.innerHTML = `
        <div class="spinner-border" role="status" aria-hidden="true"></div>
        <div>Loading staff…</div>
      `;
    }
    getGui() { return this.eGui; }
  }

  /* ---------- AG Grid ---------- */
  const gridOptions = {
    columnDefs: [
      { field: 'staff_id',           headerName: 'Staff Id',      width: 110 },
      { field: 'staff_name',         headerName: 'Staff Name',    flex: 1, minWidth: 150 },
      { field: 'staff_display_name', headerName: 'Display Name',  flex: 1, minWidth: 120 },
      { field: 'role_id',            headerName: 'Role Id',       width: 90 },
      { field: 'target_user_id',     headerName: 'Target User Id', width: 130 },
      {
        field: 'status',
        headerName: 'Status',
        width: 130,
        editable: p => p.data._editing === true,
        cellEditor: 'agSelectCellEditor',
        cellEditorParams: { values: ['Enable', 'Disable'] },
        cellClassRules: {
          'text-success fw-semibold': p => p.value === 'Enable',
          'text-danger fw-semibold':  p => p.value === 'Disable',
        },
      },
      {
        headerName: 'Actions',
        width: 160,
        sortable: false,
        cellRenderer: params => {
          const d = params.data;
          if (d._editing) {
            return `<div class="row-actions d-flex gap-1">
              <button class="btn btn-save"   onclick="saveRow('${d.staff_id}')">Save</button>
              <button class="btn btn-cancel" onclick="cancelRow('${d.staff_id}')">Cancel</button>
            </div>`;
          }
          return `<div class="row-actions d-flex gap-1">
            <button class="btn btn-edit" onclick="editRow('${d.staff_id}')">Edit</button>
          </div>`;
        },
      },
    ],
    rowData: [],
    pagination: true,
    paginationPageSize: 8,
    paginationPageSizeSelector: false,
    defaultColDef: { sortable: true, resizable: true },
    singleClickEdit: true,
    stopEditingWhenCellsLoseFocus: true,
    getRowId: p => p.data.staff_id,
    onGridReady: (params) => { gridApi = params.api; },
    suppressCellFocus: true,
    loadingOverlayComponent: StaffLoadingOverlay,
  };
  let gridApi;
  agGrid.createGrid(document.getElementById('staffGrid'), gridOptions);

  /* ---------- theme sync with app toggle ---------- */
  function applyGridTheme() {
    const el = document.getElementById('staffGrid');
    const dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
    el.classList.toggle('ag-theme-alpine-dark', dark);
    el.classList.toggle('ag-theme-alpine', !dark);
  }
  applyGridTheme();
  document.getElementById('themeToggle')?.addEventListener('click', () => setTimeout(applyGridTheme, 60));

  /* ---------- Search ---------- */
  document.getElementById('staffSearchForm').addEventListener('submit', async e => {
    e.preventDefault();
    const fd = new FormData(e.target);
    const params = new URLSearchParams();
    for (const [k, v] of fd) { if (v !== '') params.set(k, v); }
    gridApi.showLoadingOverlay(); // spinner in the middle of the grid while fetching
    try {
      const res = await fetch('/admin/staff-list/search?' + params.toString());
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const rows = await res.json();
      gridApi.setGridOption('rowData', rows.map(r => ({
        ...r,
        status: statusMap[r.status] ?? r.status,
        _editing: false,
      })));
    } catch (err) {
      toast('❌ Search failed: ' + err.message);
    } finally {
      gridApi.hideOverlay();
    }
  });

  /* ---------- Reset ---------- */
  document.getElementById('resetBtn').addEventListener('click', () => {
    document.getElementById('staffSearchForm').reset();
    gridApi.setGridOption('rowData', []);
    toast('Criteria reset');
  });

  /* ---------- Row edit actions ---------- */
  function editRow(staffId) {
    const node = gridApi.getRowNode(staffId);
    if (!node) return;
    node.data._origStatus = node.data.status;
    node.data._editing = true;
    gridApi.refreshCells({ rowNodes: [node], force: true });
    // wait for the re-render, then open the status editor
    setTimeout(() => {
      gridApi.startEditingCell({ rowIndex: node.rowIndex, colKey: 'status' });
    }, 100);
  }

  async function saveRow(staffId) {
    const node = gridApi.getRowNode(staffId);
    if (!node) return;
    gridApi.stopEditing();
    const payload = { id: node.data.id, status: statusRev[node.data.status] };
    try {
      const res = await fetch('/admin/staff-list/update-status', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
        body: JSON.stringify(payload),
      });
      const json = await res.json();
      if (json.success) {
        node.data._editing = false;
        gridApi.refreshCells({ rowNodes: [node], force: true });
        toast('✅ Status saved for ' + staffId);
      } else {
        toast('❌ ' + (json.message || 'Save failed'));
      }
    } catch (err) {
      toast('❌ Save failed: ' + err.message);
    }
  }

  function cancelRow(staffId) {
    const node = gridApi.getRowNode(staffId);
    if (!node) return;
    gridApi.stopEditing(false);
    node.data.status = node.data._origStatus;
    node.data._editing = false;
    gridApi.refreshCells({ rowNodes: [node], force: true });
    toast('Change discarded');
  }
</script>

@endsection
