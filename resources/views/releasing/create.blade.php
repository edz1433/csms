@extends('layouts.app')

@section('title', 'New Release')
@section('header', 'New Release (RIS)')

@section('content')
<div class="mb-4">
    <a href="{{ route('releases.index') }}" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-cpsu-green">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to Releases
    </a>
</div>

<form
    x-data="releasingForm({
        items: {{ Illuminate\Support\Js::from($items) }},
        units: {{ Illuminate\Support\Js::from($units) }},
        accountTitles: {{ Illuminate\Support\Js::from($accountTitles) }},
        storeUrl: @js(route('releases.store')),
    })"
    @submit.prevent="submit()"
    class="space-y-4 max-w-5xl"
>
    {{-- Header --}}
    <div class="bg-white rounded-xl border border-cpsu-border shadow-sm p-5" data-aos="fade-up">
        <h3 class="font-bold text-sm mb-4 flex items-center gap-2"><i data-lucide="file-text" class="w-4 h-4 text-cpsu-green"></i> Release Details</h3>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="space-y-1">
                <label class="block text-sm font-medium">Fund Cluster <span class="text-cpsu-danger">*</span></label>
                <select x-model="form.fund_cluster_id"
                        class="w-full rounded-lg border px-3 py-2 text-sm bg-white outline-none focus:ring-2 focus:ring-cpsu-green/20"
                        :class="err('fund_cluster_id') ? 'border-cpsu-danger' : 'border-cpsu-border focus:border-cpsu-green'">
                    <option value="">Select fund cluster</option>
                    @foreach ($fundClusters as $fc)
                        <option value="{{ $fc->id }}">{{ $fc->code }} — {{ $fc->name }}</option>
                    @endforeach
                </select>
                <p x-show="err('fund_cluster_id')" x-cloak x-text="err('fund_cluster_id')" class="text-xs text-cpsu-danger"></p>
            </div>
            <div class="space-y-1">
                <label class="block text-sm font-medium">Receiving Campus / Office <span class="text-cpsu-danger">*</span></label>
                <select x-model="form.location_id"
                        class="w-full rounded-lg border px-3 py-2 text-sm bg-white outline-none focus:ring-2 focus:ring-cpsu-green/20"
                        :class="err('location_id') ? 'border-cpsu-danger' : 'border-cpsu-border focus:border-cpsu-green'">
                    <option value="">Select destination</option>
                    @foreach ($locations as $loc)
                        <option value="{{ $loc->id }}">{{ $loc->name }} [{{ ucfirst($loc->type) }}]</option>
                    @endforeach
                </select>
                <p x-show="err('location_id')" x-cloak x-text="err('location_id')" class="text-xs text-cpsu-danger"></p>
            </div>
            <div class="space-y-1">
                <label class="block text-sm font-medium">Date Released <span class="text-cpsu-danger">*</span></label>
                <input x-model="form.released_at" type="date" max="{{ now()->format('Y-m-d') }}"
                       class="w-full rounded-lg border px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-cpsu-green/20"
                       :class="err('released_at') ? 'border-cpsu-danger' : 'border-cpsu-border focus:border-cpsu-green'">
                <p x-show="err('released_at')" x-cloak x-text="err('released_at')" class="text-xs text-cpsu-danger"></p>
            </div>
        </div>
    </div>

    {{-- Line items --}}
    <div class="bg-white rounded-xl border border-cpsu-border shadow-sm p-5" data-aos="fade-up">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-bold text-sm flex items-center gap-2"><i data-lucide="package-minus" class="w-4 h-4 text-cpsu-green"></i> Items to Release</h3>
            <span class="text-xs text-gray-400" x-text="form.lines.length + ' line' + (form.lines.length === 1 ? '' : 's')"></span>
        </div>

        <div class="mb-4">
            <select id="item-picker" placeholder="Search item by name or stock number to add…" autocomplete="off">
                <option value="">Search item…</option>
                @foreach ($items as $it)
                    <option value="{{ $it->id }}">{{ $it->name }} {{ $it->stock_number ? '('.$it->stock_number.')' : '' }}</option>
                @endforeach
            </select>
            <p x-show="err('lines')" x-cloak x-text="err('lines')" class="text-xs text-cpsu-danger mt-1"></p>
        </div>

        <div class="space-y-3">
            <template x-for="(line, i) in form.lines" :key="line._k">
                <div class="rounded-lg border border-cpsu-border p-3"
                     :class="overQty(line) ? 'border-cpsu-danger bg-red-50/40' : ''">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-medium text-sm" x-text="line._name"></p>
                            <p class="text-xs text-gray-400 font-mono" x-text="line._stock"></p>
                        </div>
                        <div class="text-right shrink-0">
                            <span class="text-xs text-gray-400">On hand</span>
                            <p class="text-sm font-bold" :class="overQty(line) ? 'text-cpsu-danger' : 'text-cpsu-green'"
                               x-text="Number(line._onhand).toLocaleString(undefined,{minimumFractionDigits:2})"></p>
                        </div>
                        <button type="button" @click="removeLine(i)" class="text-cpsu-danger hover:bg-red-50 rounded-lg p-1.5 transition shrink-0">
                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                        </button>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-3">
                        <div>
                            <label class="text-xs text-gray-500">Account Title (RCA)</label>
                            <select x-model.number="line.account_title_id"
                                    class="w-full rounded-lg border border-cpsu-border px-2 py-1.5 text-sm bg-white focus:border-cpsu-green outline-none">
                                <template x-for="a in accountTitles" :key="a.id">
                                    <option :value="a.id" x-text="a.name + ' — ' + a.rca_code"></option>
                                </template>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs text-gray-500">Unit</label>
                            <select x-model.number="line.unit_id"
                                    class="w-full rounded-lg border border-cpsu-border px-2 py-1.5 text-sm bg-white focus:border-cpsu-green outline-none">
                                <template x-for="u in units" :key="u.id">
                                    <option :value="u.id" x-text="u.name + ' (' + u.abbreviation + ')'"></option>
                                </template>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs text-gray-500">Quantity</label>
                            <input type="number" step="0.01" min="0.01" x-model.number="line.quantity"
                                   class="w-full rounded-lg border px-2 py-1.5 text-sm outline-none"
                                   :class="overQty(line) ? 'border-cpsu-danger focus:border-cpsu-danger' : 'border-cpsu-border focus:border-cpsu-green'">
                            <p x-show="overQty(line)" x-cloak class="text-xs text-cpsu-danger mt-0.5">Exceeds on-hand stock.</p>
                        </div>
                    </div>
                </div>
            </template>
            <div x-show="!form.lines.length" class="py-8 text-center text-gray-400 text-sm">
                No items yet — search above to add lines.
            </div>
        </div>
    </div>

    {{-- Actions --}}
    <div class="flex items-center justify-between gap-2">
        <p x-show="hasStockError" x-cloak class="text-sm text-cpsu-danger flex items-center gap-1">
            <i data-lucide="alert-triangle" class="w-4 h-4"></i> One or more lines exceed available stock.
        </p>
        <div class="flex items-center gap-2 ml-auto">
            <x-ui.button variant="ghost" :href="route('releases.index')">Cancel</x-ui.button>
            <x-ui.button variant="primary" icon="send" type="submit" x-bind:disabled="submitting || hasStockError || !form.lines.length">
                <span x-show="!submitting">Save Release</span>
                <span x-show="submitting" x-cloak>Saving…</span>
            </x-ui.button>
        </div>
    </div>
