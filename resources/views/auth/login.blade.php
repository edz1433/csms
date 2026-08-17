@extends('layouts.guest')

@section('title', 'Sign in — '.config('app.name'))

@section('content')
<div class="w-full" x-data="{ loading: false }" data-aos="fade-up">
    {{-- Brand --}}
    <div class="flex flex-col items-center mb-6">
        <div class="h-20 w-20 rounded-full bg-white shadow-md ring-4 ring-cpsu-gold/70 flex items-center justify-center overflow-hidden mb-3">
            <img src="{{ asset('images/cpsu-logo.png') }}" alt="CPSU Seal" class="h-full w-full object-contain p-1"
                 onerror="this.replaceWith(Object.assign(document.createElement('div'),{className:'text-cpsu-green font-extrabold text-2xl',innerText:'CPSU'}))">
        </div>
        <h1 class="text-center text-lg font-extrabold text-cpsu-green leading-tight">
            CPSU Common Supply<br>Management System
        </h1>
        <p class="text-xs text-gray-500 mt-1">Central Philippines State University</p>
    </div>

    <div class="bg-white rounded-2xl border border-cpsu-border shadow-sm p-6 sm:p-8">
        <h2 class="text-base font-bold text-cpsu-black mb-1">Welcome back</h2>
        <p class="text-sm text-gray-500 mb-5">Sign in to continue to your workspace.</p>

        @if ($errors->any())
            <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm px-3 py-2 flex items-start gap-2">
                <i data-lucide="alert-circle" class="w-4 h-4 mt-0.5 shrink-0"></i>
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        <form method="POST" action="{{ route('login.submit') }}" @submit="loading = true" class="space-y-4">
            @csrf
            <div>
                <label for="email" class="block text-sm font-medium text-cpsu-black mb-1">Email</label>
                <div class="relative">
                    <i data-lucide="mail" class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                           class="w-full rounded-lg border border-cpsu-border pl-9 pr-3 py-2.5 text-sm focus:border-cpsu-green focus:ring-2 focus:ring-cpsu-green/20 outline-none"
                           placeholder="you@cpsu.edu.ph">
                </div>
            </div>

            <div x-data="{ show: false }">
                <label for="password" class="block text-sm font-medium text-cpsu-black mb-1">Password</label>
                <div class="relative">
                    <i data-lucide="lock" class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
                    <input id="password" name="password" :type="show ? 'text' : 'password'" required
                           class="w-full rounded-lg border border-cpsu-border pl-9 pr-10 py-2.5 text-sm focus:border-cpsu-green focus:ring-2 focus:ring-cpsu-green/20 outline-none"
                           placeholder="••••••••">
                    <button type="button" @click="show = !show" tabindex="-1"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-cpsu-green">
                        <i data-lucide="eye" x-show="!show"></i>
                        <i data-lucide="eye-off" x-show="show" x-cloak></i>
                    </button>
                </div>
            </div>

            <label class="flex items-center gap-2 text-sm text-gray-600 select-none">
                <input type="checkbox" name="remember" class="rounded border-cpsu-border text-cpsu-green focus:ring-cpsu-green/30">
                Remember me on this device
            </label>

            <button type="submit" :disabled="loading"
                    class="w-full bg-cpsu-green hover:bg-cpsu-green-dark text-white font-semibold rounded-lg py-2.5 transition-all active:scale-95 disabled:opacity-70 flex items-center justify-center gap-2">
                <span x-show="!loading" class="flex items-center gap-2"><i data-lucide="log-in" class="w-4 h-4"></i> Sign in</span>
                <span x-show="loading" x-cloak class="flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
                    Signing in…
                </span>
            </button>
        </form>

        <div class="my-5 flex items-center gap-3">
            <div class="h-px flex-1 bg-cpsu-border"></div>
            <span class="text-[11px] font-semibold uppercase text-gray-400">or</span>
            <div class="h-px flex-1 bg-cpsu-border"></div>
        </div>

        <a href="{{ route('login.google') }}"
           class="w-full rounded-lg border border-cpsu-border bg-white hover:bg-cpsu-bg text-cpsu-black font-semibold py-2.5 transition-all active:scale-95 flex items-center justify-center gap-2">
            <i data-lucide="chrome" class="w-4 h-4 text-cpsu-green"></i>
            Sign in with Google
        </a>
    </div>

    <p class="text-center text-[11px] text-gray-400 mt-6">
        &copy; {{ date('Y') }} CPSU — Common Supply Management System
    </p>
</div>
@endsection
