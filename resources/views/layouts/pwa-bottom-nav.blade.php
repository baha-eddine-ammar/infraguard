@php
    $pwaBottomNavItems = [
        [
            'label' => 'Dashboard',
            'route' => route('dashboard'),
            'active' => request()->routeIs('dashboard'),
            'icon' => 'dashboard',
        ],
        [
            'label' => 'AI',
            'route' => route('ai-chat.index'),
            'active' => request()->routeIs('ai-chat.*'),
            'icon' => 'ai',
        ],
        [
            'label' => 'Reports',
            'route' => route('reports.index'),
            'active' => request()->routeIs('reports.*'),
            'icon' => 'reports',
        ],
    ];

    if (auth()->user()?->isDepartmentHead()) {
        $pwaBottomNavItems[] = [
            'label' => 'Admin',
            'route' => route('admin.index'),
            'active' => request()->routeIs('admin.*'),
            'icon' => 'admin',
        ];
    }
@endphp

<nav class="pwa-bottom-nav" aria-label="PWA primary navigation">
    <div class="pwa-bottom-nav__inner" style="grid-template-columns: repeat({{ count($pwaBottomNavItems) }}, minmax(0, 1fr));">
        @foreach ($pwaBottomNavItems as $item)
            <a href="{{ $item['route'] }}" class="pwa-bottom-nav__item {{ $item['active'] ? 'is-active' : '' }}">
                @if ($item['icon'] === 'dashboard')
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M4.5 11.5 12 5l7.5 6.5"></path>
                        <path d="M6.5 10.5v8h11v-8"></path>
                        <path d="M10 18.5v-5h4v5"></path>
                    </svg>
                @elseif ($item['icon'] === 'ai')
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M12 4.5v15"></path>
                        <path d="M7 8.5h10"></path>
                        <path d="M7 15.5h10"></path>
                        <path d="M5.5 12h13"></path>
                        <path d="M8 4.5h8a3.5 3.5 0 0 1 3.5 3.5v8a3.5 3.5 0 0 1-3.5 3.5H8A3.5 3.5 0 0 1 4.5 16V8A3.5 3.5 0 0 1 8 4.5Z"></path>
                    </svg>
                @elseif ($item['icon'] === 'reports')
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M6 19.5h12"></path>
                        <path d="M8 16V9"></path>
                        <path d="M12 16V5"></path>
                        <path d="M16 16v-4"></path>
                    </svg>
                @else
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path d="M12 4.5a2 2 0 1 0 0 4 2 2 0 0 0 0-4Z"></path>
                        <path d="M4.5 19.5a7.5 7.5 0 0 1 15 0"></path>
                        <path d="M17.5 6.5l2 2"></path>
                        <path d="m19.5 6.5-2 2"></path>
                    </svg>
                @endif
                <span>{{ $item['label'] }}</span>
            </a>
        @endforeach
    </div>
</nav>