</form>

@push('scripts')
<script>
  function releasingForm(cfg) {
    return {
      items: cfg.items, units: cfg.units, accountTitles: cfg.accountTitles,
      submitting: false, errors: {}, _key: 0,
      form: {
        fund_cluster_id: '', location_id: '', released_at: @js(now()->format('Y-m-d')),
        remarks: '', lines: [],
      },
      init() {
        var self = this;
        this.picker = new TomSelect('#item-picker', {
          create: false, maxItems: 1, allowEmptyOption: false,
          onChange: function (val) { if (val) { self.addLine(val); self.picker.clear(); self.picker.blur(); } },
        });
      },
      addLine(itemId) {
        var it = this.items.find(function (x) { return String(x.id) === String(itemId); });
        if (!it) return;
        this.form.lines.push({
          _k: ++this._key, _name: it.name, _stock: it.stock_number || '', _onhand: Number(it.on_hand_qty),
          item_id: it.id,
          account_title_id: it.account_title_id || (this.accountTitles[0] ? this.accountTitles[0].id : ''),
          unit_id: it.unit_id, quantity: 1,
        });
      },
      removeLine(i) { this.form.lines.splice(i, 1); },
      overQty(line) { return Number(line.quantity) > Number(line._onhand); },
      get hasStockError() { return this.form.lines.some((l) => this.overQty(l)); },
      err(field) { return this.errors[field] ? this.errors[field][0] : ''; },
      async submit() {
        if (!this.form.lines.length) { CPSU.toast('Add at least one item line.', 'error'); return; }
        if (this.hasStockError) { CPSU.toast('Fix lines that exceed on-hand stock.', 'error'); return; }
        this.submitting = true; this.errors = {};
        try {
          var res = await fetch(cfg.storeUrl, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(this.form),
          });
          if (res.status === 422) { var j = await res.json(); this.errors = j.errors || {}; CPSU.toast(j.message || 'Please fix the errors.', 'error'); this.submitting = false; return; }
          if (!res.ok) { CPSU.toast('Something went wrong.', 'error'); this.submitting = false; return; }
          var d = await res.json();
          CPSU.toast('Release saved. Stock updated.', 'success');
          setTimeout(function () { window.location = d.redirect; }, 600);
        } catch (e) { CPSU.toast('Network error.', 'error'); this.submitting = false; }
      },
    };
  }
</script>
@endpush
@endsection
