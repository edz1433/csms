@extends('layouts.app')

@section('title', 'Inventory '.$session->reference)
@section('header', $session->title)
@section('subheader', $session->reference.' · '.($session->location?->name ?? 'All campuses / offices'))

@section('content')
<div x-data="inventorySheet({
        statusUrl: @js(route('inventory.status')),
        countUrl: @js(route('inventory.count')),
        sessionId: {{ $session->id }},
        active: {{ $session->isActive() ? 'true' : 'false' }},
        progress: @js($progress),
        rows: {{ Illuminate\Support\Js::from($rows) }},
     })"
     class="space-y-4">

    <div class="flex flex-wrap items-center gap-2">
        <a href="{{ route('inventory.index') }}" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-cpsu-green">
            <i data-lucide="arrow-left" class="w-4 h-4"></i> All inventories
        </a>
        <span class="ml-auto"></span>
        @if ($session->isActive())
            <x-ui.button variant="secondary" icon="scan-line" :href="route('inventory.scanner')">Open Scanner</x-ui.button>
            <x-action-guard>
                <x-ui.button variant="ghost" icon="circle-stop" x-on:click="closeSession()">Uncast (close)</x-ui.button>
            </x-action-guard>
        @elseif ($session->canBeCast())
            <x-action-guard>
                <x-ui.button variant="primary" :icon="$session->isClosed() ? 'rotate-ccw' : 'play'"
                             x-on:click="cast({{ $session->isClosed() ? 'true' : 'false' }})">
                    {{ $session->isClosed() ? 'Cast again' : 'Cast inventory' }}
                </x-ui.button>
            </x-action-guard>
        @endif
    </div>

    {{-- Draft notice --}}
    @if ($session->isDraft())
        <div class="rounded-xl border border-amber-300 bg-amber-50 p-4 flex flex-wrap items-center gap-3" data-aos="fade-up">
            <i data-lucide="info" class="w-5 h-5 text-amber-600 shrink-0"></i>
            <div class="flex-1 min-w-[16rem]">
                <p class="text-sm font-bold text-amber-800">This inventory has not started yet</p>
                <p class="text-xs text-amber-700/90 mt-0.5">
                    The sheet below lists every item with the stock currently on record. Cast the inventory to start
                    counting — quantities are locked until then, and the expected figures refresh at that moment.
                </p>
            </div>
        </div>
    @endif

    {{-- Stat strip --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3" data-aos="fade-up">
        @foreach ([
            ['key' => 'counted',   'label' => 'Counted',       'icon' => 'clipboard-check', 'tone' => 'text-cpsu-green bg-cpsu-green/10'],
            ['key' => 'remaining', 'label' => 'Not counted',   'icon' => 'hourglass',       'tone' => 'text-amber-600 bg-amber-100'],
            ['key' => 'variance',  'label' => 'With variance', 'icon' => 'triangle-alert',  'tone' => 'text-cpsu-danger bg-red-100'],
            ['key' => 'percent',   'label' => 'Completion',    'icon' => 'gauge',           'tone' => 'text-cpsu-black bg-cpsu-bg'],
        ] as $stat)
            <div class="bg-white rounded-xl border border-cpsu-border shadow-sm p-4 flex items-center gap-3">
                <div class="h-10 w-10 rounded-xl flex items-center justify-center {{ $stat['tone'] }}">
                    <i data-lucide="{{ $stat['icon'] }}" class="w-5 h-5"></i>
                </div>
                <div class="min-w-0">
                    <p class="text-xs text-gray-400 uppercase tracking-wide">{{ $stat['label'] }}</p>
                    <p class="text-xl font-extrabold" x-text="progress.{{ $stat['key'] }} + '{{ $stat['key'] === 'percent' ? '%' : '' }}'"></p>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Progress bar --}}
    <div class="bg-white rounded-xl border border-cpsu-border shadow-sm p-4" data-aos="fade-up">
        <div class="flex items-center justify-between text-xs text-gray-500 mb-2">
            <span><span x-text="progress.counted"></span> of <span x-text="progress.total"></span> items counted</span>
            <span class="inline-flex items-center gap-1.5" :class="active ? 'text-cpsu-green font-semibold' : 'text-gray-400'">
                <span class="h-1.5 w-1.5 rounded-full" :class="active ? 'bg-cpsu-green animate-pulse' : 'bg-gray-300'"></span>
                <span x-text="active ? 'Live — scans from phones appear here' : @js($session->isDraft() ? 'Not started' : 'Closed')"></span>
            </span>
        </div>
        <div class="h-2.5 rounded-full bg-cpsu-bg overflow-hidden">
            <div class="h-full rounded-full bg-cpsu-green" style="transition:width .6s cubic-bezier(.22,1,.36,1)"
                 :style="'width:' + progress.percent + '%'"></div>
        </div>
    </div>

    {{-- Count sheet --}}
    <div class="bg-white rounded-xl border border-cpsu-border shadow-sm overflow-hidden" data-aos="fade-up">
        <div class="px-5 py-4 border-b border-cpsu-border flex flex-wrap items-center gap-3">
            <h3 class="font-bold text-sm flex items-center gap-2">
                <i data-lucide="list-checks" class="w-4 h-4 text-cpsu-green"></i> Count sheet
            </h3>
            @if ($session->isActive())
                <span class="text-xs text-gray-400">Type the quantity you counted — a blank line is not counted, and 0 is a valid count.</span>
            @endif

            <div class="ml-auto flex flex-wrap items-center gap-2">
                <input x-model="q" type="search" placeholder="Filter items…"
                       class="rounded-lg border border-cpsu-border px-3 py-1.5 text-sm outline-none focus:border-cpsu-green">
                <select x-model="filter"
                        class="rounded-lg border border-cpsu-border px-3 py-1.5 text-sm bg-white outline-none focus:border-cpsu-green">
                    <option value="">All lines</option>
                    <option value="pending">Not counted</option>
                    <option value="counted">Counted</option>
                    <option value="variance">With variance</option>
                </select>
            </div>
        </div>

        {{-- Fixed-height viewport: the sheet scrolls inside this box so the page
             itself stays put no matter how many items are being counted. --}}
        <div class="overflow-auto" style="max-height:60vh">
            <table class="w-full text-sm">
                <thead class="sticky top-0 z-10">
                    <tr class="text-left text-xs uppercase text-gray-500 bg-cpsu-bg shadow-[inset_0_-1px_0_0_rgba(227,230,222,1)]">
                        <th class="px-5 py-2.5">Item</th>
                        <th class="px-5 py-2.5 w-36">Unit</th>
                        <th class="px-5 py-2.5 w-28 text-right">Previous qty</th>
                        <th class="px-5 py-2.5 w-44 text-center">Counted qty</th>
                        <th class="px-5 py-2.5 w-24 text-right">Variance</th>
                        <th class="px-5 py-2.5 w-36 text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="row in visible()" :key="row.id">
                        <tr class="border-t border-cpsu-border transition-colors duration-700"
                            :class="row._flash ? 'bg-cpsu-green/10' : 'hover:bg-cpsu-bg/50'">
                            <td class="px-5 py-2.5">
                                <p class="font-medium" x-text="row.name"></p>
                                <p class="text-xs text-gray-400 font-mono" x-text="row.stock_number"></p>
                            </td>

                            <td class="px-5 py-2.5">
                                <select x-model.number="row.unit_id" @change="save(row)" :disabled="!active"
                                        class="w-full rounded-lg border border-cpsu-border px-2 py-1.5 text-sm bg-white focus:border-cpsu-green outline-none disabled:bg-cpsu-bg disabled:text-gray-400">
                                    @foreach ($units as $u)
                                        <option value="{{ $u->id }}">{{ $u->abbreviation }}</option>
                                    @endforeach
                                </select>
                            </td>

                            <td class="px-5 py-2.5 text-right tabular-nums text-gray-500" x-text="fmt(row.system_qty)"></td>

                            {{-- Counted qty: blank means untouched; 0 is a real count --}}
                            <td class="px-5 py-2.5">
                                <div class="flex items-stretch">
                                    <button type="button" @click="step(row, -1)" :disabled="!active"
                                            class="rounded-l-lg border border-r-0 border-cpsu-border px-2 text-gray-500 hover:bg-cpsu-bg hover:text-cpsu-green transition disabled:opacity-40">
                                        <i data-lucide="minus" class="w-3.5 h-3.5"></i>
                                    </button>
                                    <input type="number" step="0.01" min="0" inputmode="decimal" placeholder="—"
                                           x-model="row.counted_qty" :disabled="!active"
                                           @keydown.enter.prevent="$event.target.blur()"
                                           @change="save(row)"
                                           class="w-full border-y border-cpsu-border px-2 py-1.5 text-sm text-center font-semibold outline-none focus:border-cpsu-green disabled:bg-cpsu-bg disabled:text-gray-400">
                                    <button type="button" @click="step(row, 1)" :disabled="!active"
                                            class="rounded-r-lg border border-l-0 border-cpsu-border px-2 text-gray-500 hover:bg-cpsu-bg hover:text-cpsu-green transition disabled:opacity-40">
                                        <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                                    </button>
                                </div>
                            </td>

                            <td class="px-5 py-2.5 text-right tabular-nums font-semibold"
                                :class="variance(row) === null ? 'text-gray-300'
                                        : (variance(row) === 0 ? 'text-gray-400'
                                        : (variance(row) > 0 ? 'text-cpsu-green' : 'text-cpsu-danger'))"
                                x-text="variance(row) === null ? '—' : (variance(row) > 0 ? '+' : '') + fmt(variance(row))"></td>

                            <td class="px-5 py-2.5 text-center">
                                <template x-if="row._saving">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-cpsu-bg text-gray-500">
                                        <span class="h-1.5 w-1.5 rounded-full bg-gray-400 animate-pulse"></span> Saving…
                                    </span>
                                </template>
                                <template x-if="!row._saving && row.counted">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-cpsu-green/10 text-cpsu-green"
                                          :title="(row.counted_by || '') + ' · ' + (row.counted_at || '')">
                                        <i data-lucide="check" class="w-3 h-3"></i> Counted
                                    </span>
                                </template>
                                <template x-if="!row._saving && !row.counted">
                                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-700">Not counted</span>
                                </template>
                                <p class="text-[10px] text-gray-400 mt-1" x-show="row.counted && row.counted_at" x-cloak
                                   x-text="row.counted_at"></p>
                            </td>
                        </tr>
                    </template>

                    <tr x-show="!visible().length" x-cloak>
                        <td colspan="6" class="px-5 py-12 text-center text-gray-400">
                            <i data-lucide="search-x" class="w-8 h-8 mx-auto mb-2 opacity-40"></i>
                            <span x-text="rows.length ? 'No line matches this filter.' : 'There are no active items to count — add items first.'"></span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="px-5 py-3 border-t border-cpsu-border text-xs text-gray-400 flex flex-wrap items-center gap-x-4 gap-y-1">
            <span>Showing <b x-text="visible().length"></b> of <b x-text="rows.length"></b> lines</span>
            <span class="ml-auto">Previous qty is the stock on record when this inventory was cast.</span>
        </div>
    </div>

    {{-- Count from a phone --}}
    @if ($session->isActive())
        <div class="bg-white rounded-xl border border-cpsu-border shadow-sm p-5 flex items-center gap-4" data-aos="fade-up">
            <img src="{{ $scannerQr }}" alt="Scanner URL" class="h-20 w-20 shrink-0 rounded-lg border border-cpsu-border p-1">
            <div class="min-w-0">
                <p class="text-sm font-semibold">Count from a phone</p>
                <p class="text-xs text-gray-400 mt-0.5">Scan this to open the QR scanner, or scan an item's own tag. Counts land on this sheet within seconds.</p>
                <p class="text-xs font-mono text-gray-500 mt-1 truncate">{{ $scannerUrl }}</p>
            </div>
        </div>
    @endif

    @if (!$session->isActive())
        <p class="text-xs text-gray-400 text-center">
            Closed {{ $session->closed_at?->format('M d, Y · g:i A') }}
            @if ($session->closer) by {{ $session->closer->name }} @endif
            · this sheet is the record of the count and does not change on-hand stock.
        </p>
    @endif
