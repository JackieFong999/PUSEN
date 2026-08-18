@props(['active' => null])

@php
    // Auto-detect active item from the current URL (matches on href).
    $currentPath = request()->path();
    $isActive = fn (?string $href) => $href && $href !== '#' && ltrim($href, '/') === $currentPath;

    // Role-based visibility: an item is shown when it has no 'roles' key or
    // the logged-in staff's Role_Id is in the list (see config/nav.php).
    $roleId = Auth::user()?->Role_Id;
    $canSee = fn (array $item) => empty($item['roles']) || in_array($roleId, $item['roles'], true);

    $sections = config('nav.sections');
@endphp

<aside class="sidebar" id="sidebar">
    <div class="sidebar-scroll">
        <div class="d-flex align-items-center justify-content-end mb-3">
            <button class="side-collapse-btn" id="sidebarToggle" type="button" title="Collapse sidebar">
                <i class="bi bi-chevron-left"></i>
            </button>
        </div>

        @foreach ($sections as $label => $section)
            @if (! empty($section['show_label']))
                <div class="side-label">{{ $label }}</div>
            @endif
            <nav>
                @foreach ($section['items'] as $item)
                    @if (! $canSee($item))
                        @continue
                    @endif
                    @if (! empty($item['children']))
                        {{-- collapsible group --}}
                        @php
                            // keep the group open when one of its children is the current page
                            $groupHasActive = collect($item['children'])
                                ->contains(fn ($c) => $isActive($c['href'] ?? null));
                        @endphp
                        <div class="side-group {{ $groupHasActive ? 'open' : '' }}" data-group="{{ $item['label'] }}">
                            <a class="side-link" href="#" data-group-toggle>
                                <i class="bi {{ $item['icon'] }} {{ $item['color'] }}"></i>
                                <span class="txt">{{ $item['label'] }}</span>
                                <i class="bi bi-chevron-right chev"></i>
                            </a>
                            <div class="side-sub">
                                @foreach ($item['children'] as $child)
                                    @if (! $canSee($child))
                                        @continue
                                    @endif
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

    </div>
</aside>
