@extends('layouts.app')

@push('head')
  <link href="https://cdn.jsdelivr.net/npm/ag-grid-community@31.3.4/styles/ag-grid.css" rel="stylesheet" integrity="sha384-LNcL0K2K7L8L9H0XdFjFVke0Q1STyt3EhtpjMIai3xF3YpjvIOIoQlplKoTiaCS0" crossorigin="anonymous">
  <link href="https://cdn.jsdelivr.net/npm/ag-grid-community@31.3.4/styles/ag-theme-alpine.css" rel="stylesheet" integrity="sha384-kYz5+ibE+6jW5uFDveyCHnWtCKom1rUsq6SjbxD+EGAXkVIjLGi103ZL7WqhLGPC" crossorigin="anonymous">
@endpush

@section('content')

<style nonce="{{ $cspNonce }}">
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
  .form-control[readonly], .form-control:disabled { opacity: .6; }

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

  .btn-add { background: #9B2331; color: #fff; font-weight: 600; font-size: .85rem; border: 1px solid #7d1d29; border-radius: 10px; padding: .5rem 1.2rem; box-shadow: 0 4px 14px rgba(155, 35, 49, .3); }
  .btn-add:hover { background: #d04553; border-color: #a02d38; color: #fff; }
  .btn-add i { color: #fff; }

  .btn-edit { border: 1px solid #7d1d29; color: #fff; background: #9B2331; font-size: .85rem; font-weight: 600; border-radius: 10px; padding: .5rem 1.2rem; }
  .btn-edit:hover { background: #d04553; border-color: #a02d38; color: #fff; }

  /* grid action button — same style as the SEN Type Delete button (.btn-del) */
  .btn-edit-sm {
    border: 1px solid #7d1d29; color: #fff; background: #9B2331;
    font-size: .78rem; font-weight: 600;
    border-radius: 10px; padding: .38rem .9rem;
    line-height: 1.4; /* stop AG Grid cell line-height from inflating the button */
    white-space: nowrap;
  }
  .btn-edit-sm:hover { background: #d04553; border-color: #a02d38; color: #fff; }
  .btn-edit-sm i { color: #fff; }

  #emailGrid .ag-root-wrapper { border: none; }

  #emailGrid {
    height: 32vh;
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
  #emailGrid .ag-header-cell-text { font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; }
  #emailGrid .ag-cell { display: flex; align-items: center; }
  #emailGrid .ag-paging-panel { font-size: .8rem; color: var(--text-muted); border-top: 1px solid var(--card-border); }
  /* tooltip (Tips): blue border instead of the default gray */
  #emailGrid .ag-tooltip {
    border: 2.5px solid #0d6efd !important;
    border-radius: 8px;
    box-shadow: 0 4px 14px rgba(13, 110, 253, .28);
  }

  /* ---------- loading overlay ---------- */
  #emailGrid .ag-overlay { background: color-mix(in srgb, var(--card-bg) 45%, transparent); }
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

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-3 u-mt-neg-15 u-mb-075">
  <div>
    <h1 class="mb-0 u-fs-125">Email Template</h1>
  </div>
</div>

{{-- ============ AG GRID ============ --}}
<div id="emailGrid" class="ag-theme-alpine-dark"></div>

{{-- ============ ADD BUTTON ============ --}}
<div class="mt-3 mb-2">
  <button type="button" id="addBtn" class="btn btn-add"><i class="bi bi-plus-lg me-1"></i>Add Email Template</button>
</div>

{{-- ============ EDIT GROUP ============ --}}
<div class="stat-card mb-3">
  <form id="emailForm" autocomplete="off">
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label" for="fName">Template Name <span class="text-danger">*</span></label>
        <input type="text" class="form-control" id="fName" name="template_name" maxlength="50" placeholder="e.g. SEN-Notification" disabled>
      </div>
      <div class="col-md-6">
        <label class="form-label" for="fTitle">Template Title <span class="text-danger">*</span></label>
        <input type="text" class="form-control" id="fTitle" name="template_title" maxlength="100" placeholder="e.g. SEN Case Update" disabled>
      </div>
      <div class="col-12">
        <label class="form-label" for="fContent">Template Content <span class="text-danger">*</span></label>
        <textarea class="form-control" id="fContent" name="template_content" rows="7" placeholder="Dear Student, ..." disabled></textarea>
      </div>
      <div class="col-12">
        <label class="form-label" for="fRemarks">Template Remarks</label>
        <textarea class="form-control" id="fRemarks" name="template_remarks" rows="3" maxlength="255" placeholder="e.g. Auto-sent on SEN edit" disabled></textarea>
      </div>
    </div>
    <div class="d-flex gap-2 mt-3">
      <button type="button" id="saveBtn" class="btn btn-search"><i class="bi bi-check-lg me-1"></i>Save</button>
      <button type="button" id="cancelBtn" class="btn btn-cancel"><i class="bi bi-x-lg me-1"></i>Cancel</button>
    </div>
  </form>
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
        <button type="button" class="btn btn-cancel" id="confirmNo">Cancel</button>
        <button type="button" class="btn btn-search" id="confirmYes">Confirm</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/ag-grid-community@31.3.4/dist/ag-grid-community.min.noStyle.js" integrity="sha384-s1Ok/d+HoxfYayi4FqY2BuIVIwTHcD2tlc+xGlfbNgeKOkC+L3Mh6yvcfgODPrvU" crossorigin="anonymous"></script>
<script nonce="{{ $cspNonce }}">
  /* ---------- loading overlay component (must be defined before gridOptions) ---------- */
  class EmailLoadingOverlay {
    init() {
      this.eGui = document.createElement('div');
      this.eGui.className = 'grid-loading-overlay';
      this.eGui.innerHTML = `
        <div class="spinner-border" role="status" aria-hidden="true"></div>
        <div>Loading email templates…</div>
      `;
    }
    getGui() { return this.eGui; }
  }

  /* ---------- confirm dialog ---------- */
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
  let editingId = null; // null = create mode

  /* ---------- AG Grid ---------- */
  const gridOptions = {
    columnDefs: [
      { field: 'id',               headerName: 'Id',               width: 70 },
      { field: 'template_name',    headerName: 'Template Name',    width: 180 },
      { field: 'template_title',   headerName: 'Template Title',   width: 400 },
      { field: 'template_content', headerName: 'Content',          flex: 1.6, minWidth: 320, tooltipField: 'template_content' },
      { field: 'template_remarks', headerName: 'Remarks',          width: 600, tooltipField: 'template_remarks' },
      {
        field: 'id',
        headerName: 'Actions',
        width: 110,
        sortable: false,
        pinned: 'right',
        cellRenderer: params => {
          const btn = document.createElement('button');
          btn.className = 'btn-edit-sm';
          btn.innerHTML = '<i class="bi bi-pencil-square me-1"></i>Edit';
          btn.addEventListener('click', () => loadToForm(params.data));
          return btn;
        },
      },
    ],
    rowData: [],
    pagination: true,
    paginationPageSize: 10,
    paginationPageSizeSelector: false,
    defaultColDef: { sortable: true, resizable: true },
    getRowId: p => String(p.data.id),
    onGridReady: (params) => { gridApi = params.api; loadGrid(); },
    suppressCellFocus: true,
    loadingOverlayComponent: EmailLoadingOverlay,
  };
  let gridApi;
  agGrid.createGrid(document.getElementById('emailGrid'), gridOptions);

  /* ---------- theme sync ---------- */
  function applyGridTheme() {
    const el = document.getElementById('emailGrid');
    const dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
    el.classList.toggle('ag-theme-alpine-dark', dark);
    el.classList.toggle('ag-theme-alpine', !dark);
  }
  applyGridTheme();
  document.getElementById('themeToggle')?.addEventListener('click', () => setTimeout(applyGridTheme, 60));

  async function loadGrid() {
    gridApi.showLoadingOverlay();
    try {
      const res = await fetch('/admin/email-template-list/data');
      const rows = await res.json();
      gridApi.setGridOption('rowData', rows);
    } catch (err) {
      toast('❌ Load failed: ' + err.message);
    } finally {
      gridApi.hideOverlay();
    }
  }

  /* ---------- form mode ---------- */
  function setFormDisabled(disabled) {
    ['fName', 'fTitle', 'fContent', 'fRemarks'].forEach(id => {
      document.getElementById(id).disabled = disabled;
    });
  }

  function resetForm() {
    editingId = null;
    document.getElementById('emailForm').reset();
    setFormDisabled(true); // fields stay disabled until Add/Edit is pressed
  }

  function loadToForm(row) {
    editingId = row.id;
    setFormDisabled(false); // enable fields for editing
    document.getElementById('fName').value = row.template_name ?? '';
    document.getElementById('fTitle').value = row.template_title ?? '';
    document.getElementById('fContent').value = row.template_content ?? '';
    document.getElementById('fRemarks').value = row.template_remarks ?? '';
    document.getElementById('fName').disabled = true; // name immutable on modify
    document.getElementById('fTitle').focus();
    document.getElementById('emailForm').scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  /* ---------- Add button ---------- */
  document.getElementById('addBtn').addEventListener('click', () => {
    resetForm();
    setFormDisabled(false); // enable the fields for a new template
    document.getElementById('fName').focus();
    document.getElementById('emailForm').scrollIntoView({ behavior: 'smooth', block: 'start' });
  });

  /* ---------- Save ---------- */
  document.getElementById('saveBtn').addEventListener('click', async () => {
    const name = document.getElementById('fName').value.trim();
    const title = document.getElementById('fTitle').value.trim();
    const content = document.getElementById('fContent').value.trim();

    if (!name) { toast('⚠️ Template Name is required'); document.getElementById('fName').focus(); return; }
    if (!title) { toast('⚠️ Template Title is required'); document.getElementById('fTitle').focus(); return; }
    if (!content) { toast('⚠️ Template Content is required'); document.getElementById('fContent').focus(); return; }

    const isEdit = editingId !== null;
    const ok = await askConfirm(
      isEdit ? 'Save changes' : 'Save template',
      isEdit ? 'Update email template "' + name + '"?' : 'Save email template "' + name + '"?'
    );
    if (!ok) return;

    const payload = {
      template_name: name,
      template_title: title,
      template_content: content,
      template_remarks: document.getElementById('fRemarks').value.trim(),
    };
    if (isEdit) payload.id = editingId;

    try {
      const res = await fetch('/admin/email-template-list/save', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
        body: JSON.stringify(payload),
      });
      const json = await res.json();
      if (json.success) {
        toast(json.mode === 'update' ? '✅ Template updated' : '✅ Template saved');
        resetForm();
        await loadGrid();
      } else {
        toast('❌ ' + (json.message || 'Save failed'));
      }
    } catch (err) {
      toast('❌ Save failed: ' + err.message);
    }
  });

  /* ---------- Cancel ---------- */
  document.getElementById('cancelBtn').addEventListener('click', async () => {
    const ok = await askConfirm('Cancel', 'Discard changes and reset the form?');
    if (!ok) return;
    resetForm();
    toast('Form reset');
  });
</script>

@endsection
