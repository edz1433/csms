<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    @include('layouts.partials.head')
</head>
<body class="min-h-screen flex items-center justify-center p-4"
      style="background:
        radial-gradient(1200px 500px at 100% 0%, rgba(255,213,0,.10), transparent 60%),
        radial-gradient(1000px 600px at 0% 100%, rgba(11,110,46,.12), transparent 55%),
        #F7F8F5;">
    <main class="w-full max-w-md">
        @yield('content')
    </main>
    @stack('scripts')
</body>
</html>
