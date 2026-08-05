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

<div class="crumb mb-3">
  <a href="{{ route('dashboard') }}">Home</a><span class="sep">/</span>
  <a href="#">Admin</a><span class="sep">/</span>
  <span>Subject/Lecture List</span>
</div>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
  <div>
    <h1 class="mb-1">Subject/Lecture List</h1>
    <p>Subject &amp; lecture assignments — retrieved from <code>tblSubject</code>.</p>
  </div>
</div>

<div class="stat-card p-0 overflow-hidden">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0" style="min-width: 760px;">
      <thead>
        <tr>
          <th style="width:70px;">Id</th>
          <th style="width:100px;">Academic Year</th>
          <th style="width:90px;">Semester</th>
          <th>Subject Code</th>
          <th>Teacher Staff Id</th>
          <th>Subject Type</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($subjects as $s)
          <tr>
            <td class="text-muted">{{ $s->Id }}</td>
            <td>{{ $s->Academic_Year }}</td>
            <td>{{ $s->Semester }}</td>
            <td><span class="tag">{{ $s->Subject_Code }}</span></td>
            <td>{{ $s->Teacher_Staff_Id }}</td>
            <td>{{ $s->Subject_Type }}</td>
          </tr>
        @empty
          <tr>
            <td colspan="6" class="text-center text-muted py-4">No subjects found.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="text-center mt-4">
  <span style="font-size:.78rem; color:var(--text-faint);">
    <i class="bi bi-database me-1"></i>{{ count($subjects) }} record(s) from PUSENDB.tblSubject
  </span>
</div>

@endsection
