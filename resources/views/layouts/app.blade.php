@props(['title' => null])
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $title ? $title . ' - ' : '' }}{{ config('app.name', 'PolyU SEN Data Bank') }}</title>

<!-- Bootstrap 5.3 + Icons + Inter font -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

@stack('head')

<style>
  :root {
    --bg: #f4f6fb;
    --bg-soft: #ffffff;
    --sidebar-bg: #ffffff;
    --sidebar-bg-collapsed: #fbfcfe;
    --card-bg: #ffffff;
    --card-border: #595959;
    --border: #595959;
    --bs-border-color: #595959;
    --bs-border-color-translucent: #595959;
    --text: #171b26;
    --text-muted: #5d6679;
    --text-faint: #9aa2b5;
    --accent: #6d8dff;
    --accent-rgb: 109, 141, 255;
    --accent-soft: rgba(109, 141, 255, 0.22);
    --accent-solid: #9B2331;
    --success: #34d399;
    --danger: #f87171;
    --radius: 12px;
    --sidebar-w: 264px;
    --sidebar-w-collapsed: 78px;
    --topbar-h: 60px;
    --shadow: 0 8px 24px rgba(23, 27, 38, 0.08);
  }

  * { scrollbar-width: thin; scrollbar-color: #333c52 transparent; }
  *::-webkit-scrollbar { width: 8px; height: 8px; }
  *::-webkit-scrollbar-thumb { background: #333c52; border-radius: 8px; }
  *::-webkit-scrollbar-track { background: transparent; }

  html { scroll-behavior: smooth; }

  body {
    font-family: 'Inter', system-ui, -apple-system, sans-serif;
    background: var(--bg);
    color: var(--text);
    transition: background .3s ease, color .3s ease;
  }

  /* ============ TOP BAR ============ */
  .topbar {
    position: fixed;
    top: 0; left: 0; right: 0;
    height: var(--topbar-h);
    z-index: 1030;
    display: flex;
    align-items: center;
    padding: 0 1.25rem;
    background: color-mix(in srgb, var(--bg) 82%, transparent);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    border-bottom: 1px solid var(--border);
  }
  .brand {
    display: flex; align-items: center; gap: .65rem;
    font-weight: 800; font-size: 1.05rem; letter-spacing: -.01em;
    color: var(--text); text-decoration: none;
    white-space: nowrap;
  }
  .brand-mark {
    height: 45px; width: auto;
    background: #fff; border-radius: 8px;
    padding: 4px 10px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, .18);
  }
  .topbar .nav-link {
    color: var(--text-muted);
    font-size: .875rem; font-weight: 500;
    padding: .4rem .75rem;
    border-radius: 8px;
    transition: color .15s, background .15s;
  }
  .topbar .nav-link:hover { color: var(--text); background: var(--accent-soft); }
  .topbar .nav-link.active { color: var(--accent); }

  .icon-btn {
    width: 38px; height: 38px;
    border-radius: 10px;
    border: 1px solid var(--border);
    background: transparent;
    color: var(--text-muted);
    display: grid; place-items: center;
    font-size: 1.05rem;
    position: relative;
    transition: all .15s;
  }
  .icon-btn:hover { color: var(--accent); border-color: rgba(var(--accent-rgb), .4); background: var(--accent-soft); }
  .icon-btn .dot {
    position: absolute; top: 8px; right: 9px;
    width: 7px; height: 7px; border-radius: 50%;
    background: var(--danger);
    border: 2px solid var(--bg);
  }
  .avatar {
    width: 34px; height: 34px; border-radius: 50%;
    background: var(--accent-solid);
    color: #fff; font-weight: 700; font-size: .8rem;
    display: grid; place-items: center;
    cursor: pointer;
    border: 2px solid var(--card-border);
  }

  /* ============ LAYOUT ============ */
  .layout { display: flex; min-height: 100vh; padding-top: var(--topbar-h); }

  /* ============ SIDEBAR ============ */
  .sidebar {
    position: fixed;
    top: var(--topbar-h);
    bottom: 0;
    left: 0;
    width: var(--sidebar-w);
    z-index: 1020;
    background: var(--sidebar-bg);
    border-right: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    transition: width .28s cubic-bezier(.4,0,.2,1), background .3s ease;
    overflow: hidden;
  }
  .sidebar.collapsed { width: var(--sidebar-w-collapsed); }

  .sidebar-scroll {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
    padding: 1rem .85rem 1rem .85rem;
  }

  .side-search {
    display: flex; align-items: center; gap: .5rem;
    background: var(--bg-soft);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: .5rem .75rem;
    margin-bottom: 1.25rem;
    color: var(--text-faint);
    transition: border-color .15s;
    flex-shrink: 0;
  }
  .side-search:focus-within { border-color: rgba(var(--accent-rgb), .5); }
  .side-search input {
    background: transparent; border: 0; outline: 0;
    color: var(--text); font-size: .85rem; width: 100%;
  }
  .side-search input::placeholder { color: var(--text-faint); }
  .side-search kbd {
    font-size: .65rem; background: var(--card-border);
    color: #fff; border-radius: 5px; padding: 2px 5px;
  }

  .side-label {
    font-size: .68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .09em;
    color: var(--text-faint);
    padding: .85rem .6rem .45rem;
    white-space: nowrap;
  }

  .side-link {
    display: flex; align-items: center; gap: .7rem;
    padding: .55rem .6rem;
    margin: 2px 0;
    border-radius: 9px;
    border: 1px solid transparent;
    color: var(--text-muted);
    text-decoration: none;
    font-size: .875rem; font-weight: 500;
    position: relative;
    white-space: nowrap;
    transition: background .15s, color .15s, border-color .15s;
  }
  .side-link i { font-size: 1.05rem; width: 22px; text-align: center; flex-shrink: 0; }
  .side-link .txt { flex: 1; overflow: hidden; text-overflow: ellipsis; }
  .side-link .badge-soft {
    background: var(--card-border); color: #fff;
    font-size: .66rem; font-weight: 600;
    border-radius: 20px; padding: 2px 8px;
  }
  .side-link:hover:not(.active) { color: var(--text); background: var(--accent-soft); border-color: #9B2331; }
  .side-link.active {
    color: #fff;
    background: var(--accent-solid);
    box-shadow: 0 6px 16px rgba(var(--accent-rgb), .3);
  }
  .side-link.active .badge-soft { background: rgba(255,255,255,.2); color: #fff; }

  /* colorful icon palette (light mode) */
  .ic-gray   { color: #9aa2b5; }
  .ic-blue   { color: #3b82f6; }
  .ic-green  { color: #22a55c; }
  .ic-pink   { color: #e11d6d; }
  .ic-yellow { color: #eab308; }
  .ic-purple { color: #9B2331; }
  .ic-cyan   { color: #06b6d4; }
  .ic-orange { color: #ff9a5c; }
  .side-link.active i { color: #fff; }

  /* collapse indicator */
  .side-group > .side-link .chev { transition: transform .2s; font-size: .8rem; }
  .side-group.open > .side-link .chev { transform: rotate(90deg); }
  .side-sub { display: none; padding-left: 2.15rem; }
  .side-group.open > .side-sub { display: block; }
  .side-sub .side-link { font-size: .82rem; padding: .42rem .6rem; }

  /* ============ MAIN CONTENT ============ */
  .main {
    flex: 1;
    margin-left: var(--sidebar-w);
    padding: 2rem 2.25rem 3rem;
    max-width: 100%;
    transition: margin-left .28s cubic-bezier(.4,0,.2,1);
  }
  .sidebar.collapsed ~ .main { margin-left: var(--sidebar-w-collapsed); }

  .page-header h1 {
    font-weight: 800; letter-spacing: -.02em; font-size: clamp(1.6rem, 3vw, 2.3rem);
    margin-bottom: .4rem;
  }
  .page-header p { color: var(--text-muted); max-width: 640px; font-size: .95rem; }

  .crumb { font-size: .78rem; color: var(--text-faint); }
  .crumb a { color: var(--text-faint); text-decoration: none; }
  .crumb a:hover { color: var(--accent); }
  .crumb .sep { margin: 0 .4rem; }

  .chip {
    border: 1px solid var(--border);
    background: transparent;
    color: var(--text-muted);
    font-size: .8rem; font-weight: 500;
    padding: .4rem .95rem;
    border-radius: 30px;
    transition: all .15s;
  }
  .chip:hover { color: var(--text); border-color: rgba(var(--accent-rgb), .5); }
  .chip.active {
    background: var(--accent-solid); border-color: transparent; color: #fff;
    box-shadow: 0 4px 14px rgba(var(--accent-rgb), .3);
  }

  .stat-card {
    background: var(--card-bg);
    border: 1px solid var(--card-border);
    border-radius: var(--radius);
    padding: 1rem 1.1rem;
  }
  .stat-card .num { font-size: 1.35rem; font-weight: 800; }
  .stat-card .lbl { font-size: .75rem; color: var(--text-muted); font-weight: 500; }
  .stat-card i { color: var(--accent); font-size: 1.1rem; }

  .ex-card {
    background: var(--card-bg);
    border: 1px solid var(--card-border);
    border-radius: var(--radius);
    overflow: hidden;
    transition: transform .2s, border-color .2s, box-shadow .2s;
    display: flex; flex-direction: column;
    height: 100%;
  }
  .ex-card:hover {
    transform: translateY(-4px);
    border-color: rgba(var(--accent-rgb), .45);
    box-shadow: var(--shadow);
  }
  .ex-thumb {
    aspect-ratio: 16 / 9;
    background: var(--bg-soft);
    border-bottom: 1px solid var(--card-border);
    position: relative;
    overflow: hidden;
    display: grid; place-items: center;
    color: var(--text-faint);
    font-size: 2.2rem;
  }
  .ex-thumb::after {
    content: '';
    position: absolute; inset: 0;
    background: linear-gradient(180deg, transparent 55%, rgba(0,0,0,.35));
    pointer-events: none;
  }
  .ex-thumb .mini-side {
    position: absolute; left: 0; top: 0; bottom: 0;
    width: 26%;
    background: rgba(var(--accent-rgb), .22);
    border-right: 1px solid rgba(var(--accent-rgb), .35);
  }
  .ex-card .body { padding: 1rem 1.1rem 1.1rem; display: flex; flex-direction: column; gap: .5rem; flex: 1; }
  .ex-card .title { font-weight: 700; font-size: .95rem; color: var(--text); }
  .ex-card .desc { font-size: .82rem; color: var(--text-muted); line-height: 1.5; }
  .tag {
    font-size: .68rem; font-weight: 600;
    color: var(--accent);
    background: var(--accent-soft);
    border-radius: 6px; padding: 3px 8px;
  }
  .ex-card .foot {
    margin-top: auto;
    display: flex; align-items: center; justify-content: space-between;
  }
  .ex-card .foot a { font-size: .8rem; font-weight: 600; color: var(--accent); text-decoration: none; }
  .ex-card .foot a:hover { text-decoration: underline; }

  /* sidebar bottom (profile card) - removed; profile moved to header */

  .side-collapse-btn {
    width: 26px; height: 26px; border-radius: 8px;
    border: 1px solid var(--border); background: transparent;
    color: var(--text-faint); font-size: .75rem;
    display: grid; place-items: center;
    flex-shrink: 0;
    transition: all .15s;
  }
  .side-collapse-btn:hover { color: var(--accent); border-color: rgba(var(--accent-rgb), .4); }
  .sidebar.collapsed .side-collapse-btn i { transform: rotate(180deg); }
  .sidebar.collapsed .side-search, .sidebar.collapsed .side-label,
  .sidebar.collapsed .side-link .txt, .sidebar.collapsed .side-link .badge-soft,
  .sidebar.collapsed .side-link .chev {
    display: none !important;
  }
  .sidebar.collapsed .side-link { justify-content: center; padding: .6rem 0; }
  .sidebar.collapsed .sidebar-scroll > .d-flex { justify-content: center; }
  .sidebar.collapsed .side-label { text-align: center; padding: .85rem 0 .45rem; font-size: .6rem; }
  .sidebar.collapsed .side-scroll { padding: 1rem .5rem; }

  /* colorful icon chip on CTA button */
  .icon-chip {
    display: inline-grid;
    place-items: center;
    width: 24px; height: 24px;
    border-radius: 7px;
    background: rgba(255,255,255,.95);
    box-shadow: 0 2px 6px rgba(0,0,0,.25);
    flex-shrink: 0;
  }
  .icon-chip i::before, .icon-chip svg { display: block; }

  .offcanvas {
    background: var(--sidebar-bg);
    border-right: 1px solid var(--border);
    width: var(--sidebar-w) !important;
  }
  .offcanvas .brand { margin: .35rem .5rem .6rem; }

  .footer-note {
    text-align: center; font-size: .78rem; color: var(--text-faint);
    border-top: 1px solid var(--border); padding-top: 1.5rem; margin-top: 2.5rem;
  }

  .toast-holder { position: fixed; bottom: 1.25rem; right: 1.25rem; z-index: 2000; }

  /* page loading overlay (shown during navigation) */
  .page-loader {
    position: fixed; inset: 0; z-index: 3000;
    background: color-mix(in srgb, var(--bg) 72%, transparent);
    backdrop-filter: blur(2px);
    -webkit-backdrop-filter: blur(2px);
    display: none; place-items: center;
  }
  .page-loader.show { display: grid; }
  .page-loader .loader-box {
    display: flex; flex-direction: column; align-items: center; gap: .8rem;
    background: var(--card-bg);
    border: 1px solid var(--card-border);
    border-radius: 14px;
    padding: 1.6rem 2.2rem;
    box-shadow: var(--shadow);
  }
  .page-loader .spinner-border { width: 2rem; height: 2rem; color: var(--accent); }
  .page-loader .loader-text { font-size: .85rem; font-weight: 600; color: var(--text-muted); letter-spacing: .02em; }

  /* generic action buttons + modal used by confirm dialogs (incl. nav guard) */
  .btn-search {
    background: #9B2331;
    color: #fff; font-weight: 600; font-size: .85rem;
    border: 1px solid #7d1d29; border-radius: 10px; padding: .5rem 1.2rem;
    box-shadow: 0 4px 14px rgba(155, 35, 49, .3);
  }
  .btn-search:hover { background: #d04553; border-color: #a02d38; color: #fff; }
  .btn-cancel { border: 1px solid #7d1d29; color: #fff; background: #9B2331; font-size: .85rem; font-weight: 600; border-radius: 10px; padding: .5rem 1.2rem; }
  .btn-cancel:hover { background: #d04553; border-color: #a02d38; color: #fff; }

  /* disabled state: gray with black text (applies to every standardized button) */
  .btn-search:disabled, .btn-cancel:disabled, .btn-create:disabled, .btn-add:disabled,
  .btn-import:disabled, .btn-save:disabled, .btn-edit:disabled, .btn-login:disabled {
    background: #e5e7eb !important;
    border-color: #d1d5db !important;
    color: #000 !important;
    box-shadow: none !important;
    opacity: 1 !important;
    filter: none !important;
    cursor: not-allowed;
  }
  .btn-search:disabled i, .btn-cancel:disabled i, .btn-create:disabled i, .btn-add:disabled i,
  .btn-import:disabled i, .btn-save:disabled i, .btn-edit:disabled i, .btn-login:disabled i {
    color: #000 !important;
  }
  .modal-content { background: var(--card-bg); border: 1px solid var(--card-border); border-radius: 14px; }
  .modal-content .modal-title { font-size: 1rem; font-weight: 600; color: var(--text); }

  @media (max-width: 991.98px) {
    .sidebar { display: none; }
    .main { margin-left: 0 !important; padding: 1.5rem 1.1rem 2.5rem; }
  }
</style>
</head>
<body>

<!-- ===================== TOP BAR ===================== -->
<header class="topbar">
  <div class="d-flex align-items-center gap-2 me-3">
    <button class="icon-btn d-lg-none" id="mobileMenuBtn" type="button" aria-label="Open menu">
      <i class="bi bi-list"></i>
    </button>
    <a class="brand" href="{{ url('/') }}">
      <img class="brand-mark" src="{{ asset('images/polyu-logo.png') }}" alt="PolyU">
      <span>{{ config('app.name', 'PolyU SEN Data Bank') }}</span>
    </a>
  </div>

  <div class="ms-auto d-flex align-items-center gap-2">
    @php $authStaff = Auth::user(); @endphp
    {{-- Login name / title --}}
    <div class="d-flex align-items-center gap-2 me-2">
      <div class="avatar" style="width:32px;height:32px;flex-shrink:0;">{{ $authStaff ? strtoupper(mb_substr($authStaff->Staff_Display_Name ?: $authStaff->Staff_Name, 0, 1)) : config('nav.profile.initial') }}</div>
      <div class="d-none d-md-block lh-1">
        <div style="font-size:.8rem;font-weight:600;color:var(--text);margin-bottom:3px;">{{ $authStaff ? ($authStaff->Staff_Display_Name ?: $authStaff->Staff_Name) : config('nav.profile.name') }}</div>
        <div style="font-size:.68rem;color:var(--text-faint);">{{ $authStaff ? ($authStaff->role?->Role_Desc ?: $authStaff->Staff_Id) : config('nav.profile.role') }}</div>
      </div>
    </div>
    {{-- Logout --}}
    <form method="POST" action="{{ route('logout') }}" class="m-0" id="logoutForm">
      @csrf
      <button type="submit" class="icon-btn" aria-label="Log out" title="Log out">
        <i class="bi bi-box-arrow-right"></i>
      </button>
    </form>
  </div>
</header>

<!-- ===================== LAYOUT ===================== -->
<div class="layout">

  {{-- Desktop sidebar --}}
  <x-sidebar />

  {{-- Main content --}}
  <main class="main">
    @yield('content')
  </main>
</div>

<!-- ============ OFFCANVAS SIDEBAR (mobile) ============ -->
<div class="offcanvas offcanvas-start" tabindex="-1" id="mobileSidebar" aria-labelledby="mobileSidebarLabel">
  <div class="offcanvas-header pb-0">
    <a class="brand" href="{{ url('/') }}">
      <img class="brand-mark" src="{{ asset('images/polyu-logo.png') }}" alt="PolyU">
      <span>{{ config('app.name', 'PolyU SEN Data Bank') }}</span>
    </a>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body p-3">
    @php $mobileRoleId = Auth::user()?->Role_Id; @endphp
    @foreach (config('nav.sections') as $label => $section)
      @if (! empty($section['show_label']))
        <div class="side-label">{{ $label }}</div>
      @endif
      @foreach ($section['items'] as $item)
        @if (! empty($item['roles']) && ! in_array($mobileRoleId, $item['roles'], true))
          @continue
        @endif
        @if (! empty($item['children']))
          @foreach ($item['children'] as $child)
            @if (! empty($child['roles']) && ! in_array($mobileRoleId, $child['roles'], true))
              @continue
            @endif
            <a class="side-link" href="{{ $child['href'] ?? '#' }}">
              <i class="bi {{ $child['icon'] }} {{ $child['color'] }}"></i>
              <span class="txt">{{ $child['label'] }}</span>
            </a>
          @endforeach
        @else
          <a class="side-link" href="{{ $item['href'] ?? '#' }}">
            <i class="bi {{ $item['icon'] }} {{ $item['color'] }}"></i>
            <span class="txt">{{ $item['label'] }}</span>
          </a>
        @endif
      @endforeach
    @endforeach
  </div>
</div>

<!-- ============ NAV GUARD CONFIRM MODAL (unsaved changes) ============ -->
<div class="modal fade" id="navConfirmModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title">Unsaved changes</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" style="font-size:.88rem;color:var(--text);">This form has unsaved changes. If you leave this page, all unsaved changes will be discarded. Do you want to continue?</div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-cancel" id="navConfirmStay">Stay</button>
        <button type="button" class="btn btn-search" id="navConfirmLeave">Discard &amp; Leave</button>
      </div>
    </div>
  </div>
</div>

{{-- ============ LOGOUT CONFIRM MODAL ============ --}}
<div class="modal fade" id="logoutConfirmModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title"><i class="bi bi-box-arrow-right me-1" style="color:var(--accent);"></i>Log out</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" style="font-size:.88rem;color:var(--text);">Are you sure you want to log out of the system?</div>
      <div class="modal-footer border-0 pt-0">
        <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-search" id="logoutConfirmYes">Log out</button>
      </div>
    </div>
  </div>
</div>

<!-- toast holder -->
<div class="toast-holder"></div>

<!-- page loading overlay (shown during navigation) -->
<div id="pageLoader" class="page-loader" aria-hidden="true">
  <div class="loader-box">
    <div class="spinner-border" role="status" aria-label="Loading"></div>
    <div class="loader-text">Loading...</div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // ---------- Sidebar collapse (desktop) ----------
  const sidebar = document.getElementById('sidebar');
  const sidebarToggle = document.getElementById('sidebarToggle');
  // restore collapsed state across page navigations
  if (sidebar && sidebarToggle && localStorage.getItem('pusen-sidebar-collapsed') === '1') {
    sidebar.classList.add('collapsed');
    sidebarToggle.title = 'Expand sidebar';
  }
  if (sidebarToggle) {
    sidebarToggle.addEventListener('click', () => {
      sidebar.classList.toggle('collapsed');
      localStorage.setItem('pusen-sidebar-collapsed', sidebar.classList.contains('collapsed') ? '1' : '0');
      sidebarToggle.title = sidebar.classList.contains('collapsed') ? 'Expand sidebar' : 'Collapse sidebar';
    });
  }

  // ---------- Collapsible group (state survives page navigations) ----------
  const GROUP_STORAGE_KEY = 'pusen-open-groups';
  function loadOpenGroups() {
    try { return JSON.parse(localStorage.getItem(GROUP_STORAGE_KEY) || '[]'); } catch { return []; }
  }
  function saveOpenGroups(groups) {
    localStorage.setItem(GROUP_STORAGE_KEY, JSON.stringify(groups));
  }
  // restore persisted state: open groups that were saved open, close ones the
  // user collapsed, but never close the group containing the active page link.
  const savedOpenGroups = new Set(loadOpenGroups());
  const activeGroup = document.querySelector('.side-group .side-link.active')?.closest('.side-group');
  document.querySelectorAll('.side-group[data-group]').forEach(group => {
    const name = group.dataset.group;
    if (savedOpenGroups.has(name) || group === activeGroup) group.classList.add('open');
    else group.classList.remove('open');
  });
  document.querySelectorAll('[data-group-toggle]').forEach(link => {
    link.addEventListener('click', (e) => {
      e.preventDefault();
      const group = link.closest('.side-group');
      if (!group) return;
      group.classList.toggle('open');
      const name = group.dataset.group;
      if (!name) return;
      const open = loadOpenGroups();
      const idx = open.indexOf(name);
      if (group.classList.contains('open') && idx === -1) open.push(name);
      if (!group.classList.contains('open') && idx !== -1) open.splice(idx, 1);
      saveOpenGroups(open);
    });
  });

  // ---------- Unsaved-changes navigation guard ----------
  // A page with an editable form can register a dirty checker:
  //   window.PUSEN_DIRTY_FN = () => true/false   (set from the page script, which runs
  //   before this layout script) - or later via window.pusenSetDirtyChecker(fn).
  let pusenDirtyChecker = typeof window.PUSEN_DIRTY_FN === 'function' ? window.PUSEN_DIRTY_FN : null;
  window.pusenSetDirtyChecker = (fn) => { pusenDirtyChecker = fn; };

  function pusenAskLeave() {
    return new Promise(resolve => {
      const modalEl = document.getElementById('navConfirmModal');
      const leaveBtn = document.getElementById('navConfirmLeave');
      let settled = false;
      const finish = (ok) => {
        if (settled) return;
        settled = true;
        bootstrap.Modal.getOrCreateInstance(modalEl).hide();
        resolve(ok);
      };
      leaveBtn.onclick = () => finish(true);
      document.getElementById('navConfirmStay').onclick = () => finish(false);
      modalEl.addEventListener('hidden.bs.modal', () => finish(false), { once: true });
      bootstrap.Modal.getOrCreateInstance(modalEl).show();
    });
  }

  // ---------- Logout confirm (styled modal; also clears the unsaved-changes guard
  // so leaving a dirty form via logout doesn't stack a second browser dialog) ----------
  const logoutForm = document.getElementById('logoutForm');
  if (logoutForm) {
    logoutForm.addEventListener('submit', (e) => {
      e.preventDefault();
      bootstrap.Modal.getOrCreateInstance(document.getElementById('logoutConfirmModal')).show();
    });
    document.getElementById('logoutConfirmYes').addEventListener('click', () => {
      window.__pusenLogoutConfirmed = true;
      if (typeof window.pusenSetDirtyChecker === 'function') window.pusenSetDirtyChecker(() => false);
      logoutForm.submit(); // native submit() bypasses the submit listener -> no loop
    });
  }

  // ---------- Active link switching + unsaved-changes guard ----------
  document.querySelectorAll('.side-link').forEach(link => {
    link.addEventListener('click', (e) => {
      if (link.hasAttribute('data-group-toggle')) return;
      const href = link.getAttribute('href');
      // while a form on the page has unsaved changes, ask before leaving
      if (href && href !== '#' && pusenDirtyChecker && pusenDirtyChecker()) {
        e.preventDefault();
        pusenAskLeave().then(ok => { if (ok) window.location.href = href; });
        return;
      }
      link.closest('nav, .offcanvas-body')?.querySelectorAll('.side-link.active').forEach(a => a.classList.remove('active'));
      link.classList.add('active');
    });
  });

  // ---------- Filter chips ----------
  document.querySelectorAll('.chip').forEach(chip => {
    chip.addEventListener('click', () => {
      document.querySelectorAll('.chip').forEach(c => c.classList.remove('active'));
      chip.classList.add('active');
    });
  });

  // ---------- Mobile offcanvas ----------
  document.getElementById('mobileMenuBtn').addEventListener('click', () => {
    bootstrap.Offcanvas.getOrCreateInstance(document.getElementById('mobileSidebar')).show();
  });

  // ---------- Page loading overlay (navigation) ----------
  const pageLoader = document.getElementById('pageLoader');
  function showPageLoader() { pageLoader.classList.add('show'); }
  function hidePageLoader() { pageLoader.classList.remove('show'); }

  // Show as soon as a real navigation starts (link click / form submit), but
  // skip if the action was cancelled (e.g. unsaved-changes guard preventDefault).
  document.addEventListener('click', (e) => {
    const a = e.target.closest('a[href]');
    if (!a) return;
    const href = a.getAttribute('href');
    if (!href || href === '#' || href.startsWith('http') || href.startsWith('//') ||
        href.startsWith('mailto:') || href.startsWith('tel:') ||
        a.hasAttribute('download') || a.target === '_blank') return;
    setTimeout(() => { if (!e.defaultPrevented) showPageLoader(); }, 0);
  });
  document.addEventListener('submit', (e) => {
    setTimeout(() => { if (!e.defaultPrevented) showPageLoader(); }, 0);
  });

  // Safety net: cover JS-driven navigation (e.g. redirect after Save) too,
  // and let ESC cancel a stuck overlay (browser-cancelled navigation).
  // When an unsaved-changes guard blocks the unload (browser's native "Leave
  // site?" dialog), DON'T show the overlay here - if the user cancels, it would
  // stay stuck on screen. Instead show it on pagehide, which only fires when the
  // page is really unloading (user picked "Leave"). (2026-08-24)
  window.addEventListener('beforeunload', (e) => {
    if (pusenDirtyChecker && pusenDirtyChecker()) return;
    showPageLoader();
  });
  window.addEventListener('pagehide', () => {
    if (pusenDirtyChecker && pusenDirtyChecker()) showPageLoader();
  });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') hidePageLoader(); });

  // Hide once the new page is fully loaded (also on back/forward cache restore).
  window.addEventListener('load', hidePageLoader);
  window.addEventListener('pageshow', hidePageLoader);

  // ---------- Toast helper ----------
  function toast(msg) {
    const holder = document.querySelector('.toast-holder');
    const el = document.createElement('div');
    el.className = 'toast align-items-center show border-0';
    el.style.background = 'var(--card-bg)';
    el.style.color = 'var(--text)';
    el.style.boxShadow = 'var(--shadow)';
    el.innerHTML = `<div class="d-flex"><div class="toast-body" style="font-size:.85rem;font-weight:500;">${msg}</div>
      <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button></div>`;
    holder.appendChild(el);
    setTimeout(() => el.remove(), 2600);
  }
</script>
</body>
</html>
