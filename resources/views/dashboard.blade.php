@extends('layouts.app')

@section('content')

<style>
  .stat-card { border-color: #000; }
  .table {
    --bs-table-bg: transparent;
    color: var(--text);
    border: 0;
    border-collapse: separate;
    border-spacing: 0;
  }
  .table thead th {
    font-size: .72rem;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: var(--text-faint);
    font-weight: 700;
    border-bottom: 1px solid #000;
    background: color-mix(in srgb, var(--bg-soft) 70%, transparent);
    padding: .8rem 1.1rem;
    white-space: nowrap;
  }
  .table tbody td {
    border-bottom: 1px solid #000;
    padding: .75rem 1.1rem;
    font-size: .875rem;
    color: var(--text);
  }
  .table tbody tr:last-child td { border-bottom: 0; }
  .table-hover tbody tr:hover { background: var(--accent-soft); }

  .filter-btn {
    display: inline-flex; align-items: center; gap: .35rem;
    border: 1px solid var(--border);
    background: var(--bg-soft);
    color: var(--text-muted);
    font-size: .8rem; font-weight: 600;
    border-radius: 10px;
    padding: .4rem .9rem;
    text-decoration: none;
    transition: all .15s;
  }
  .filter-btn:hover { color: var(--accent); border-color: rgba(var(--accent-rgb), .4); }
  .filter-btn.active { background: #9B2331; border-color: #7d1d29; color: #fff; }
  .filter-btn.active:hover { background: #d04553; border-color: #a02d38; color: #fff; }
</style>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
    <div>
        <h1 class="mb-0" style="font-size:1.25rem;">Dashboard</h1>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('dashboard') }}" class="filter-btn {{ $status ? '' : 'active' }}">
            <i class="bi bi-list-ul"></i> Show All
        </a>
        <a href="{{ route('dashboard', ['status' => 'success']) }}" class="filter-btn {{ $status === 'success' ? 'active' : '' }}">
            <i class="bi bi-check-circle"></i> Success
        </a>
        <a href="{{ route('dashboard', ['status' => 'failure']) }}" class="filter-btn {{ $status === 'failure' ? 'active' : '' }}">
            <i class="bi bi-x-octagon"></i> Failure
        </a>
    </div>
</div>

{{-- ============ IMPORT LOGGING (ALL TYPES) ============ --}}
<div class="stat-card p-0 overflow-hidden">
  <div class="d-flex align-items-center justify-content-between px-3 pt-3 pb-2">
    <span style="font-size:.72rem; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:#000;">Import Logging</span>
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
        @forelse ($importLogs as $log)
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
            <td colspan="10" class="text-muted" style="font-size:.85rem;">No import logs yet.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

{{-- ============ LOGIN STATISTIC (LAST 10 DAYS) ============ --}}
<div class="stat-card p-3 mt-4">
  <div class="d-flex align-items-center justify-content-between mb-2">
    <span style="font-size:.72rem; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:var(--text-faint);">Login Statistic</span>
    <span style="font-size:.75rem; color:var(--text-faint);">Recent 10 days</span>
  </div>
  <div style="height: 300px; position: relative;">
    <canvas id="loginChart"></canvas>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('loginChart');
    const stats = @json($loginStats);
    if (!ctx || typeof Chart === 'undefined') return;

    function chartColors() {
      const dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
      return {
        grid: dark ? 'rgba(255,255,255,.08)' : 'rgba(0,0,0,.06)',
        tick: dark ? '#8b93a7' : '#5d6679',
        success: '#16a34a',
        failure: '#f87171',
      };
    }

    let chart = null;
    function renderChart() {
      const c = chartColors();
      const monthNames = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
      const cfg = {
        type: 'bar',
        data: {
          labels: stats.map(s => {
            const [, m, d] = s.date.split('-').map(Number);
            return monthNames[m - 1] + ' ' + String(d).padStart(2, '0'); // e.g. Aug 09
          }),
          datasets: [
            { label: 'Login Success', data: stats.map(s => s.success), backgroundColor: c.success, borderRadius: 4, maxBarThickness: 26, barPercentage: 1 },
            { label: 'Login Failure', data: stats.map(s => s.failure), backgroundColor: c.failure, borderRadius: 4, maxBarThickness: 26, barPercentage: 1 },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { labels: { color: c.tick, boxWidth: 12, font: { size: 12 } } },
            tooltip: {
              callbacks: {
                title: items => items[0]?.label ? stats[items[0].dataIndex].date : '',
              },
            },
          },
          scales: {
            x: { ticks: { color: c.tick }, grid: { color: c.grid } },
            y: { beginAtZero: true, ticks: { color: c.tick, stepSize: 1 }, grid: { color: c.grid } },
          },
        },
      };
      if (chart) chart.destroy();
      chart = new Chart(ctx, cfg);
    }

    renderChart();
    document.getElementById('themeToggle')?.addEventListener('click', () => setTimeout(renderChart, 60));
  });
</script>

@endsection
