@extends('layouts.app')

@section('title', 'Physical Inventory')
@section('header', 'Physical Inventory')
@section('subheader', 'Cast an inventory, then count on this PC or with the QR scanner')

@section('content')
<div x-data="inventoryHub({
        statusUrl: @js(route('inventory.status')),
        storeUrl: @js(route('inventory.store')),
        labelsUrl: @js(route('inventory.labels')),
        castUrlTemplate: @js(route('inventory.cast', ['session' => '__ID__'])),
        closeUrlTemplate: @js(route('inventory.close', ['session' => '__ID__'])),
        itemCount: {{ $itemCount }},
        active: {{ $active ? 'true' : 'false' }},
        progress: @js($progress ?? ['counted' => 0, 'total' => 0, 'remaining' => 0, 'variance' => 0, 'percent' => 0]),
     })"
     class="space-y-4">

    {{-- ============ Active inventory hero ============ --}}
    @if ($active)
        <div class="relative overflow-hidden rounded-2xl border border-cpsu-green/30 shadow-sm" data-aos="fade-up"
             style="background:linear-gradient(135deg,#0B6E2E 0%,#074A1F 100%)">
            <div aria-hidden="true" class="absolute -right-10 -top-10 h-56 w-56 rounded-full bg-cpsu-gold/10"></div>
            <div aria-hidden="true" class="absolute -right-24 top-16 h-56 w-56 rounded-full bg-white/5"></div>

            <div class="relative p-5 sm:p-7 text-white">
                <div class="flex flex-wrap items-start gap-4">
                    <div class="flex-1 min-w-[16rem]">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-2.5 py-1 text-[11px] font-bold uppercase tracking-widest">
                            <span class="h-1.5 w-1.5 rounded-full bg-cpsu-gold animate-pulse"></span> Inventory cast
                        </span>
                        <h2 class="text-2xl font-extrabold mt-3 leading-tight">{{ $active->title }}</h2>
                        <p class="text-white/70 text-sm mt-1 font-mono">{{ $active->reference }}</p>
                        <p class="text-white/70 text-sm mt-2">
                            Started {{ $active->started_at->format('M d, Y · g:i A') }} by {{ $active->starter?->name }}
                        </p>
                    </div>

                    {{-- Progress ring --}}
                    <div class="shrink-0 text-center">
                        <div class="relative h-28 w-28">
                            <svg viewBox="0 0 120 120" class="h-28 w-28 -rotate-90">
                                <circle cx="60" cy="60" r="52" fill="none" stroke="rgba(255,255,255,.18)" stroke-width="12"></circle>
                                <circle cx="60" cy="60" r="52" fill="none" stroke="#FFD500" stroke-width="12" stroke-linecap="round"
                                        :stroke-dasharray="327" :stroke-dashoffset="327 - (327 * progress.percent / 100)"
                                        style="transition:stroke-dashoffset .6s cubic-bezier(.22,1,.36,1)"></circle>
                            </svg>
                            <div class="absolute inset-0 flex flex-col items-center justify-center">
                                <span class="text-2xl font-extrabold" x-text="progress.percent + '%'"></span>
                                <span class="text-[10px] uppercase tracking-wider text-white/60">counted</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Live stats --}}
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-6">
                    @foreach ([
                        ['key' => 'counted', 'label' => 'Items counted', 'icon' => 'clipboard-check'],
                        ['key' => 'remaining', 'label' => 'Remaining', 'icon' => 'hourglass'],
                        ['key' => 'variance', 'label' => 'With variance', 'icon' => 'triangle-alert'],
                        ['key' => 'total', 'label' => 'Active items', 'icon' => 'package'],
                    ] as $stat)
                        <div class="rounded-xl bg-white/10 backdrop-blur px-4 py-3">
                            <div class="flex items-center gap-2 text-white/70 text-[11px] uppercase tracking-wide">
                                <i data-lucide="{{ $stat['icon'] }}" class="w-3.5 h-3.5"></i> {{ $stat['label'] }}
                            </div>
                            <p class="text-2xl font-extrabold mt-1" x-text="progress.{{ $stat['key'] }}"></p>
                        </div>
                    @endforeach
                </div>

                <div class="flex flex-wrap gap-2 mt-6">
                    <a href="{{ route('inventory.show', $active) }}"
                       class="inline-flex items-center gap-2 bg-cpsu-gold hover:brightness-95 text-cpsu-black font-bold rounded-lg px-5 py-2.5 text-sm transition active:scale-95">
                        <i data-lucide="list-checks" class="w-4 h-4"></i> Count sheet
                    </a>
                    <a href="{{ route('inventory.scanner') }}"
                       class="inline-flex items-center gap-2 bg-white/15 hover:bg-white/25 text-white font-semibold rounded-lg px-5 py-2.5 text-sm transition active:scale-95">
                        <i data-lucide="scan-line" class="w-4 h-4"></i> QR Scanner
                    </a>
                    <a href="{{ route('inventory.labels') }}" target="_blank"
                       class="inline-flex items-center gap-2 bg-white/15 hover:bg-white/25 text-white font-semibold rounded-lg px-5 py-2.5 text-sm transition active:scale-95">
                        <i data-lucide="qr-code" class="w-4 h-4"></i> Print QR labels
                    </a>
                    <x-action-guard>
                        <button type="button" @click="closeSession({{ $active->id }})"
                                class="inline-flex items-center gap-2 bg-white/10 hover:bg-cpsu-danger text-white font-semibold rounded-lg px-5 py-2.5 text-sm transition active:scale-95">
                            <i data-lucide="circle-stop" class="w-4 h-4"></i> Uncast (close)
                        </button>
                    </x-action-guard>
                </div>
            </div>
        </div>
    @endif

    {{-- ============ Item QR tags ============ --}}
    <div class="bg-white rounded-xl border border-cpsu-border shadow-sm p-5" data-aos="fade-up">
        <div class="flex flex-wrap items-start gap-5">
            <div class="h-28 w-28 shrink-0 rounded-lg border border-dashed border-cpsu-border p-2 flex flex-col items-center justify-center text-center">
                <i data-lucide="qr-code" class="w-10 h-10 text-cpsu-green"></i>
                <span class="text-[9px] text-gray-400 mt-1 uppercase tracking-wide">Inventory tag</span>
            </div>

            <div class="flex-1 min-w-[18rem]">
                <h3 class="font-bold text-sm flex items-center gap-2">
                    <i data-lucide="printer" class="w-4 h-4 text-cpsu-green"></i> Item QR tags
                </h3>
                <p class="text-sm text-gray-500 mt-1">
                    Each tag is a little card — QR, stock number, item name, description, unit and account title,
                    with blanks for a written count. Cut along the dashed line and post it on the shelf or bin.
                    A single item's tag is also on its row in <a href="{{ route('items.index') }}" class="text-cpsu-green font-semibold hover:underline">Items / Stocks</a>.
                </p>

                <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end mt-4">
                    <div class="sm:col-span-5">
                        <label class="block text-xs font-medium text-gray-500 mb-1">Account Title</label>
                        <select id="label-account" x-model="labelAccount"
                                class="w-full rounded-lg border border-cpsu-border px-3 py-2 text-sm bg-white focus:border-cpsu-green outline-none">
                            <option value="">All account titles</option>
                            @foreach ($accountTitles as $at)
                                <option value="{{ $at->id }}">{{ $at->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-4">
                        <label class="block text-xs font-medium text-gray-500 mb-1">Coverage</label>
                        <select x-model="labelScope"
                                class="w-full rounded-lg border border-cpsu-border px-3 py-2 text-sm bg-white focus:border-cpsu-green outline-none">
                            <option value="">All active items ({{ number_format($itemCount) }})</option>
                            <option value="1">With stock on hand only</option>
                        </select>
                    </div>
                    <div class="sm:col-span-3">
                        <x-ui.button variant="primary" icon="printer" class="w-full h-[38px]" x-on:click="printLabels()">
                            Print tags
                        </x-ui.button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ============ Inventory list ============ --}}
    <div class="bg-white rounded-xl border border-cpsu-border shadow-sm overflow-hidden" data-aos="fade-up">
        <div class="px-5 py-4 border-b border-cpsu-border flex flex-wrap items-center justify-between gap-3">
            <h3 class="font-bold text-sm flex items-center gap-2">
                <i data-lucide="history" class="w-4 h-4 text-cpsu-green"></i> Inventories
            </h3>
            @unless ($hasActive)
                <x-action-guard>
                    <x-ui.button variant="primary" icon="plus" x-on:click="add()" x-bind:disabled="saving">
                        <span x-show="!saving">New inventory</span>
                        <span x-show="saving" x-cloak>Adding…</span>
                    </x-ui.button>
                </x-action-guard>
            @else
                <span class="text-xs text-gray-400">Close the running inventory before starting another.</span>
            @endunless
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs uppercase text-gray-500 bg-cpsu-bg">
                        <th class="px-5 py-2.5">Reference</th>
                        <th class="px-5 py-2.5">Title</th>
                        <th class="px-5 py-2.5 text-center">Counted</th>
                        <th class="px-5 py-2.5">Started</th>
                        <th class="px-5 py-2.5">Closed</th>
                        <th class="px-5 py-2.5 text-center">Status</th>
                        <th class="px-5 py-2.5 text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sessions as $s)
                        <tr class="border-t border-cpsu-border hover:bg-cpsu-bg/50 transition">
                            <td class="px-5 py-3 font-mono font-semibold">{{ $s->reference }}</td>
                            <td class="px-5 py-3">{{ $s->title }}</td>
                            <td class="px-5 py-3 text-center font-semibold">{{ $s->counts_count }}</td>
                            <td class="px-5 py-3 text-gray-500">
                                {{ $s->isDraft() ? '—' : $s->started_at?->format('M d, Y') }}
                                <span class="block text-xs text-gray-400">{{ $s->starter?->name }}</span>
                            </td>
                            <td class="px-5 py-3 text-gray-500">
                                {{ $s->closed_at?->format('M d, Y') ?? '—' }}
                                @if ($s->closer)<span class="block text-xs text-gray-400">{{ $s->closer->name }}</span>@endif
                            </td>
                            <td class="px-5 py-3 text-center">
                                @if ($s->isActive())
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-cpsu-green/10 text-cpsu-green">
                                        <span class="h-1.5 w-1.5 rounded-full bg-cpsu-green animate-pulse"></span> Cast
                                    </span>
                                @elseif ($s->isDraft())
                                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-700">Not started</span>
                                @else
                                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-500">Closed</span>
                                @endif
                            </td>

                            {{-- Action column: cast a draft or re-cast the latest
                                 closed one, uncast the running one. Older closed
                                 inventories are sealed. --}}
                            <td class="px-5 py-3 text-right whitespace-nowrap">
                                <div class="inline-flex items-center gap-2">
                                    @if ($s->canBeCast())
                                        <x-action-guard>
                                            <button type="button"
                                                    @click="cast({{ $s->id }}, {{ Illuminate\Support\Js::from($s->reference) }}, {{ $s->isClosed() ? 'true' : 'false' }})"
                                                    class="inline-flex items-center gap-1.5 rounded-lg bg-cpsu-green hover:bg-cpsu-green-dark text-white font-semibold px-3 py-1.5 text-xs transition active:scale-95">
                                                <i data-lucide="{{ $s->isClosed() ? 'rotate-ccw' : 'play' }}" class="w-3.5 h-3.5"></i>
                                                {{ $s->isClosed() ? 'Cast again' : 'Cast' }}
                                            </button>
                                        </x-action-guard>
                                    @elseif ($s->isActive())
                                        <x-action-guard>
                                            <button type="button" @click="closeSession({{ $s->id }})"
                                                    class="inline-flex items-center gap-1.5 rounded-lg bg-white border border-cpsu-border hover:border-cpsu-danger hover:text-cpsu-danger font-semibold px-3 py-1.5 text-xs transition active:scale-95">
                                                <i data-lucide="circle-stop" class="w-3.5 h-3.5"></i> Uncast
                                            </button>
                                        </x-action-guard>
                                    @endif
                                    <a href="{{ route('inventory.show', $s) }}"
                                       class="text-cpsu-green hover:underline font-semibold">Open</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-12 text-center text-gray-400">
                                <i data-lucide="scan-line" class="w-8 h-8 mx-auto mb-2 opacity-40"></i>
                                No inventory yet — add one, then cast it from this column to start counting.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('scripts')
