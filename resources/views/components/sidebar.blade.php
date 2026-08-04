@props(['active' => null])

@php
    // Auto-detect active item from the current URL (matches on href).
    $currentPath = request()->path();
    $isActive = fn (?string $href) => $href && $href !== '#' && ltrim($href, '/') === $currentPath;
    $sections = config('nav.sections');
    $profile  = config('nav.profile');
@endphp

<aside class="sidebar" id="sidebar">
    <div class="sidebar-scroll">
        <div class="d-flex align-items-center gap-2 mb-3">
            <div class="side-search flex-grow-1" style="margin:0;">
                <i class="bi bi-search"></i>
                <input type="text" placeholder="Search...">
                <kbd>⌘K</kbd>
            </div>
            <button class="side-collapse-btn" id="sidebarToggle" type="button" title="Collapse sidebar">
                <i class="bi bi-chevron-left"></i>
            </button>
        </div>

        @foreach ($sections as $label => $items)
            <div class="side-label">{{ $label }}</div>
            <nav>
                @foreach ($items as $item)
                    @if (! empty($item['children']))
                        {{-- collapsible group --}}
                        <div class="side-group">
                            <a class="side-link" href="#" data-group-toggle>
                                <i class="bi {{ $item['icon'] }} {{ $item['color'] }}"></i>
                                <span class="txt">{{ $item['label'] }}</span>
                                <i class="bi bi-chevron-right chev"></i>
                            </a>
                            <div class="side-sub">
                                @foreach ($item['children'] as $child)
                                    <a class="side-link {{ $isActive($child['href'] ?? null) ? 'active' : '' }}"
                                       href="{{ $child['href'] ?? '#' }}">
                                        <i class="bi {{ $child['icon'] }} {{ $child['color'] }}"></i>
                                        <span class="txt">{{ $child['label'] }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <a class="side-link {{ $isActive($item['href'] ?? null) ? 'active' : '' }}"
                           href="{{ $item['href'] ?? '#' }}">
                            <i class="bi {{ $item['icon'] }} {{ $item['color'] }}"></i>
                            <span class="txt">{{ $item['label'] }}</span>
                            @if (! empty($item['badge']))
                                <span class="badge-soft">{{ $item['badge'] }}</span>
                            @endif
                        </a>
                    @endif
                @endforeach
            </nav>
        @endforeach

        <div class="upgrade mt-3">
            <h6><i class="bi bi-gem me-1"></i>Go Pro</h6>
            <p>Unlock 2,400+ premium navbar &amp; sidebar designs.</p>
            <button class="btn btn-sm text-white border-0" style="background:var(--accent-grad); border-radius:8px; font-weight:600; width:100%;">
                Upgrade Now
            </button>
        </div>
    </div>

    {{-- sidebar footer: profile --}}
    <div class="side-foot">
        <div class="profile-row">
            <div class="avatar" style="width:34px;height:34px;flex-shrink:0;">{{ $profile['initial'] }}</div>
            <div class="info">
                <div class="name">{{ $profile['name'] }}</div>
                <div class="role">{{ $profile['role'] }}</div>
            </div>
            <button class="mini-btn" type="button" title="Settings"><i class="bi bi-gear"></i></button>
            <button class="mini-btn" type="button" title="Log out"><i class="bi bi-box-arrow-right"></i></button>
        </div>
    </div>
</aside>
