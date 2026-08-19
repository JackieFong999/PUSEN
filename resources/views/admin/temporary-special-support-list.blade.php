@extends('layouts.app')

@section('content')

<link href="https://cdn.jsdelivr.net/npm/ag-grid-community@31/styles/ag-grid.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/ag-grid-community@31/styles/ag-theme-alpine.css" rel="stylesheet">

<style>
  .btn-del {
    border: 1px solid #7d1d29; color: #fff; background: #9B2331;
    font-size: .78rem; font-weight: 600;
    border-radius: 10px; padding: .38rem .9rem;
    line-height: 1.4; /* stop AG Grid cell line-height from inflating the button */
    white-space: nowrap;
  }
  .btn-del:hover { background: #d04553; border-color: #a02d38; color: #fff; }
  .btn-del i { color: #fff; }
  .btn-add {
    border: 1px solid #7d1d29; color: #fff; background: #9B2331;
    font-size: .85rem; font-weight: 600; border-radius: 10px; padding: .5rem 1.2rem;
    box-shadow: 0 4px 14px rgba(155, 35, 49, .3);
  }
  .btn-add:hover { background: #d04553; border-color: #a02d38; color: #fff; }
  .btn-add i { color: #fff; }
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
  .modal-content { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 14px; }
  .modal-content .modal-title { font-size: 1rem; font-weight: 600; color: var(--text); }
  .modal-content .modal-body { font-size: .88rem; color: var(--text); }

  /* ---------- AG Grid: same look as the SEN Search grid ---------- */
  #supportGrid .ag-root-wrapper { border: none; }

  #supportGrid {
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
  #supportGrid .ag-header-cell-text { font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; }
  #supportGrid .ag-cell { display: flex; align-items: center; }
  #supportGrid .ag-paging-panel { font-size: .8rem; color: var(--text-muted); border-top: 1px solid var(--card-border); }
</style>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
  <div>
    <h1 class="mb-0" style="font-size:1.25rem;">Temporary Special Support</h1>
  </div>
  <div style="font-size:.75rem; color:var(--text-faint);">
    <i class="bi bi-database me-1"></i>{{ count($supports) }} record(s) &middot; 10 per page
  </div>
</div>

{{-- ============ AG GRID LIST ============ --}}
<div class="stat-card p-3">
  <div id="supportGrid" class="ag-theme-alpine-dark"></div>
</div>

{{-- ============ ADD NEW ENTRY (below the list) ============ --}}
<div class="stat-card p-3 mt-4">
  <div style="font-size:.72rem; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#000; margin-bottom:.6rem;">Add New Temporary Special Support</div>
  <div class="d-flex gap-2 align-items-center">
    <input type="text" id="newSupport" class="form-control" maxlength="40"
           placeholder="Enter a new value…" style="max-width:420px;">
    <button type="button" id="addSupportBtn" class="btn btn-add"><i class="bi bi-plus-lg me-1"></i>Save</button>
  </div>
</div>

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
        <button type="button" class="btn btn-del" id="confirmNo" style="border-color:var(--border); background:var(--bg-soft); color:var(--text-muted);">Cancel</button>
        <button type="button" class="btn btn-add" id="confirmYes" style="background:#dc2626; border-color:#b91c1c;">Delete</button>
      </div>
    </div>
  </div>
</div>

{{-- ============ INFO DIALOG (delete blocked because the value is in use) ============ --}}
<div class="modal fade" id="infoModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title"><i class="bi bi-info-circle me-1" style="color:var(--danger);"></i>Cannot Delete</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="infoModalMsg"></div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-add" id="infoOk">OK</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/ag-grid-community@31/dist/ag-grid-community.min.js"></script>
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

  /* ---------- info dialog (delete blocked, e.g. value already in use) ---------- */
  const infoModalEl = document.getElementById('infoModal');
  function showInfo(message) {
    document.getElementById('infoModalMsg').textContent = message;
    bootstrap.Modal.getOrCreateInstance(infoModalEl).show();
  }
  document.getElementById('infoOk').addEventListener('click', () => bootstrap.Modal.getOrCreateInstance(infoModalEl).hide());

  function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  }

  /* ---------- AG Grid ---------- */
  const ROWS = @json($supports->map(fn ($v) => ['support' => $v]));
  let gridApi = null;

  const gridOptions = {
    columnDefs: [
      {
        field: 'support',
        headerName: 'Temporary Special Support',
        flex: 1,
        cellRenderer: p => esc(p.value),
      },
      {
        field: 'support',
        headerName: 'Actions',
        width: 110,
        sortable: false,
        pinned: 'right',
        cellRenderer: params => {
          const btn = document.createElement('button');
          btn.type = 'button';
          btn.className = 'btn-del';
          btn.innerHTML = '<i class="bi bi-trash3 me-1"></i>Delete';
          btn.addEventListener('click', () => deleteSupport(params.node.data));
          return btn;
        },
      },
    ],
    rowData: ROWS,
    pagination: true,
    paginationPageSize: 10,
    paginationPageSizeSelector: false,
    defaultColDef: { sortable: true, resizable: true },
    getRowId: p => p.data.support,
    onGridReady: p => { gridApi = p.api; },
  };

  const gridEl = document.getElementById('supportGrid');
  agGrid.createGrid(gridEl, gridOptions);

  /* ---------- theme sync ---------- */
  function applyGridTheme() {
    const dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
    gridEl.classList.toggle('ag-theme-alpine-dark', dark);
    gridEl.classList.toggle('ag-theme-alpine', !dark);
  }
  applyGridTheme();
  document.getElementById('themeToggle')?.addEventListener('click', () => setTimeout(applyGridTheme, 60));

  /* ---------- delete (with usage check on the server) ---------- */
  async function deleteSupport(row) {
    const ok = await askConfirm('Delete Temporary Special Support', 'Delete "' + row.support + '"?\n\nA value still used by SEN cases cannot be deleted.');
    if (!ok) return;

    try {
      const res = await fetch('/admin/temporary-special-support-list/delete', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
        body: JSON.stringify({ support: row.support }),
      });
      const json = await res.json();
      if (json.success) {
        gridApi.applyTransaction({ remove: [row] });
        toast('🗑️ Deleted: ' + row.support);
      } else {
        // blocked deletes (e.g. value still used by SEN cases) -> dialog with the message
        showInfo(json.message || 'Delete failed');
      }
    } catch (err) {
      toast('❌ Delete failed: ' + err.message);
    }
  }

  /* ---------- add new entry (unique value) ---------- */
  document.getElementById('addSupportBtn').addEventListener('click', async () => {
    const input = document.getElementById('newSupport');
    const value = input.value.trim();
    if (!value) { toast('⚠️ Please enter a value'); input.focus(); return; }

    try {
      const res = await fetch('/admin/temporary-special-support-list/store', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
        body: JSON.stringify({ support: value }),
      });
      const json = await res.json();
      if (json.success) {
        gridApi.applyTransaction({ add: [{ support: json.support }] });
        input.value = '';
        toast('✅ Added: ' + json.support);
      } else {
        toast('❌ ' + (json.message || 'Save failed'));
      }
    } catch (err) {
      toast('❌ Save failed: ' + err.message);
    }
  });

  // Enter key in the input also saves
  document.getElementById('newSupport').addEventListener('keydown', e => {
    if (e.key === 'Enter') { e.preventDefault(); document.getElementById('addSupportBtn').click(); }
  });
</script>

@endsection
