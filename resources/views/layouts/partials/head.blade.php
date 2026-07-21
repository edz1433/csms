{{-- Shared <head>: CPSU design system + all framework-agnostic CDN libraries.
     No frontend framework — Tailwind (CDN), Alpine.js, SweetAlert2, AOS, CountUp,
     Chart.js, DataTables (jQuery), Tom Select. --}}
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', config('app.name'))</title>

{{-- Tailwind CDN with CPSU palette. For production this would be compiled;
     CDN keeps the XAMPP dev setup zero-build. --}}
<script src="https://cdn.tailwindcss.com/3.4.16"></script>
<script>
  tailwind.config = {
    theme: {
      extend: {
        colors: {
          cpsu: {
            green: '#0B6E2E',
            'green-dark': '#074A1F',
            gold: '#FFD500',
            'gold-dark': '#E6BF00',
            black: '#1A1A1A',
            bg: '#F7F8F5',
            border: '#E3E6DE',
            danger: '#DC2626',
            success: '#16A34A',
          },
        },
        fontFamily: {
          sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
        },
      },
    },
  };
</script>

{{-- Inter font --}}
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

{{-- jQuery + DataTables (server-side lists) --}}
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.tailwindcss.min.css">
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.tailwindcss.min.js"></script>

{{-- SweetAlert2 (themed confirms + toasts) --}}
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

{{-- AOS (scroll reveals) --}}
<link href="https://unpkg.com/aos@2.3.4/dist/aos.css" rel="stylesheet">
<script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>

{{-- CountUp.js (dashboard KPI animation) --}}
<script src="https://cdn.jsdelivr.net/npm/countup.js@2.8.0/dist/countUp.umd.js"></script>

{{-- Chart.js (reports) --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

{{-- Tom Select (searchable item/supplier selects) --}}
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>

{{-- Lucide icons (framework-agnostic SVGs) --}}
<script src="https://unpkg.com/lucide@0.462.0/dist/umd/lucide.min.js"></script>

{{-- Alpine.js — loaded LAST + deferred so all data helpers are defined first --}}
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.7/dist/cdn.min.js"></script>

{{-- CPSU theme layer: brand chrome, DataTables restyle, motion primitives --}}
<style>
  :root {
    --cpsu-green: #0B6E2E; --cpsu-green-dark: #074A1F;
    --cpsu-gold: #FFD500;  --cpsu-gold-dark: #E6BF00;
    --cpsu-black: #1A1A1A; --cpsu-white: #FFFFFF;
    --cpsu-gray-bg: #F7F8F5; --cpsu-border: #E3E6DE;
    --cpsu-danger: #DC2626; --cpsu-success: #16A34A;
  }
  body { background: var(--cpsu-gray-bg); color: var(--cpsu-black); font-family: 'Inter', system-ui, sans-serif; }
  [x-cloak] { display: none !important; }

  /* DataTables Tailwind restyle so it doesn't look like stock jQuery */
  .dataTables_wrapper .dataTables_filter input,
  .dataTables_wrapper .dataTables_length select {
    border: 1px solid var(--cpsu-border); border-radius: 0.5rem;
    padding: 0.4rem 0.7rem; outline: none; background: #fff;
  }
  .dataTables_wrapper .dataTables_filter input:focus,
  .dataTables_wrapper .dataTables_length select:focus {
    border-color: var(--cpsu-green); box-shadow: 0 0 0 3px rgba(11,110,46,.12);
  }
  table.dataTable thead th {
    background: var(--cpsu-gray-bg); color: #3b4a3e; font-weight: 600;
    text-transform: uppercase; font-size: .7rem; letter-spacing: .03em;
    border-bottom: 1px solid var(--cpsu-border) !important;
  }
  table.dataTable tbody tr:hover { background: var(--cpsu-gray-bg); }
  table.dataTable tbody td { border-bottom: 1px solid #f0f2ee; vertical-align: middle; }
  .dataTables_wrapper .dataTables_paginate .paginate_button.current,
  .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
    background: var(--cpsu-green) !important; color: #fff !important;
    border-radius: 0.5rem; border-color: var(--cpsu-green) !important;
  }
  .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
    background: var(--cpsu-gray-bg) !important; border-radius: 0.5rem;
    border-color: var(--cpsu-border) !important; color: var(--cpsu-green) !important;
  }

  /* Tom Select on-brand focus */
  .ts-control { border-radius: .5rem !important; border-color: var(--cpsu-border) !important; padding: .4rem .6rem !important; }
  .ts-control.focus { border-color: var(--cpsu-green) !important; box-shadow: 0 0 0 3px rgba(11,110,46,.12) !important; }

  /* Badge cross-fade for payment status toggle */
  .badge-fade { transition: background-color .35s ease, color .35s ease, border-color .35s ease; }
</style>

{{-- Global JS init: SweetAlert2 CPSU theme + toast helper --}}
<script>
  window.CPSU = {
    // themed confirm dialog
    confirm(opts = {}) {
      return Swal.fire({
        icon: opts.icon || 'warning',
        title: opts.title || 'Are you sure?',
        text: opts.text || '',
        html: opts.html || undefined,
        showCancelButton: true,
        confirmButtonText: opts.confirmText || 'Yes, continue',
        cancelButtonText: opts.cancelText || 'Cancel',
        confirmButtonColor: '#0B6E2E',
        cancelButtonColor: '#DC2626',
        reverseButtons: true,
      });
    },
    // top-right auto-dismiss toast, colored by type
    toast(message, type = 'success') {
      const border = { success: '#16A34A', error: '#DC2626', info: '#FFD500', warning: '#E6BF00' }[type] || '#16A34A';
      Swal.fire({
        toast: true, position: 'top-end', timer: 3000, timerProgressBar: true,
        showConfirmButton: false, icon: type, title: message,
        didOpen: (el) => { el.style.borderLeft = '5px solid ' + border; },
      });
    },
  };
  // csrf for all jQuery/fetch calls
  window.CSRF = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
  $.ajaxSetup && $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': window.CSRF } });

  document.addEventListener('DOMContentLoaded', () => {
    if (window.lucide) lucide.createIcons();
    if (window.AOS) AOS.init({ duration: 500, once: true, offset: 40 });
  });
  // re-draw lucide icons after Alpine/DataTables inject DOM
  document.addEventListener('alpine:initialized', () => window.lucide && lucide.createIcons());
</script>

@stack('head')
