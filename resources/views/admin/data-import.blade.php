@extends('layouts.app')

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
  .import-file { font-family: ui-monospace, SFMono-Regular, Consolas, monospace; font-size: .8rem; }
  .import-file .bi { color: var(--text-faint); }
  .badge-soon {
    font-size: .65rem; font-weight: 700; letter-spacing: .05em; text-transform: uppercase;
    color: var(--text-faint);
    border: 1px solid var(--border);
    border-radius: 99px; padding: .2rem .6rem;
    background: transparent;
  }
  .btn-import { background: #2563eb; border: 1px solid #1e40af; color: #fff; font-weight: 600; font-size: .85rem; border-radius: 10px; padding: .5rem 1.2rem; }
  .btn-import:hover { background: #16a34a; border-color: #15803d; color: #fff; }
  .btn-import:disabled { opacity: .45; filter: none; }
  .result-count { font-size: .95rem; }
  .result-count .num { font-weight: 800; font-size: 1.05rem; }
  /* wider confirm dialog so long filenames fit on one line */
  .modal-dialog-filename { max-width: 560px; }
  .modal-dialog-filename .modal-body { overflow-wrap: anywhere; }
  .num-insert { color: var(--success); }
  .num-update { color: var(--accent); }
  .num-dup    { color: #fbbf24; }
  .num-fail   { color: var(--danger); }
</style>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
  <div>
    <h1 class="mb-0" style="font-size:1.25rem;">Data Import</h1>
    <div class="text-muted" style="font-size:.85rem;">
      Import master data from the SFTP server. Files are picked from
      <span class="import-file"><i class="bi bi-folder2-open me-1"></i>upload/</span>
      and archived to <span class="import-file"><i class="bi bi-archive me-1"></i>processed/</span> after a successful import.
    </div>
  </div>
</div>

@if ($sftpError)
  <div class="alert alert-danger d-flex align-items-center gap-2 py-2" style="font-size:.85rem;">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <span>{{ $sftpError }}</span>
  </div>
@endif

<div class="stat-card p-0 overflow-hidden">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0" style="min-width: 720px;">
      <thead>
        <tr>
          <th>Import Function</th>
          <th style="width:140px;">Action</th>
          <th>Latest File in SFTP</th>
        </tr>
      </thead>
      <tbody>
        {{-- Active import functions --}}
        @foreach ($functions as $fn)
          <tr>
            <td>
              <div class="fw-semibold">{{ $fn['label'] }}</div>
              <div class="text-muted" style="font-size:.75rem;">{{ $fn['desc'] }}</div>
            </td>
            <td>
              <button type="button" class="btn btn-import btn-sm" data-type="{{ $fn['type'] }}"
                      @disabled(! $fn['ready'])>
                <i class="bi bi-download me-1"></i>Import
              </button>
            </td>
            <td>
              @if ($fn['ready'])
                <span class="import-file"><i class="bi bi-file-earmark-text me-1"></i>{{ $fn['file'] }}</span>
              @else
                <span class="import-file text-muted">
                  <i class="bi bi-dash-lg me-1"></i>
                  No new file in upload/
                </span>
                @if ($fn['last'])
                  <div class="text-muted mt-1" style="font-size:.72rem;">
                    Last imported: <span class="import-file">{{ $fn['last'] }}</span>
                  </div>
                @endif
              @endif
            </td>
          </tr>
        @endforeach

        {{-- Coming soon rows --}}
        @foreach ([
            ['Advisor List', '—'],
            ['Student Registration', '—'],
        ] as [$label, $table])
          <tr class="opacity-50">
            <td>
              <div class="fw-semibold">{{ $label }}</div>
              <div class="text-muted" style="font-size:.75rem;">{{ $table }}</div>
            </td>
            <td>
              <button type="button" class="btn btn-import btn-sm" disabled>
                <i class="bi bi-download me-1"></i>Import
              </button>
            </td>
            <td>
              <span class="badge-soon"><i class="bi bi-hourglass-split me-1"></i>Coming soon</span>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>

{{-- ============ CONFIRM DIALOG ============ --}}
<div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-filename">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title" id="confirmModalTitle">Confirm</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="confirmModalMsg"></div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-cancel" id="confirmNo">Cancel</button>
        <button type="button" class="btn btn-search" id="confirmYes">Import</button>
      </div>
    </div>
  </div>
</div>

{{-- ============ RESULT DIALOG ============ --}}
<div class="modal fade" id="resultModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title" id="resultModalTitle">Import Result</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="resultModalBody"></div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-search" data-bs-dismiss="modal">OK</button>
      </div>
    </div>
  </div>
</div>

<script>
  const CSRF = '{{ csrf_token() }}';
  const functions = @json($functions);

  const confirmModalEl = document.getElementById('confirmModal');
  const resultModalEl  = document.getElementById('resultModal');
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

  async function runImport(type) {
    const fn = functions.find(f => f.type === type);
    if (!fn || !fn.file) return;

    const ok = await askConfirm('Import Data', `Import "${fn.file}"?\n\n${fn.confirm}`);
    if (!ok) return;

    const btn = document.querySelector(`.btn-import[data-type="${type}"]`);
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Importing…';

    try {
      const res = await fetch('/admin/data-import/import', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': CSRF,
          'Accept': 'application/json',
        },
        body: JSON.stringify({ type, file: fn.file }),
      });
      const data = await res.json();

      if (data.status === 'success') {
        showResult('Import Completed', `
          <div class="result-count">
            <div class="mb-1"><span class="num num-insert">${data.inserted}</span> record(s) inserted</div>
            <div class="mb-1"><span class="num num-update">${data.updated}</span> record(s) updated</div>
            <div><span class="num num-dup">${data.duplicated}</span> record(s) duplicated</div>
          </div>
          <div class="text-muted mt-2" style="font-size:.78rem;">
            <i class="bi bi-check-circle me-1"></i>${data.archive_moved ? `File archived to processed/ as ${esc(data.archive_name ?? fn.file)}` : 'File NOT archived (check server)'}
          </div>
        `);
        // refresh the page so the file column updates (file moved to processed/)
        resultModalEl.addEventListener('hidden.bs.modal', () => location.reload(), { once: true });
      } else if (data.status === 'abort') {
        showResult('Import Aborted', `
          <div class="d-flex align-items-start gap-2">
            <i class="bi bi-x-octagon-fill text-danger mt-1"></i>
            <div>
              <div class="fw-semibold">${data.failures} error record(s) found in the CSV file.</div>
              <div class="text-muted" style="font-size:.85rem;">No records imported. The file stays in upload/ — fix the source data and try again.</div>
            </div>
          </div>
        `);
      } else {
        showResult('Import Error', `
          <div class="d-flex align-items-start gap-2">
            <i class="bi bi-exclamation-triangle-fill text-danger mt-1"></i>
            <div>
              <div class="fw-semibold">Something went wrong.</div>
              <div class="text-muted" style="font-size:.85rem;">${esc(data.message ?? 'Unknown error')}</div>
            </div>
          </div>
        `);
      }
    } catch (e) {
      showResult('Import Error', `<div class="fw-semibold">Request failed: ${esc(e.message)}</div>`);
    } finally {
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-download me-1"></i>Import';
    }
  }

  document.querySelectorAll('.btn-import[data-type]').forEach(btn =>
    btn.addEventListener('click', () => runImport(btn.dataset.type))
  );
</script>

@endsection
