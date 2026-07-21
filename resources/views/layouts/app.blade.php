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
