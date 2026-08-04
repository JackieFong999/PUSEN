@extends('layouts.app')

@section('content')

<div class="crumb mb-3">
    <a href="{{ route('dashboard') }}">Home</a><span class="sep">/</span>
    <span>Dashboard</span>
</div>

<div class="page-header d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
    <div>
        <h1 class="mb-1">Dashboard</h1>
        <p>Welcome back, {{ config('nav.profile.name') }} — here's what's happening across your designs today.</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn text-white border-0 d-flex align-items-center gap-2"
                style="background:var(--accent-grad); border-radius:10px; font-weight:600; font-size:.85rem; padding:.45rem 1rem;">
            <span class="icon-chip">
                <svg width="13" height="13" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                    <defs>
                        <linearGradient id="rainbowPlus" x1="0" y1="0" x2="16" y2="16" gradientUnits="userSpaceOnUse">
                            <stop offset="0%" stop-color="#ffd86f"/>
                            <stop offset="25%" stop-color="#ff7a45"/>
                            <stop offset="50%" stop-color="#ff3d8a"/>
                            <stop offset="75%" stop-color="#a06bff"/>
                            <stop offset="100%" stop-color="#4facfe"/>
                        </linearGradient>
                    </defs>
                    <path d="M8 1.5v13M1.5 8h13" stroke="url(#rainbowPlus)" stroke-width="2.8" stroke-linecap="round"/>
                </svg>
            </span>New Design
        </button>
    </div>
</div>

{{-- stats --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card d-flex align-items-center gap-3">
            <i class="bi bi-collection-fill"></i>
            <div><div class="num">2,400+</div><div class="lbl">Curated Designs</div></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card d-flex align-items-center gap-3">
            <i class="bi bi-people-fill"></i>
            <div><div class="num">48k</div><div class="lbl">Designers</div></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card d-flex align-items-center gap-3">
            <i class="bi bi-stars"></i>
            <div><div class="num">850</div><div class="lbl">Categories</div></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card d-flex align-items-center gap-3">
            <i class="bi bi-arrow-repeat"></i>
            <div><div class="num">Weekly</div><div class="lbl">New Drops</div></div>
        </div>
    </div>
</div>

{{-- filter chips --}}
<div class="d-flex flex-wrap gap-2 mb-4">
    <button class="chip active">All</button>
    <button class="chip">Minimal</button>
    <button class="chip">Dark Mode</button>
    <button class="chip">Collapsible</button>
    <button class="chip">With Icons</button>
    <button class="chip">Light Mode</button>
</div>

{{-- design cards --}}
<div class="row g-4" id="gallery">
    @php
        $designs = [
            ['title' => 'Sana AI',        'tag' => 'MINIMAL',     'icon' => 'bi-window-sidebar', 'desc' => 'Right-aligned icons in a featherweight sidebar — sleek, futuristic and distraction-free.', 'likes' => '2.1k'],
            ['title' => 'Supabase',       'tag' => 'DENSE',       'icon' => 'bi-stack',          'desc' => 'Large nav structures organized into clean subcategories with subtle separators.',           'likes' => '1.8k'],
            ['title' => 'Robin Spielmann','tag' => 'THEMES',      'icon' => 'bi-palette',        'desc' => 'Portfolio sidebar with blue / light / dark theme toggles right inside the panel.',      'likes' => '964'],
            ['title' => 'Swag App',       'tag' => 'MULTI-TIER',  'icon' => 'bi-briefcase',      'desc' => 'Bold color with nested navigation and tabbed content inside the sidebar panel.',       'likes' => '1.2k'],
            ['title' => 'This is FC88',   'tag' => 'BOLD',        'icon' => 'bi-fire',           'desc' => 'Handcrafted icons and oversized typography that reflect a bold, activist spirit.',       'likes' => '733'],
            ['title' => 'Buttery',        'tag' => 'ICON LIB',    'icon' => 'bi-bezier2',        'desc' => 'A growing icon library whose sidebar highlights every option with brand-aligned icons.',  'likes' => '611'],
        ];
    @endphp

    @foreach ($designs as $d)
        <div class="col-sm-6 col-xl-4">
            <div class="ex-card">
                <div class="ex-thumb"><span class="mini-side"></span><i class="bi {{ $d['icon'] }}"></i></div>
                <div class="body">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="title">{{ $d['title'] }}</span>
                        <span class="tag">{{ $d['tag'] }}</span>
                    </div>
                    <p class="desc">{{ $d['desc'] }}</p>
                    <div class="foot">
                        <span class="d-flex gap-2" style="font-size:.72rem; color:var(--text-faint);">
                            <i class="bi bi-heart"></i> {{ $d['likes'] }}
                        </span>
                        <a href="#">View Design <i class="bi bi-arrow-right"></i></a>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="text-center mt-4">
    <button class="btn btn-outline-secondary rounded-pill px-4"
            style="font-size:.85rem; color:var(--text-muted); border-color:var(--border);" id="loadMore">
        <i class="bi bi-arrow-clockwise me-1"></i>Load More Designs
    </button>
</div>

<div class="footer-note">
    <i class="bi bi-grid-1x2 me-1"></i> {{ config('app.name', 'Pusen01') }} — built with Laravel + Bootstrap 5.
</div>

@endsection
