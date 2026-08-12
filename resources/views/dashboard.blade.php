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
    padding: .75rem 1.1rem;
    font-size: .875rem;
    color: var(--text);
  }
  .table-hover tbody tr:hover { background: var(--accent-soft); }
</style>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
    <div>
        <h1 class="mb-0" style="font-size:1.25rem;">Dashboard</h1>
    </div>
</div>

{{-- ============ SUBJECT IMPORT LOGGING ============ --}}
<div class="stat-card p-0 overflow-hidden">
  <div class="d-flex align-items-center justify-content-between px-3 pt-3 pb-2">
    <span style="font-size:.72rem; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:var(--text-muted);">Subject Logging</span>
    <span style="font-size:.75rem; color:var(--text-faint);">Latest 50</span>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0" style="min-width: 1150px;">
      <thead>
        <tr>
          <th>Import Time</th>
          <th>File Name</th>
          <th>File Type</th>
          <th>Import Status</th>
          <th>CSV Rows</th>
          <th>Imported</th>
          <th>Updated</th>
          <th>Duplicated</th>
          <th>Errors</th>
          <th>Import By</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($subjectLogs as $log)
          <tr>
            <td>{{ $log->created_at ? \Carbon\Carbon::parse($log->created_at)->format('Y-m-d H:i') : '—' }}</td>
            <td>{{ $log->File_Name }}</td>
            <td>{{ $log->FileType }}</td>
            <td style="color:{{ $log->Import_Status === 'Success' ? 'var(--success)' : ($log->Import_Status ? 'var(--danger)' : 'var(--text-muted)') }}; font-weight:600;">{{ $log->Import_Status ?: '—' }}</td>
            <td>{{ $log->CSV_Row_Count }}</td>
            <td>{{ $log->Import_Count }}</td>
            <td>{{ $log->Updated_Count }}</td>
            <td>{{ $log->Duplicated_Count }}</td>
            <td>{{ $log->Error_Count }}</td>
            <td>{{ $log->created_by }}</td>
          </tr>
        @empty
          <tr>
            <td colspan="10" class="text-muted" style="font-size:.85rem;">No subject import logs yet.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

@endsection
