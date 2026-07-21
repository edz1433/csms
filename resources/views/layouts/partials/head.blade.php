{{-- Shared <head>: CPSU design system + all framework-agnostic CDN libraries.
     No frontend framework — Tailwind (CDN), Alpine.js, SweetAlert2, AOS, CountUp,
     Chart.js, DataTables (jQuery), Tom Select. --}}
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', config('app.name'))</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><circle cx='16' cy='16' r='15' fill='%230B6E2E' stroke='%23FFD500' stroke-width='2'/><text x='16' y='21' font-size='11' font-family='Arial' font-weight='bold' fill='white' text-anchor='middle'>CS</text></svg>">
<link rel="apple-touch-icon" href="{{ asset('images/cpsu-logo.png') }}">
<meta name="theme-color" content="#0B6E2E">
<meta name="description" content="CPSU Common Supply Management System">
<meta name="robots" content="noindex">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
{{-- All front-end libraries are self-hosted under public/vendor so the system
     works with NO internet connection. No npm build step required. --}}

{{-- Tailwind (self-hosted Play build) with CPSU palette --}}
<script src="{{ asset('vendor/tailwind/tailwind.js') }}"></script>
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

{{-- jQuery + DataTables (server-side lists) + Responsive (no horizontal scroll) --}}
<script src="{{ asset('vendor/jquery/jquery.min.js') }}"></script>
<link rel="stylesheet" href="{{ asset('vendor/datatables/dataTables.tailwindcss.min.css') }}">
<link rel="stylesheet" href="{{ asset('vendor/datatables/responsive.dataTables.min.css') }}">
<script src="{{ asset('vendor/datatables/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('vendor/datatables/dataTables.responsive.min.js') }}"></script>

{{-- SweetAlert2 (themed confirms + toasts) --}}
<script src="{{ asset('vendor/sweetalert2/sweetalert2.all.min.js') }}"></script>

{{-- AOS (scroll reveals) --}}
<link href="{{ asset('vendor/aos/aos.css') }}" rel="stylesheet">
<script src="{{ asset('vendor/aos/aos.js') }}"></script>

{{-- CountUp.js (dashboard KPI animation) --}}
<script src="{{ asset('vendor/countup/countUp.umd.js') }}"></script>

{{-- Chart.js (reports) --}}
<script src="{{ asset('vendor/chartjs/chart.umd.min.js') }}"></script>

{{-- Tom Select (searchable item/supplier selects) --}}
<link href="{{ asset('vendor/tom-select/tom-select.min.css') }}" rel="stylesheet">
<script src="{{ asset('vendor/tom-select/tom-select.complete.min.js') }}"></script>

{{-- Lucide icons (framework-agnostic SVGs) --}}
<script src="{{ asset('vendor/lucide/lucide.min.js') }}"></script>

{{-- Alpine.js — loaded LAST + deferred so all data helpers are defined first --}}
<script defer src="{{ asset('vendor/alpine/alpine.min.js') }}"></script>

