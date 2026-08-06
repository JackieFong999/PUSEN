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
    <h1 class="mb-1">Fund Type</h1>
  </div>
</div>

<div class="stat-card p-0 overflow-hidden">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0" style="min-width: 480px;">
      <thead>
        <tr>
          <th style="width:180px;">Fund Type Code</th>
          <th>Fund Status</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($fundTypes as $ft)
          <tr>
            <td><span class="tag">{{ $ft->Fund_Type_Code }}</span></td>
            <td>{{ $ft->Fund_Status }}</td>
          </tr>
        @empty
          <tr>
            <td colspan="2" class="text-center text-muted py-4">No fund types found.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="text-center mt-4">
  <span style="font-size:.78rem; color:var(--text-faint);">
    <i class="bi bi-database me-1"></i>{{ count($fundTypes) }} record(s) from PUSENDB.tblFund_Type
  </span>
</div>

@endsection
