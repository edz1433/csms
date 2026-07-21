{{-- Access-gated sidebar. $access + $role shared via View Composer (Phase 2).
     Each link renders only if the user has that page key (admins always pass). --}}
@php
    $access = $access ?? (auth()->user()->access ?? []);
    $role   = $role ?? (auth()->user()->role ?? null);
    $can = fn ($key) => $role === 'administrator' || in_array($key, $access ?? [], true);

    $nav = [
        ['key' => 'dashboard',      'route' => 'dashboard',            'label' => 'Dashboard',        'icon' => 'layout-dashboard'],
        ['key' => 'items',          'route' => 'items.index',          'label' => 'Items / Stocks',   'icon' => 'package'],
        ['key' => 'receiving',      'route' => 'deliveries.index',     'label' => 'Receiving',        'icon' => 'truck'],
        ['key' => 'releasing',      'route' => 'releases.index',       'label' => 'Releasing',        'icon' => 'send'],
        ['key' => 'suppliers',      'route' => 'suppliers.index',      'label' => 'Suppliers',        'icon' => 'building-2'],
        ['key' => 'locations',      'route' => 'locations.index',      'label' => 'Campuses/Offices', 'icon' => 'map-pin'],
        ['key' => 'units',          'route' => 'units.index',          'label' => 'Units',            'icon' => 'ruler'],
        ['key' => 'fund_clusters',  'route' => 'fund-clusters.index',  'label' => 'Fund Clusters',    'icon' => 'layers'],
        ['key' => 'account_titles', 'route' => 'account-titles.index', 'label' => 'Account Titles',   'icon' => 'book-open'],
        ['key' => 'reports',        'route' => 'reports.index',        'label' => 'Reports',          'icon' => 'bar-chart-3'],
        ['key' => 'users',          'route' => 'users.index',          'label' => 'User Management',   'icon' => 'users'],
    ];
@endphp

<aside
    x-data="{ open: false }"
    class="fixed inset-y-0 left-0 z-40 w-64 bg-cpsu-green text-white flex flex-col transform transition-transform duration-200 lg:translate-x-0"
    :class="open ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
    @toggle-sidebar.window="open = !open"
    @keydown.escape.window="open = false"
>
    {{-- Brand header --}}
    <div class="flex items-center gap-3 px-4 h-16 border-b border-white/10 shrink-0">
        <img src="{{ asset('images/cpsu-logo.png') }}" alt="CPSU"
             class="h-10 w-10 rounded-full bg-white/95 object-contain p-0.5"
             onerror="this.style.display='none'">
        <div class="leading-tight">
            <div class="font-bold text-sm tracking-tight">CPSU <span class="text-cpsu-gold">CSMS</span></div>
            <div class="text-[10px] text-white/70">Supply Management</div>
        </div>
    </div>

    {{-- Nav --}}
    <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1">
        @foreach ($nav as $item)
            @if ($can($item['key']) && \Illuminate\Support\Facades\Route::has($item['route']))
                @php $active = request()->routeIs(\Illuminate\Support\Str::before($item['route'], '.').'*'); @endphp
                <a href="{{ route($item['route']) }}"
                   class="group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-all duration-200
                          {{ $active ? 'bg-white/10 text-white border-l-4 border-cpsu-gold pl-2' : 'text-white/80 hover:bg-white/10 hover:text-white border-l-4 border-transparent pl-2' }}">
                    <i data-lucide="{{ $item['icon'] }}" class="w-[18px] h-[18px] shrink-0"></i>
                    <span>{{ $item['label'] }}</span>
                </a>
            @endif
        @endforeach
    </nav>

    {{-- User footer --}}
    <div class="border-t border-white/10 p-3">
        <div class="flex items-center gap-3 px-2 py-2">
            <div class="h-9 w-9 rounded-full bg-cpsu-gold text-cpsu-black flex items-center justify-center font-bold text-sm shrink-0">
                {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}
            </div>
            <div class="min-w-0 flex-1">
                <div class="text-sm font-semibold truncate">{{ auth()->user()->name ?? 'User' }}</div>
                <div class="text-[11px] text-white/60 capitalize">{{ str_replace('_', ' ', $role ?? '') }}</div>
            </div>
        </div>
        <form method="POST" action="{{ route('logout') }}" class="mt-1">
            @csrf
            <button type="submit"
                    class="w-full flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-white/80 hover:bg-white/10 hover:text-white transition-all active:scale-95">
                <i data-lucide="log-out" class="w-[18px] h-[18px]"></i> Sign out
            </button>
        </form>
    </div>
</aside>

{{-- Mobile backdrop --}}
<div x-data="{ open:false }" @toggle-sidebar.window="open=!open"
     x-show="open" x-cloak @click="$dispatch('toggle-sidebar')"
     x-transition:enter="transition-opacity duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
     x-transition:leave="transition-opacity duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
     class="fixed inset-0 z-30 bg-black/40 lg:hidden"></div>
