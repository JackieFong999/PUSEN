@extends('layouts.app')

@section('content')

<style nonce="{{ $cspNonce }}">
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
    <h1 class="mb-0 u-fs-125">Target User List</h1>
  </div>
</div>

<div class="stat-card p-0 overflow-hidden">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0 u-minw-560">
      <thead>
        <tr>
          <th class="u-w-80">Id</th>
          <th class="u-w-140">Target User Id</th>
          <th>Target User Description</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($targetUsers as $tu)
          <tr>
            <td class="text-muted">{{ $tu->Id }}</td>
            <td><span class="tag">{{ $tu->Target_User_Id }}</span></td>
            <td>{{ $tu->Target_User_Desc }}</td>
          </tr>
        @empty
          <tr>
            <td colspan="3" class="text-center text-muted py-4">No target users found.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="text-center mt-4">
  <span class="u-fs-078 u-c-text-faint">
    <i class="bi bi-database me-1"></i>{{ count($targetUsers) }} record(s) from {{ config('database.connections.pusen.database') }}.tblTarget_User
  </span>
</div>

@endsection
