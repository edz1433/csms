@extends('layouts.app')

@section('title', 'New Delivery')
@section('header', 'New Delivery')

@section('content')
<div class="mb-4">
    <a href="{{ route('deliveries.index') }}" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-cpsu-green">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to Deliveries
    </a>
</div>

<form
    x-data="receivingForm({
        items: {{ Illuminate\Support\Js::from($items) }},
        units: {{ Illuminate\Support\Js::from($units) }},
        storeUrl: @js(route('deliveries.store')),
    })"
    @submit.prevent="submit()"
    class="space-y-4 max-w-4xl"
>
    {{-- Header --}}
    <div class="bg-white rounded-xl border border-cpsu-border shadow-sm p-5" data-aos="fade-up">
        <h3 class="font-bold text-sm mb-4 flex items-center gap-2"><i data-lucide="file-text" class="w-4 h-4 text-cpsu-green"></i> Delivery Details</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="space-y-1">
                <label class="block text-sm font-medium">PO Number <span class="text-cpsu-danger">*</span></label>
                <input x-model="form.po_number" type="text" placeholder="e.g. PO-2026-0142"
                       class="w-full rounded-lg border px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-cpsu-green/20"
                       :class="err('po_number') ? 'border-cpsu-danger' : 'border-cpsu-border focus:border-cpsu-green'">
                <p x-show="err('po_number')" x-cloak x-text="err('po_number')" class="text-xs text-cpsu-danger"></p>
            </div>
            <div class="space-y-1">
                <label class="block text-sm font-medium">Supplier</label>
                <select x-model="form.supplier_id"
                        class="w-full rounded-lg border border-cpsu-border px-3 py-2 text-sm bg-white outline-none focus:border-cpsu-green focus:ring-2 focus:ring-cpsu-green/20">
                    <option value="">— None / walk-in —</option>
                    @foreach ($suppliers as $s)
                        <option value="{{ $s->id }}">{{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="space-y-1">
                <label class="block text-sm font-medium">Date Received <span class="text-cpsu-danger">*</span></label>
                <input x-model="form.received_at" type="date" max="{{ now()->format('Y-m-d') }}"
                       class="w-full rounded-lg border px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-cpsu-green/20"
                       :class="err('received_at') ? 'border-cpsu-danger' : 'border-cpsu-border focus:border-cpsu-green'">
                <p x-show="err('received_at')" x-cloak x-text="err('received_at')" class="text-xs text-cpsu-danger"></p>
            </div>
            <div class="space-y-1">
                <label class="block text-sm font-medium">Remarks</label>
                <input x-model="form.remarks" type="text" placeholder="Optional"
                       class="w-full rounded-lg border border-cpsu-border px-3 py-2 text-sm outline-none focus:border-cpsu-green focus:ring-2 focus:ring-cpsu-green/20">
            </div>
        </div>
    </div>

    {{-- Line items --}}
    <div class="bg-white rounded-xl border border-cpsu-border shadow-sm p-5" data-aos="fade-up">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-bold text-sm flex items-center gap-2"><i data-lucide="package-plus" class="w-4 h-4 text-cpsu-green"></i> Items Received</h3>
            <span class="text-xs text-gray-400" x-text="form.lines.length + ' line' + (form.lines.length === 1 ? '' : 's')"></span>
        </div>

        {{-- Search-select to add an item --}}
        <div class="mb-4">
            <select id="item-picker" placeholder="Search item by name or stock number to add…" autocomplete="off">
                <option value="">Search item…</option>
                @foreach ($items as $it)
                    <option value="{{ $it->id }}">{{ $it->name }} {{ $it->stock_number ? '('.$it->stock_number.')' : '' }}</option>
                @endforeach
            </select>
            <p x-show="err('lines')" x-cloak x-text="err('lines')" class="text-xs text-cpsu-danger mt-1"></p>
        </div>

        {{-- Rows --}}
        <div>
            <table class="w-full text-sm">
                <thead x-show="form.lines.length">
                    <tr class="text-left text-xs uppercase text-gray-500 bg-cpsu-bg">
                        <th class="px-3 py-2 rounded-l-lg">Item</th>
                        <th class="px-3 py-2 w-40">Unit</th>
                        <th class="px-3 py-2 w-36">Quantity</th>
                        <th class="px-3 py-2 w-12 rounded-r-lg"></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(line, i) in form.lines" :key="line._k">
                        <tr class="border-b border-cpsu-border">
                            <td class="px-3 py-2">
                                <p class="font-medium" x-text="line._name"></p>
                                <p class="text-xs text-gray-400 font-mono" x-text="line._stock"></p>
                            </td>
                            <td class="px-3 py-2">
                                <select x-model.number="line.unit_id"
                                        class="w-full rounded-lg border border-cpsu-border px-2 py-1.5 text-sm bg-white focus:border-cpsu-green outline-none">
                                    <template x-for="u in units" :key="u.id">
                                        <option :value="u.id" x-text="u.name + ' (' + u.abbreviation + ')'"></option>
                                    </template>
                                </select>
                            </td>
                            <td class="px-3 py-2">
                                <input type="number" step="0.01" min="0.01" x-model.number="line.quantity"
                                       class="w-full rounded-lg border border-cpsu-border px-2 py-1.5 text-sm focus:border-cpsu-green outline-none"
                                       :class="lineErr(i,'quantity') ? 'border-cpsu-danger' : ''">
                            </td>
                            <td class="px-3 py-2 text-center">
                                <button type="button" @click="removeLine(i)" class="text-cpsu-danger hover:bg-red-50 rounded-lg p-1.5 transition">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="!form.lines.length">
                        <td colspan="4" class="px-3 py-8 text-center text-gray-400 text-sm">
                            No items yet — search above to add lines.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Actions --}}
    <div class="flex items-center justify-end gap-2">
        <x-ui.button variant="ghost" :href="route('deliveries.index')">Cancel</x-ui.button>
        <x-ui.button variant="primary" icon="save" type="submit" x-bind:disabled="submitting">
            <span x-show="!submitting">Save Delivery</span>
            <span x-show="submitting" x-cloak>Saving…</span>
        </x-ui.button>
    </div>
</form>

@push('scripts')
<script>
  function receivingForm(cfg) {
    return {
      items: cfg.items, units: cfg.units,
      submitting: false, errors: {}, _key: 0,
      form: {
        po_number: '', supplier_id: '', received_at: @js(now()->format('Y-m-d')),
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
          _k: ++this._key, _name: it.name, _stock: it.stock_number || '',
          item_id: it.id, unit_id: it.unit_id, quantity: 1,
        });
      },
      removeLine(i) { this.form.lines.splice(i, 1); },
      err(field) { return this.errors[field] ? this.errors[field][0] : ''; },
      lineErr(i, field) { return this.errors['lines.' + i + '.' + field] ? this.errors['lines.' + i + '.' + field][0] : ''; },
      async submit() {
        if (!this.form.lines.length) { CPSU.toast('Add at least one item line.', 'error'); return; }
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
          CPSU.toast('Delivery saved. Stock updated.', 'success');
          setTimeout(function () { window.location = d.redirect; }, 600);
        } catch (e) { CPSU.toast('Network error.', 'error'); this.submitting = false; }
      },
    };
  }
</script>
@endpush
@endsection
