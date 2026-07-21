<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('code') · CPSU CSMS</title>
    <script src="{{ asset('vendor/tailwind/tailwind.js') }}"></script>
    <script>tailwind.config = { theme: { extend: { colors: { cpsu: { green: '#0B6E2E', 'green-dark': '#074A1F', gold: '#FFD500', bg: '#F7F8F5', border: '#E3E6DE' } } } } };</script>
    <style>
      @font-face { font-family:'Inter'; font-weight:400; font-display:swap; src:url('{{ asset('vendor/fonts/inter/inter-400.woff2') }}') format('woff2'); }
      @font-face { font-family:'Inter'; font-weight:600; font-display:swap; src:url('{{ asset('vendor/fonts/inter/inter-600.woff2') }}') format('woff2'); }
      @font-face { font-family:'Inter'; font-weight:800; font-display:swap; src:url('{{ asset('vendor/fonts/inter/inter-800.woff2') }}') format('woff2'); }
      body{font-family:Inter,system-ui,sans-serif}
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-6"
      style="background:radial-gradient(1000px 500px at 100% 0%, rgba(255,213,0,.10), transparent 60%),radial-gradient(900px 500px at 0% 100%, rgba(11,110,46,.12), transparent 55%),#F7F8F5;">
    <div class="text-center max-w-md">
        <div class="h-16 w-16 mx-auto rounded-full bg-white shadow ring-4 ring-cpsu-gold/60 flex items-center justify-center mb-5">
            <span class="text-cpsu-green font-extrabold">CPSU</span>
        </div>
        <p class="text-7xl font-extrabold text-cpsu-green leading-none">@yield('code')</p>
        <h1 class="text-xl font-bold text-gray-800 mt-3">@yield('message')</h1>
        <p class="text-sm text-gray-500 mt-2">@yield('detail', 'Please check the address or head back to a safe page.')</p>
        <a href="{{ url('/') }}" class="inline-flex items-center gap-2 mt-6 bg-cpsu-green hover:bg-cpsu-green-dark text-white font-semibold rounded-lg px-5 py-2.5 transition">
            Back to Dashboard
        </a>
    </div>
</body>
</html>
