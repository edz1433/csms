@extends('layouts.app')

@section('title', 'Count '.$item->name)
@section('header', 'Count item')
@section('subheader', $item->stock_number ? $item->stock_number.' · '.$item->name : $item->name)

@section('content')
<div x-data="scanCount({
        statusUrl: @js(route('inventory.status')),
        countUrl: @js(route('inventory.count')),
        active: {{ $session ? 'true' : 'false' }},
        session: @js($session ? ['reference' => $session->reference, 'title' => $session->title] : null),
        item: @js([
            'id' => $item->id,
            'name' => $item->name,
            'stock_number' => $item->stock_number,
            'unit_id' => $item->unit_id,
            'unit' => $item->unit?->abbreviation,
            'system_qty' => (float) $item->on_hand_qty,
        ]),
        counted: @js($count ? (float) $count->counted_qty : null),
        countedAt: @js($count?->counted_at?->format('M d · g:i A')),
        units: {{ Illuminate\Support\Js::from($units) }},
     })"
     class="max-w-xl mx-auto space-y-4">

    {{-- ===== No active inventory ===== --}}
    <div x-show="!active" x-cloak data-aos="fade-up"
         class="bg-white rounded-2xl border border-cpsu-danger/30 shadow-sm p-8 text-center">
        <div class="h-16 w-16 mx-auto rounded-2xl bg-red-50 flex items-center justify-center mb-4">
            <i data-lucide="scan-line" class="w-8 h-8 text-cpsu-danger"></i>
        </div>
        <h2 class="text-xl font-extrabold">No active inventory</h2>
        <p class="text-sm text-gray-500 mt-2">
            This QR was scanned while no inventory is running, so nothing can be counted yet.
        </p>
        <p class="text-xs text-gray-400 mt-4 inline-flex items-center gap-1.5">
            <span class="h-1.5 w-1.5 rounded-full bg-gray-300 animate-pulse"></span>
            Watching for an inventory to be cast — this page unlocks itself.
        </p>
        <div class="mt-5">
            <x-ui.button variant="ghost" icon="arrow-left" :href="route('inventory.index')">Physical Inventory</x-ui.button>
        </div>
    </div>

    {{-- ===== Count sheet ===== --}}
    <div x-show="active" x-cloak class="space-y-4">
        <div class="rounded-2xl border border-cpsu-green/30 shadow-sm overflow-hidden" data-aos="fade-up"
             style="background:linear-gradient(135deg,#0B6E2E 0%,#074A1F 100%)">
            <div class="p-5 text-white">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-2.5 py-1 text-[11px] font-bold uppercase tracking-widest">
                    <span class="h-1.5 w-1.5 rounded-full bg-cpsu-gold animate-pulse"></span>
                    <span x-text="session ? session.reference : 'Inventory active'"></span>
                </span>
                <h2 class="text-xl font-extrabold mt-3 leading-tight">{{ $item->name }}</h2>
                <p class="text-white/70 text-sm font-mono mt-0.5">{{ $item->stock_number }}</p>
                @if ($item->description)
                    <p class="text-white/60 text-xs mt-2">{{ $item->description }}</p>
                @endif
            </div>
        </div>

        <div class="bg-white rounded-xl border border-cpsu-border shadow-sm p-5 space-y-4" data-aos="fade-up">
            <div class="grid grid-cols-2 gap-3">
                <div class="rounded-lg bg-cpsu-bg p-3">
                    <p class="text-[11px] text-gray-400 uppercase tracking-wide">System qty</p>
                    <p class="text-xl font-extrabold tabular-nums">{{ number_format((float) $item->on_hand_qty, 2) }}</p>
                </div>
                <div class="rounded-lg p-3 transition-colors"
                     :class="variance() === null ? 'bg-cpsu-bg' : (variance() === 0 ? 'bg-cpsu-green/10' : 'bg-amber-100')">
                    <p class="text-[11px] text-gray-400 uppercase tracking-wide">Variance</p>
                    <p class="text-xl font-extrabold tabular-nums"
                       :class="variance() === null ? 'text-gray-400' : (variance() >= 0 ? 'text-cpsu-green' : 'text-cpsu-danger')"
                       x-text="variance() === null ? '—' : (variance() > 0 ? '+' : '') + variance().toFixed(2)"></p>
                </div>
            </div>

            <div class="space-y-1">
                <label class="block text-sm font-medium">Counted quantity <span class="text-cpsu-danger">*</span></label>
                <div class="flex items-stretch">
                    <button type="button" @click="step(-1)"
                            class="rounded-l-lg border border-r-0 border-cpsu-border px-4 text-gray-500 hover:bg-cpsu-bg hover:text-cpsu-green transition">
                        <i data-lucide="minus" class="w-5 h-5"></i>
                    </button>
                    <input x-model.number="qty" type="number" step="0.01" min="0" inputmode="decimal"
                           class="w-full border-y border-cpsu-border px-3 py-3 text-center text-2xl font-extrabold outline-none focus:border-cpsu-green">
                    <button type="button" @click="step(1)"
                            class="rounded-r-lg border border-l-0 border-cpsu-border px-4 text-gray-500 hover:bg-cpsu-bg hover:text-cpsu-green transition">
                        <i data-lucide="plus" class="w-5 h-5"></i>
                    </button>
                </div>
            </div>

            <div class="space-y-1">
                <label class="block text-sm font-medium">Unit</label>
                <select x-model.number="unitId"
                        class="w-full rounded-lg border border-cpsu-border px-3 py-2.5 text-sm bg-white outline-none focus:border-cpsu-green">
                    @foreach ($units as $u)
                        <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->abbreviation }})</option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-400" x-show="unitId !== item.unit_id" x-cloak>
                    Saving also changes this item's unit to <b x-text="unitLabel(unitId)"></b>.
                </p>
            </div>

            <div class="space-y-1">
                <label class="block text-sm font-medium">Remarks</label>
                <input x-model="remarks" type="text" placeholder="Optional"
                       class="w-full rounded-lg border border-cpsu-border px-3 py-2 text-sm outline-none focus:border-cpsu-green">
            </div>

            <x-ui.button variant="primary" icon="save" class="w-full py-3" x-on:click="save()" x-bind:disabled="saving">
                <span x-show="!saving">Save count</span>
                <span x-show="saving" x-cloak>Saving…</span>
            </x-ui.button>

            <p class="text-xs text-gray-400 text-center" x-show="countedAt" x-cloak>
                Already counted at <span x-text="countedAt"></span> — saving overwrites it.
            </p>
        </div>

        <div class="bg-white rounded-xl border border-cpsu-border shadow-sm p-5 text-center" data-aos="fade-up">
            <p class="text-xs text-gray-400 uppercase tracking-wide mb-3">This item's QR label</p>
            <img src="{{ $qr }}" alt="QR for {{ $item->name }}" class="mx-auto h-40 w-40">
            <p class="text-xs text-gray-400 mt-2">Scanning it always opens this page.</p>
            <div class="flex justify-center gap-2 mt-4">
                <x-ui.button variant="ghost" icon="scan-line" :href="route('inventory.scanner')">Scan another</x-ui.button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
  function scanCount(cfg) {
    return {
      active: cfg.active, session: cfg.session, item: cfg.item, units: cfg.units,
      qty: cfg.counted !== null ? cfg.counted : cfg.item.system_qty,
      unitId: cfg.item.unit_id, remarks: '', countedAt: cfg.countedAt, saving: false,

      init() {
        var self = this;
        // A phone left on this page picks up the session opening or closing.
        setInterval(function () { self.poll(); }, 5000);
      },
      async poll() {
        try {
          var res = await fetch(cfg.statusUrl, { headers: { 'Accept': 'application/json' } });
          var j = await res.json();
          var was = this.active;
          this.active = j.active;
          this.session = j.session;
          if (was && !j.active) { CPSU.toast('The inventory was closed.', 'warning'); }
          if (!was && j.active) { CPSU.toast('An inventory is now active — you can count.', 'success'); }
        } catch (e) { /* keep polling */ }
      },
      async save() {
        if (this.qty === '' || this.qty === null || isNaN(this.qty) || this.qty < 0) {
          CPSU.toast('Enter the counted quantity.', 'error');
          return;
        }
        this.saving = true;
        try {
          var res = await fetch(cfg.countUrl, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({
              item_id: this.item.id, unit_id: this.unitId,
              counted_qty: this.qty, remarks: this.remarks || null,
            }),
          });
          var j = await res.json();
          if (res.status === 409) { this.active = false; CPSU.toast(j.message, 'error'); this.saving = false; return; }
          if (!res.ok) { CPSU.toast('Could not save that count.', 'error'); this.saving = false; return; }
          this.countedAt = j.count.counted_at;
          this.item.unit = j.count.unit;
          CPSU.toast('Count saved.', 'success');
        } catch (e) { CPSU.toast('Network error.', 'error'); }
        this.saving = false;
      },
      variance() {
        if (this.qty === '' || this.qty === null || isNaN(this.qty)) return null;
        return Math.round((Number(this.qty) - Number(this.item.system_qty)) * 100) / 100;
      },
      step(d) { this.qty = Math.max(0, Math.round(((Number(this.qty) || 0) + d) * 100) / 100); },
      unitLabel(id) {
        var u = this.units.find(function (x) { return Number(x.id) === Number(id); });
        return u ? u.abbreviation : '';
      },
    };
  }
</script>
@endpush
@endsection