<script>
  function inventoryHub(cfg) {
    return {
      progress: cfg.progress, saving: false, labelAccount: '', labelScope: '',
      init() {
        var self = this;
        new TomSelect('#label-account', { create: false, allowEmptyOption: true });
        if (cfg.active) { setInterval(function () { self.poll(); }, 5000); }
      },
      printLabels() {
        var p = {};
        if (this.labelAccount) { p.account_title_id = this.labelAccount; }
        if (this.labelScope) { p.with_stock = 1; }
        window.open(cfg.labelsUrl + '?' + new URLSearchParams(p).toString(), '_blank');
      },
      async poll() {
        try {
          var res = await fetch(cfg.statusUrl, { headers: { 'Accept': 'application/json' } });
          var j = await res.json();
          if (!j.active) { window.location.reload(); return; }
          this.progress = j.progress;
        } catch (e) { /* offline for a beat — try again on the next tick */ }
      },
      async add() {
        this.saving = true;
        try {
          var res = await fetch(cfg.storeUrl, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          });
          var j = await res.json();
          if (!res.ok) { CPSU.toast(j.message || 'Could not add an inventory.', 'error'); this.saving = false; return; }
          CPSU.toast(j.message || ('Inventory ' + j.reference + ' added — cast it to start counting.'),
                     j.existing ? 'info' : 'success');
          setTimeout(function () { location.reload(); }, 700);
        } catch (e) { CPSU.toast('Network error.', 'error'); this.saving = false; }
      },
      cast(id, reference, reopening) {
        CPSU.confirm({
          icon: 'question',
          title: reopening ? 'Open this inventory again?' : 'Start this inventory?',
          html: reopening
            ? '<b>' + reference + '</b> will re-open for counting. Everything already counted is kept — '
              + 'only the lines still waiting get their expected quantities refreshed.'
              + '<br><span style="font-size:.85rem;color:#888">QR scanners go live again immediately.</span>'
            : 'All <b>' + cfg.itemCount + '</b> active items will be added to the count sheet of <b>' + reference + '</b> '
              + 'with the stock they hold right now.<br><span style="font-size:.85rem;color:#888">'
              + 'QR scanners go live immediately, and counting stays open until you uncast it.</span>',
          confirmText: reopening ? 'Yes, open it again' : 'Yes, start counting',
        }).then(function (r) {
          if (!r.isConfirmed) return;
          $.ajax({ url: cfg.castUrlTemplate.replace('__ID__', id), method: 'PATCH' })
            .done(function (d) {
              CPSU.toast(d.reopened
                ? 'Inventory re-opened — counting is live again.'
                : 'Inventory cast — ' + d.seeded + ' items on the sheet.', 'success');
              setTimeout(function () { window.location = d.redirect; }, 800);
            })
            .fail(function (x) { CPSU.toast((x.responseJSON && x.responseJSON.message) || 'Could not cast.', 'error'); });
        });
      },
      closeSession(id) {
        CPSU.confirm({
          title: 'Close this inventory?',
          text: 'Scanners stop accepting counts right away, and a closed inventory cannot be cast again.',
          confirmText: 'Close inventory',
        }).then(function (r) {
          if (!r.isConfirmed) return;
          $.ajax({ url: cfg.closeUrlTemplate.replace('__ID__', id), method: 'PATCH' })
            .done(function () { CPSU.toast('Inventory closed.', 'success'); setTimeout(function () { location.reload(); }, 600); })
            .fail(function () { CPSU.toast('Could not close the inventory.', 'error'); });
        });
      },
    };
  }
</script>
@endpush
@endsection
