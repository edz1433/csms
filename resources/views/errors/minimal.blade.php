<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('code') · CPSU CSMS</title>
    <link rel="icon" href="{{ asset('images/cpsu-logo.png') }}">
    <script src="{{ asset('vendor/tailwind/tailwind.js') }}"></script>
    <script>tailwind.config = { theme: { extend: { colors: { cpsu: { green: '#0B6E2E', 'green-dark': '#074A1F', gold: '#FFD500', black: '#1A1A1A', bg: '#F7F8F5', border: '#E3E6DE', danger: '#DC2626' } } } } };</script>
    <script src="{{ asset('vendor/lucide/lucide.min.js') }}"></script>
    <style>
      @font-face { font-family:'Inter'; font-weight:400; font-display:swap; src:url('{{ asset('vendor/fonts/inter/inter-400.woff2') }}') format('woff2'); }
      @font-face { font-family:'Inter'; font-weight:600; font-display:swap; src:url('{{ asset('vendor/fonts/inter/inter-600.woff2') }}') format('woff2'); }
      @font-face { font-family:'Inter'; font-weight:800; font-display:swap; src:url('{{ asset('vendor/fonts/inter/inter-800.woff2') }}') format('woff2'); }
      body{font-family:Inter,system-ui,sans-serif}
      @keyframes floaty { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-6px)} }
      .floaty{animation:floaty 5s ease-in-out infinite}
      @keyframes rise { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:translateY(0)} }
      .rise{animation:rise .5s cubic-bezier(.22,1,.36,1) both}
      .rise-2{animation-delay:.08s} .rise-3{animation-delay:.16s} .rise-4{animation-delay:.24s}
    </style>
</head>
<body class="relative min-h-screen flex items-center justify-center p-6 text-cpsu-black"
      style="background:
        radial-gradient(1100px 520px at 100% 0%, rgba(255,213,0,.16), transparent 60%),
        radial-gradient(900px 520px at 0% 100%, rgba(11,110,46,.16), transparent 55%),
        #F7F8F5;">

    {{-- Watermarked status code --}}
    <div aria-hidden="true"
         class="pointer-events-none select-none absolute inset-0 flex items-center justify-center overflow-hidden">
        <span class="font-extrabold text-cpsu-green/[0.05] leading-none" style="font-size:min(58vw,32rem)">@yield('code')</span>
    </div>

    <div class="relative w-full max-w-lg">
        <div class="rise bg-white/85 backdrop-blur rounded-2xl border border-cpsu-border shadow-xl p-8 sm:p-10 text-center">

            <div class="floaty h-20 w-20 mx-auto rounded-2xl bg-white shadow-lg ring-1 ring-cpsu-border flex items-center justify-center mb-6">
                <i data-lucide="@yield('icon', 'triangle-alert')" class="w-9 h-9 text-cpsu-green"></i>
            </div>

            <p class="rise rise-2 inline-flex items-center gap-2 rounded-full bg-cpsu-green/10 text-cpsu-green px-3 py-1 text-xs font-bold tracking-widest uppercase">
                Error @yield('code')
            </p>

            <h1 class="rise rise-3 text-2xl sm:text-3xl font-extrabold mt-4 leading-tight">@yield('message')</h1>
            <p class="rise rise-3 text-sm text-gray-500 mt-3 leading-relaxed">
                @yield('detail', 'Please check the address, or head back to a safe page.')
            </p>

            @yield('extra')

            <div class="rise rise-4 flex flex-wrap items-center justify-center gap-2 mt-8">
                <a href="{{ url('/') }}"
                   class="inline-flex items-center gap-2 bg-cpsu-green hover:bg-cpsu-green-dark text-white font-semibold rounded-lg px-5 py-2.5 text-sm transition active:scale-95">
                    <i data-lucide="layout-dashboard" class="w-4 h-4"></i> Back to Dashboard
                </a>
                <button type="button" onclick="history.back()"
                        class="inline-flex items-center gap-2 bg-white border border-cpsu-border hover:bg-cpsu-bg font-semibold rounded-lg px-5 py-2.5 text-sm transition active:scale-95">
                    <i data-lucide="arrow-left" class="w-4 h-4"></i> Go back
                </button>
            </div>
        </div>

        <div class="rise rise-4 flex items-center justify-center gap-2 mt-6 text-xs text-gray-400 text-center">
            <img src="{{ asset('images/cpsu-logo.png') }}" alt="" class="h-5 w-5 rounded-full object-contain"
                 onerror="this.style.display='none'">
            Central Philippines State University — Common Supply Management System
        </div>
    </div>

    <script>if (window.lucide) lucide.createIcons();</script>
</body>
</html>