{{-- CPSU theme layer: brand chrome, DataTables restyle, motion primitives --}}
<style>
  /* Self-hosted Inter — no Google Fonts dependency (works offline) */
  @font-face { font-family:'Inter'; font-style:normal; font-weight:400; font-display:swap; src:url('{{ asset('vendor/fonts/inter/inter-400.woff2') }}') format('woff2'); }
  @font-face { font-family:'Inter'; font-style:normal; font-weight:500; font-display:swap; src:url('{{ asset('vendor/fonts/inter/inter-500.woff2') }}') format('woff2'); }
  @font-face { font-family:'Inter'; font-style:normal; font-weight:600; font-display:swap; src:url('{{ asset('vendor/fonts/inter/inter-600.woff2') }}') format('woff2'); }
  @font-face { font-family:'Inter'; font-style:normal; font-weight:700; font-display:swap; src:url('{{ asset('vendor/fonts/inter/inter-700.woff2') }}') format('woff2'); }
  @font-face { font-family:'Inter'; font-style:normal; font-weight:800; font-display:swap; src:url('{{ asset('vendor/fonts/inter/inter-800.woff2') }}') format('woff2'); }

  :root {
    --cpsu-green: #0B6E2E; --cpsu-green-dark: #074A1F;
    --cpsu-gold: #FFD500;  --cpsu-gold-dark: #E6BF00;
    --cpsu-black: #1A1A1A; --cpsu-white: #FFFFFF;
    --cpsu-gray-bg: #F7F8F5; --cpsu-border: #E3E6DE;
    --cpsu-danger: #DC2626; --cpsu-success: #16A34A;
  }
  body { background: var(--cpsu-gray-bg); color: var(--cpsu-black); font-family: 'Inter', system-ui, sans-serif; }
  [x-cloak] { display: none !important; }

  /* ---- Modern DataTables restyle (rounded, airy, no horizontal scroll) ---- */
  .dataTables_wrapper { position: relative; }
  .dataTables_wrapper .dataTables_filter input,
  .dataTables_wrapper .dataTables_length select {
    border: 1px solid var(--cpsu-border); border-radius: 0.6rem;
    padding: 0.5rem 0.8rem; outline: none; background: #fff; font-size: .875rem;
    transition: border-color .15s, box-shadow .15s;
  }
  .dataTables_wrapper .dataTables_filter input { min-width: 220px; }
  .dataTables_wrapper .dataTables_filter input:focus,
  .dataTables_wrapper .dataTables_length select:focus {
    border-color: var(--cpsu-green); box-shadow: 0 0 0 3px rgba(11,110,46,.12);
  }
  .dataTables_wrapper .dataTables_filter, .dataTables_wrapper .dataTables_length { margin-bottom: .75rem; }
  .dataTables_wrapper .dataTables_info { color: #8a978c; font-size: .8rem; padding-top: 1rem; }

  /* table shell — no fixed layout, wraps instead of overflowing */
  table.dataTable { width: 100% !important; border-collapse: separate !important; border-spacing: 0; }
  table.dataTable thead th {
    background: transparent; color: #6b7a6f; font-weight: 600;
    text-transform: uppercase; font-size: .68rem; letter-spacing: .04em;
    padding: .55rem .85rem !important; border: 0 !important;
    border-bottom: 1.5px solid var(--cpsu-border) !important;
  }
  table.dataTable tbody td {
    padding: .7rem .85rem !important; vertical-align: middle;
    border: 0 !important; border-bottom: 1px solid #f0f2ee !important;
    font-size: .875rem; word-break: break-word;
  }
  table.dataTable tbody tr { transition: background-color .12s; }
  table.dataTable tbody tr:hover { background: var(--cpsu-gray-bg); }
  table.dataTable tbody tr:last-child td { border-bottom: 0 !important; }
  table.dataTable.no-footer { border-bottom: 0 !important; }

  /* pagination pills */
  .dataTables_wrapper .dataTables_paginate { padding-top: .85rem; }
  .dataTables_wrapper .dataTables_paginate .paginate_button {
    border-radius: .55rem !important; margin: 0 2px; padding: .35rem .75rem !important;
    border: 1px solid transparent !important; color: #55635a !important;
  }
  .dataTables_wrapper .dataTables_paginate .paginate_button.current,
  .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
    background: var(--cpsu-green) !important; color: #fff !important;
    border-color: var(--cpsu-green) !important; box-shadow: 0 2px 6px rgba(11,110,46,.25);
  }
  .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
    background: var(--cpsu-gray-bg) !important; border-color: var(--cpsu-border) !important;
    color: var(--cpsu-green) !important;
  }
  .dataTables_wrapper .dataTables_paginate .paginate_button.disabled { opacity: .4; }

  /* Responsive extension — expandable child rows replace horizontal scroll */
  table.dataTable.dtr-inline.collapsed > tbody > tr > td.dtr-control:first-child { padding-left: 2rem; }
  table.dataTable.dtr-inline.collapsed > tbody > tr > td.dtr-control:first-child:before {
    top: 50%; left: .6rem; transform: translateY(-50%);
    height: 1.05rem; width: 1.05rem; line-height: 1rem; font-size: .8rem;
    border-radius: .35rem; background: var(--cpsu-green); border: 0; box-shadow: none;
    color: #fff; content: '+'; display: inline-flex; align-items: center; justify-content: center;
  }
  table.dataTable.dtr-inline.collapsed > tbody > tr.parent > td.dtr-control:first-child:before { background: var(--cpsu-danger); content: '-'; }
  table.dataTable > tbody > tr.child ul.dtr-details { width: 100%; }
  table.dataTable > tbody > tr.child ul.dtr-details > li {
    border-bottom: 1px solid #f0f2ee; padding: .45rem 0; display: flex; gap: .5rem;
  }
  table.dataTable > tbody > tr.child .dtr-title { min-width: 8rem; font-weight: 600; color: #6b7a6f; font-size: .75rem; text-transform: uppercase; }
  table.dataTable > tbody > tr.child { background: var(--cpsu-gray-bg); }

  /* Static (non-DataTable) tables: wrap content, never force horizontal scroll */
  .cpsu-table { width: 100%; table-layout: auto; }
  .cpsu-table td, .cpsu-table th { word-break: break-word; overflow-wrap: anywhere; }

  /* Tom Select on-brand focus */
  .ts-control { border-radius: .5rem !important; border-color: var(--cpsu-border) !important; padding: .4rem .6rem !important; }
  .ts-control.focus { border-color: var(--cpsu-green) !important; box-shadow: 0 0 0 3px rgba(11,110,46,.12) !important; }

  /* Badge cross-fade for payment status toggle */
  .badge-fade { transition: background-color .35s ease, color .35s ease, border-color .35s ease; }

  /* DataTables skeleton loader (shown while AJAX in flight) */
  div.dataTables_processing.card { background: transparent !important; border: 0 !important; box-shadow: none !important; }
  .cpsu-skeleton { display: grid; gap: .5rem; padding: .5rem 0; }
  .cpsu-skeleton > div { height: 2.25rem; border-radius: .5rem; background: linear-gradient(90deg,#eef1ec 25%,#f6f8f4 37%,#eef1ec 63%); background-size: 400% 100%; animation: cpsu-shimmer 1.2s ease infinite; }
  @keyframes cpsu-shimmer { 0% { background-position: 100% 0; } 100% { background-position: 0 0; } }
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

  // Server-side DataTable factory with CPSU chrome + skeleton loader.
  window.CPSU.dataTable = function (selector, ajaxUrl, columns, opts) {
    opts = opts || {};
    return $(selector).DataTable(Object.assign({
      processing: true, serverSide: true,
      responsive: { details: { type: 'column', target: 'tr' } },
      autoWidth: false,
      // keep the first column + the last (actions) visible longest; collapse the middle first
      columnDefs: [
        { responsivePriority: 1, targets: 0 },
        { responsivePriority: 2, targets: -1 },
      ],
      ajax: ajaxUrl, columns: columns,
      order: opts.order || [[0, 'asc']],
      pageLength: opts.pageLength || 10,
      lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
      language: {
        search: '', searchPlaceholder: 'Search…',
        processing: '<div class="cpsu-skeleton"><div></div><div></div><div></div><div></div></div>',
        emptyTable: 'No records found.', zeroRecords: 'No matching records.',
      },
      drawCallback: function () { window.lucide && lucide.createIcons(); },
    }, opts.overrides || {}));
  };

  // Registry of DataTable reload fns, keyed by resource, so AJAX writes can
  // refresh their table without a full page reload.
  window.reloadTable = {};

  // Themed delete: SweetAlert2 confirm -> AJAX DELETE -> reload table.
  window.CPSU.deleteResource = function (url, label, resource) {
    CPSU.confirm({
      title: 'Delete ' + (label || 'record') + '?',
      text: 'This action cannot be undone.',
      confirmText: 'Yes, delete',
    }).then(function (r) {
      if (!r.isConfirmed) return;
      $.ajax({ url: url, method: 'POST', data: { _method: 'DELETE' } })
        .done(function () {
          CPSU.toast((label ? label.charAt(0).toUpperCase() + label.slice(1) : 'Record') + ' deleted', 'success');
          if (resource && window.reloadTable[resource]) window.reloadTable[resource]();
        })
        .fail(function (xhr) {
          CPSU.toast((xhr.responseJSON && xhr.responseJSON.message) || 'Could not delete record', 'error');
        });
    });
  };

  // Inline is_active toggle (AJAX PATCH, no reload).
  window.CPSU.toggleActive = function (url, el) {
    var on = el.getAttribute('aria-checked') === 'true';
    $.ajax({ url: url, method: 'PATCH', data: { is_active: on ? 0 : 1 } })
      .done(function () {
        el.setAttribute('aria-checked', on ? 'false' : 'true');
        el.classList.toggle('bg-cpsu-green', !on);
        el.classList.toggle('bg-gray-300', on);
        el.querySelector('span').classList.toggle('translate-x-5', !on);
        CPSU.toast('Status updated', 'success');
      })
      .fail(function () { CPSU.toast('Could not update status', 'error'); });
  };

  // Open a resource form modal in create/edit mode. Page-level Alpine forms
  // listen for 'resource-create' / 'resource-edit' to (re)populate fields.
  window.openCreate = function (resource) {
    window.dispatchEvent(new CustomEvent('resource-create', { detail: { resource: resource } }));
    window.dispatchEvent(new CustomEvent('open-modal', { detail: resource + '-form' }));
  };
  window.openEdit = function (resource, data) {
    window.dispatchEvent(new CustomEvent('resource-edit', { detail: { resource: resource, data: data } }));
    window.dispatchEvent(new CustomEvent('open-modal', { detail: resource + '-form' }));
  };

  // Alpine factory powering every setup CRUD modal form (create/edit + AJAX submit).
  window.setupForm = function (cfg) {
    return {
      mode: 'create', modalTitle: '', submitting: false,
      form: JSON.parse(JSON.stringify(cfg.blank)),
      errors: {},
      init() {
        var self = this;
        window.addEventListener('resource-create', function (e) { if (e.detail.resource === cfg.resource) self.startCreate(); });
        window.addEventListener('resource-edit', function (e) { if (e.detail.resource === cfg.resource) self.startEdit(e.detail.data); });
      },
      startCreate() { this.mode = 'create'; this.form = JSON.parse(JSON.stringify(cfg.blank)); this.errors = {}; this.modalTitle = 'New ' + cfg.singular; },
      startEdit(data) { this.mode = 'edit'; this.form = Object.assign(JSON.parse(JSON.stringify(cfg.blank)), data); this.errors = {}; this.modalTitle = 'Edit ' + cfg.singular; },
      err(field) { return this.errors[field] ? this.errors[field][0] : ''; },
      async submit() {
        this.submitting = true; this.errors = {};
        var url = this.mode === 'create' ? cfg.storeUrl : cfg.updateUrl.replace('__ID__', this.form.id);
        var payload = Object.assign({}, this.form);
        if (this.mode === 'edit') payload._method = 'PUT';
        try {
          var res = await fetch(url, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(payload),
          });
          if (res.status === 422) { var j = await res.json(); this.errors = j.errors || {}; this.submitting = false; return; }
          if (!res.ok) { var j2 = await res.json().catch(function () { return {}; }); CPSU.toast(j2.message || 'Something went wrong', 'error'); this.submitting = false; return; }
          window.dispatchEvent(new CustomEvent('close-modal', { detail: cfg.resource + '-form' }));
          if (window.reloadTable[cfg.resource]) window.reloadTable[cfg.resource]();
          CPSU.toast(cfg.singular + (this.mode === 'create' ? ' created' : ' updated'), 'success');
        } catch (e) { CPSU.toast('Network error', 'error'); }
        this.submitting = false;
      },
    };
  };

  document.addEventListener('DOMContentLoaded', () => {
    if (window.lucide) lucide.createIcons();
    if (window.AOS) AOS.init({ duration: 500, once: true, offset: 40 });
  });
  // re-draw lucide icons after Alpine/DataTables inject DOM
  document.addEventListener('alpine:initialized', () => window.lucide && lucide.createIcons());
</script>

@stack('head')