</div>

@push('scripts')
<script>
  function inventorySheet(cfg) {
    return {
      rows: cfg.rows, progress: cfg.progress, active: cfg.active, q: '', filter: '',

      init() {
        var self = this;
        this.rows.forEach(function (r) { r._saving = false; r._flash = false; });
        if (cfg.active) { setInterval(function () { self.poll(); }, 5000); }
      },

      /* ---------- filtering ---------- */
      visible() {
        var q = this.q.trim().toLowerCase(), filter = this.filter, self = this;
        return this.rows.filter(function (r) {
          if (filter === 'pending' && r.counted) return false;
          if (filter === 'counted' && !r.counted) return false;
          if (filter === 'variance' && !(r.counted && self.variance(r) !== 0)) return false;
          if (!q) return true;
          return ((r.name || '') + ' ' + (r.stock_number || '')).toLowerCase().indexOf(q) !== -1;
        });
      },

      /* ---------- editing ---------- */
      // Blank means "not counted yet"; 0 is a perfectly good count.
      isBlank(v) { return v === '' || v === null || typeof v === 'undefined'; },

      step(row, d) {
        if (!this.active) return;
        var base = this.isBlank(row.counted_qty) ? row.system_qty : Number(row.counted_qty);
        row.counted_qty = Math.max(0, Math.round((base + d) * 100) / 100);
        this.save(row);
      },

      variance(row) {
        if (this.isBlank(row.counted_qty)) return null;
        return Math.round((Number(row.counted_qty) - Number(row.system_qty)) * 100) / 100;
      },

      async save(row) {
        if (!this.active || row._saving) return;
        if (this.isBlank(row.counted_qty)) { return; }        // nothing typed yet
        if (isNaN(row.counted_qty) || Number(row.counted_qty) < 0) {
          CPSU.toast('Quantity cannot be negative.', 'error');
          return;
        }

        row._saving = true;
        try {
          var res = await fetch(cfg.countUrl, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({
              item_id: row.item_id, unit_id: row.unit_id, counted_qty: Number(row.counted_qty),
            }),
          });
          var j = await res.json();
          if (res.status === 409) {                            // closed mid-count
            this.active = false;
            CPSU.toast(j.message, 'error');
            row._saving = false;
            return;
          }
          if (!res.ok) { CPSU.toast('Could not save that count.', 'error'); row._saving = false; return; }

          row.counted = true;
          row.counted_qty = j.count.counted_qty;
          row.unit = j.count.unit;
          row.counted_by = j.count.counted_by;
          row.counted_at = j.count.counted_at;
          this.progress = j.progress;
          this.flash(row);
        } catch (e) { CPSU.toast('Network error — that count was not saved.', 'error'); }
        row._saving = false;
      },

      flash(row) {
        row._flash = true;
        setTimeout(function () { row._flash = false; }, 900);
      },

      /* ---------- live ---------- */
      async poll() {
        try {
          var res = await fetch(cfg.statusUrl, { headers: { 'Accept': 'application/json' } });
          var j = await res.json();
          if (!j.active || !j.session || j.session.id !== cfg.sessionId) { window.location.reload(); return; }
          // Someone counted elsewhere (a phone, another PC) — pick the sheet up.
          if (j.progress.counted !== this.progress.counted) { this.reloadSoon(); }
          this.progress = j.progress;
        } catch (e) { /* keep polling */ }
      },
      reloadSoon() {
        if (this._pending) return;
        this._pending = true;
        setTimeout(function () { window.location.reload(); }, 1500);
      },

      fmt(n) { return (Number(n) || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },

      cast(reopening) {
        CPSU.confirm({
          icon: 'question',
          title: reopening ? 'Open this inventory again?' : 'Start this inventory?',
          html: reopening
            ? 'Counting re-opens on this sheet. Everything already counted is kept — only the lines '
              + 'still waiting get their expected quantities refreshed.'
              + '<br><span style="font-size:.85rem;color:#888">QR scanners go live again immediately.</span>'
            : 'All <b>' + this.rows.length + '</b> items on this sheet will be opened for counting, '
              + 'with their expected quantities refreshed to the stock on record right now.'
              + '<br><span style="font-size:.85rem;color:#888">QR scanners go live immediately.</span>',
          confirmText: reopening ? 'Yes, open it again' : 'Yes, start counting',
        }).then(function (r) {
          if (!r.isConfirmed) return;
          $.ajax({ url: '/inventory/' + cfg.sessionId + '/cast', method: 'PATCH' })
            .done(function () { CPSU.toast('Counting is open.', 'success'); setTimeout(function () { location.reload(); }, 700); })
            .fail(function (x) { CPSU.toast((x.responseJSON && x.responseJSON.message) || 'Could not cast.', 'error'); });
        });
      },

      closeSession() {
        CPSU.confirm({
          title: 'Close this inventory?',
          text: 'Scanners stop accepting counts right away, and a closed inventory cannot be cast again.',
          confirmText: 'Close inventory',
        }).then(function (r) {
          if (!r.isConfirmed) return;
          $.ajax({ url: '/inventory/' + cfg.sessionId + '/close', method: 'PATCH' })
            .done(function () { CPSU.toast('Inventory closed.', 'success'); setTimeout(function () { location.reload(); }, 600); })
            .fail(function () { CPSU.toast('Could not close the inventory.', 'error'); });
        });
      },
    };
  }
</script>
@endpush
@endsection
