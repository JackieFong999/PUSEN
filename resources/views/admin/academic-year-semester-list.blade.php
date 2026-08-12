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
    <h1 class="mb-0" style="font-size:1.25rem;">Academic Year Semester</h1>
  </div>
</div>

<div class="stat-card p-0 overflow-hidden">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0" style="min-width: 560px;">
      <thead>
        <tr>
          <th style="width:180px;">Year Semester Code</th>
          <th>Year Semester Description</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($years as $y)
          <tr>
            <td><span class="tag">{{ $y->Year_Semester_Code }}</span></td>
            <td>{{ $y->Year_Semester_Desc }}</td>
          </tr>
        @empty
          <tr>
            <td colspan="2" class="text-center text-muted py-4">No academic year/semesters found.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="text-center mt-4">
  <span style="font-size:.78rem; color:var(--text-faint);">
    <i class="bi bi-database me-1"></i>{{ count($years) }} record(s) from PUSENDB.tblAcademicYear_Semester
  </span>
</div>

@endsection
