<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    @include('layouts.partials.head')
</head>
<body class="min-h-screen">
    @include('layouts.partials.sidebar')

    {{-- Content column --}}
    <div class="lg:pl-64 min-h-screen flex flex-col">
        {{-- Topbar --}}
        <header class="sticky top-0 z-20 h-16 bg-white/90 backdrop-blur border-b border-cpsu-border flex items-center gap-3 px-4 lg:px-6">
            <button @click="$dispatch('toggle-sidebar')"
                    class="lg:hidden p-2 -ml-2 rounded-lg hover:bg-cpsu-bg text-cpsu-black active:scale-95 transition">
                <i data-lucide="menu" class="w-5 h-5"></i>
            </button>
            <div class="flex-1 min-w-0">
                <h1 class="text-base lg:text-lg font-bold text-cpsu-black truncate">@yield('header', View::yieldContent('title'))</h1>
                @hasSection('subheader')
                    <p class="text-xs text-gray-500 truncate">@yield('subheader')</p>
                @endif
            </div>
            @yield('toolbar')

            {{-- User menu (upper-right) with Sign out --}}
            <div x-data="{ open: false }" class="relative shrink-0">
                <button @click="open = !open" @click.outside="open = false"
                        class="flex items-center gap-2 rounded-lg pl-1.5 pr-2 py-1.5 hover:bg-cpsu-bg transition active:scale-95">
                    <span class="h-8 w-8 rounded-full bg-cpsu-green text-white flex items-center justify-center font-bold text-sm shrink-0">
                        {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}
                    </span>
                    <span class="hidden sm:block text-left leading-tight max-w-[10rem]">
                        <span class="block text-sm font-semibold text-cpsu-black truncate">{{ auth()->user()->name }}</span>
                        <span class="block text-[11px] text-gray-400 capitalize truncate">{{ str_replace('_', ' ', auth()->user()->role) }}</span>
                    </span>
                    <i data-lucide="chevron-down" class="w-4 h-4 text-gray-400 transition-transform" :class="open && 'rotate-180'"></i>
                </button>

                <div x-show="open" x-cloak
                     x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0 -translate-y-1 scale-95"
                     x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                     x-transition:leave="transition ease-in duration-100"
                     x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                     class="absolute right-0 mt-2 w-60 bg-white rounded-xl border border-cpsu-border shadow-lg overflow-hidden z-50">
                    <div class="px-4 py-3 border-b border-cpsu-border">
                        <p class="text-sm font-semibold text-cpsu-black truncate">{{ auth()->user()->name }}</p>
                        <p class="text-xs text-gray-400 truncate">{{ auth()->user()->email }}</p>
                        <span class="inline-flex mt-1.5 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-cpsu-green/10 text-cpsu-green capitalize">
                            {{ str_replace('_', ' ', auth()->user()->role) }}
                        </span>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                                class="w-full flex items-center gap-2 px-4 py-2.5 text-sm text-cpsu-danger hover:bg-red-50 transition text-left">
                            <i data-lucide="log-out" class="w-4 h-4"></i> Sign out
                        </button>
                    </form>
                </div>
            </div>
        </header>

        {{-- Flash messages --}}
        @if (session('success') || session('error'))
            <div x-data
                 x-init="CPSU.toast(@js(session('success') ?? session('error')), '{{ session('success') ? 'success' : 'error' }}')"></div>
        @endif

        {{-- Main --}}
        <main class="flex-1 p-4 lg:p-6">
            @yield('content')
        </main>

        <footer class="px-6 py-4 text-center text-xs text-gray-400 border-t border-cpsu-border">
            &copy; {{ date('Y') }} Central Philippines State University — Common Supply Management System
        </footer>
    </div>

    @stack('scripts')
</body>
</html>
