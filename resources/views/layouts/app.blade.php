@props(['title' => null])
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $title ? $title . ' — ' : '' }}{{ config('app.name', 'Pusen01') }}</title>
<script>
  // Apply saved theme before first paint (avoids flash)
  (function () {
    var t = localStorage.getItem('pusen-theme');
    document.documentElement.setAttribute('data-bs-theme', t || 'dark');
  })();
</script>

<!-- Bootstrap 5.3 + Icons + Inter font -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
  :root {
    --bg: #0e1016;
    --bg-soft: #131722;
    --sidebar-bg: #151926;
    --sidebar-bg-collapsed: #10131d;
    --card-bg: #181d2b;
    --card-border: #242b3d;
    --border: #222939;
    --text: #e8eaf0;
    --text-muted: #8b93a7;
    --text-faint: #5c6478;
    --accent: #6d8dff;
    --accent-rgb: 109, 141, 255;
    --accent-soft: rgba(109, 141, 255, 0.12);
    --accent-grad: linear-gradient(135deg, #6d8dff, #8f6dff);
    --success: #34d399;
    --danger: #f87171;
    --radius: 12px;
    --sidebar-w: 264px;
    --sidebar-w-collapsed: 78px;
    --topbar-h: 60px;
    --shadow: 0 8px 24px rgba(0, 0, 0, 0.35);
  }

  [data-bs-theme="light"] {
    --bg: #f4f6fb;
    --bg-soft: #ffffff;
    --sidebar-bg: #ffffff;
    --sidebar-bg-collapsed: #fbfcfe;
    --card-bg: #ffffff;
    --card-border: #e6e9f2;
    --border: #e3e7f0;
    --text: #171b26;
    --text-muted: #5d6679;
    --text-faint: #9aa2b5;
    --accent-soft: rgba(109, 141, 255, 0.12);
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
    width: 32px; height: 32px; border-radius: 9px;
    background: var(--accent-grad);
    display: grid; place-items: center;
    color: #fff; font-size: 1.05rem;
    box-shadow: 0 4px 12px rgba(109, 141, 255, .35);
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
    background: var(--accent-grad);
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
    color: var(--text-faint); border-radius: 5px; padding: 2px 5px;
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
    color: var(--text-muted);
    text-decoration: none;
    font-size: .875rem; font-weight: 500;
    position: relative;
    white-space: nowrap;
    transition: background .15s, color .15s;
  }
  .side-link i { font-size: 1.05rem; width: 22px; text-align: center; flex-shrink: 0; }
  .side-link .txt { flex: 1; overflow: hidden; text-overflow: ellipsis; }
  .side-link .badge-soft {
    background: var(--card-border); color: var(--text-muted);
    font-size: .66rem; font-weight: 600;
    border-radius: 20px; padding: 2px 8px;
  }
  .side-link:hover { color: var(--text); background: var(--accent-soft); }
  .side-link.active {
    color: #fff;
    background: var(--accent-grad);
    box-shadow: 0 6px 16px rgba(var(--accent-rgb), .3);
  }
  .side-link.active .badge-soft { background: rgba(255,255,255,.2); color: #fff; }
  [data-bs-theme="light"] .side-link.active { color: #fff; }

  /* colorful icon palette */
  .ic-gray   { color: #c9cdd6; }
  .ic-blue   { color: #4d9fff; }
  .ic-green  { color: #2ecc71; }
  .ic-pink   { color: #ff4d8d; }
  .ic-yellow { color: #ffc74d; }
  .ic-purple { color: #b07cff; }
  .ic-cyan   { color: #38d6f2; }
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
    background: var(--accent-grad); border-color: transparent; color: #fff;
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

  /* sidebar bottom (profile card) */
  .side-foot {
    border-top: 1px solid var(--border);
    padding: .9rem .85rem;
    background: var(--sidebar-bg);
    transition: background .3s ease;
    flex-shrink: 0;
  }
  .profile-row { display: flex; align-items: center; gap: .7rem; }
  .profile-row .info { flex: 1; min-width: 0; white-space: nowrap; }
  .profile-row .info .name { font-size: .83rem; font-weight: 600; color: var(--text); }
  .profile-row .info .role { font-size: .72rem; color: var(--text-faint); }
  .profile-row .mini-btn {
    width: 30px; height: 30px; border-radius: 8px;
    border: 1px solid var(--border); background: transparent;
    color: var(--text-faint); font-size: .85rem;
    display: grid; place-items: center;
    transition: all .15s; flex-shrink: 0;
  }
  .profile-row .mini-btn:hover { color: var(--danger); border-color: rgba(248,113,113,.4); background: rgba(248,113,113,.08); }

  .upgrade {
    border-radius: var(--radius);
    background: linear-gradient(150deg, rgba(var(--accent-rgb), .16), rgba(143,109,255,.10));
    border: 1px solid rgba(var(--accent-rgb), .28);
    padding: .95rem;
    margin-bottom: 1rem;
    text-align: center;
  }
  .upgrade h6 { font-weight: 700; font-size: .85rem; margin-bottom: .3rem; }
  .upgrade p { font-size: .72rem; color: var(--text-muted); margin-bottom: .7rem; }

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
  .sidebar.collapsed .side-link .chev, .sidebar.collapsed .upgrade,
  .sidebar.collapsed .profile-row .info, .sidebar.collapsed .profile-row .mini-btn,
  .sidebar.collapsed .side-foot .foot-extra {
    display: none !important;
  }
  .sidebar.collapsed .side-link { justify-content: center; padding: .6rem 0; }
  .sidebar.collapsed .sidebar-scroll > .d-flex { justify-content: center; }
  .sidebar.collapsed .side-label { text-align: center; padding: .85rem 0 .45rem; font-size: .6rem; }
  .sidebar.collapsed .side-scroll { padding: 1rem .5rem; }
  .sidebar.collapsed .profile-row { justify-content: center; }

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
      <span class="brand-mark"><i class="bi bi-grid-1x2-fill"></i></span>
      <span>{{ config('app.name', 'Pusen01') }}</span>
    </a>
  </div>

  <div class="ms-auto d-flex align-items-center gap-2">
    <button class="icon-btn" id="themeToggle" type="button" aria-label="Toggle theme" title="Toggle theme">
      <i class="bi bi-moon-stars-fill"></i>
    </button>
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
      <span class="brand-mark"><i class="bi bi-grid-1x2-fill"></i></span>
      <span>{{ config('app.name', 'Pusen01') }}</span>
    </a>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body p-3">
    @foreach (config('nav.sections') as $label => $section)
      @if (! empty($section['show_label']))
        <div class="side-label">{{ $label }}</div>
      @endif
      @foreach ($section['items'] as $item)
        @if (! empty($item['children']))
          @foreach ($item['children'] as $child)
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

<!-- toast holder -->
<div class="toast-holder"></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // ---------- Sidebar collapse (desktop) ----------
  const sidebar = document.getElementById('sidebar');
  const sidebarToggle = document.getElementById('sidebarToggle');
  if (sidebarToggle) {
    sidebarToggle.addEventListener('click', () => {
      sidebar.classList.toggle('collapsed');
      sidebarToggle.title = sidebar.classList.contains('collapsed') ? 'Expand sidebar' : 'Collapse sidebar';
    });
  }

  // ---------- Collapsible group ----------
  document.querySelectorAll('[data-group-toggle]').forEach(link => {
    link.addEventListener('click', (e) => {
      e.preventDefault();
      link.closest('.side-group').classList.toggle('open');
    });
  });

  // ---------- Active link switching ----------
  document.querySelectorAll('.side-link').forEach(link => {
    link.addEventListener('click', () => {
      if (link.hasAttribute('data-group-toggle')) return;
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

  // ---------- Theme toggle ----------
  const themeToggle = document.getElementById('themeToggle');
  const themeIcon = themeToggle.querySelector('i');
  function applyTheme(theme) {
    document.documentElement.setAttribute('data-bs-theme', theme);
    localStorage.setItem('pusen-theme', theme);
    themeIcon.className = theme === 'dark' ? 'bi bi-moon-stars-fill' : 'bi bi-sun-fill';
  }
  themeToggle.addEventListener('click', () => {
    const isDark = document.documentElement.getAttribute('data-bs-theme') === 'dark';
    applyTheme(isDark ? 'light' : 'dark');
    toast(isDark ? '☀️ Light mode enabled' : '🌙 Dark mode enabled');
  });

  // ---------- Mobile offcanvas ----------
  document.getElementById('mobileMenuBtn').addEventListener('click', () => {
    bootstrap.Offcanvas.getOrCreateInstance(document.getElementById('mobileSidebar')).show();
  });

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
