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
    class="space-y-4"
>
    {{-- Header --}}
    <div class="bg-white rounded-xl border border-cpsu-border shadow-sm p-5" data-aos="fade-up">
        <h3 class="font-bold text-sm mb-4 flex items-center gap-2"><i data-lucide="file-text" class="w-4 h-4 text-cpsu-green"></i> Delivery Details</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="space-y-1">
                <label class="block text-sm font-medium">PO Number <span class="text-cpsu-danger">*</span></label>
                <input x-model="form.po_number" type="text" placeholder="e.g. PO-2026-0142"
                       class="w-full rounded-lg border px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-cpsu-green/20"
                       :class="err('po_number') ? 'border-cpsu-danger' : 'border-cpsu-border focus:border-cpsu-green'">
                <p x-show="err('po_number')" x-cloak x-text="err('po_number')" class="text-xs text-cpsu-danger"></p>
            </div>
            <div class="space-y-1">
                <label class="block text-sm font-medium">Fund Cluster</label>
                <select x-model="form.fund_cluster_id"
                        class="w-full rounded-lg border px-3 py-2 text-sm bg-white outline-none focus:ring-2 focus:ring-cpsu-green/20"
                        :class="err('fund_cluster_id') ? 'border-cpsu-danger' : 'border-cpsu-border focus:border-cpsu-green'">
                    <option value="">— None —</option>
                    @foreach ($fundClusters as $fc)
                        <option value="{{ $fc->id }}">{{ $fc->code }} — {{ $fc->name }}</option>
                    @endforeach
                </select>
                <p x-show="err('fund_cluster_id')" x-cloak x-text="err('fund_cluster_id')" class="text-xs text-cpsu-danger"></p>
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
            <div class="space-y-1 sm:col-span-2 lg:col-span-4">
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
            <div class="flex items-center gap-3">
                <span class="text-xs text-gray-400" x-text="form.lines.length + ' line' + (form.lines.length === 1 ? '' : 's')"></span>
                <button type="button" x-show="form.lines.length" @click="clearLines()"
                        class="text-xs text-cpsu-danger hover:underline inline-flex items-center gap-1">
                    <i data-lucide="eraser" class="w-3.5 h-3.5"></i> Clear all
                </button>
            </div>
        </div>

        {{-- Search-select to add an item --}}
        <div class="mb-4">
            <select id="item-picker" placeholder="Search item by name or stock number to add…" autocomplete="off">
                <option value="">Search item…</option>
                @foreach ($items as $it)
                    <option value="{{ $it->id }}">{{ $it->name }} {{ $it->stock_number ? '('.$it->stock_number.')' : '' }}</option>
                @endforeach
            </select>
            <p class="text-xs text-gray-400 mt-1">Tip: pick an item to add a row — the search stays open so you can add several in a row. Re-picking an item bumps its quantity.</p>
            <p x-show="err('lines')" x-cloak x-text="err('lines')" class="text-xs text-cpsu-danger mt-1"></p>
        </div>

        {{-- Rows --}}
        <div>
            <table class="w-full text-sm">
                <thead x-show="form.lines.length">
                    <tr class="text-left text-xs uppercase text-gray-500 bg-cpsu-bg">
                        <th class="px-3 py-2 rounded-l-lg">Item</th>
                        <th class="px-3 py-2 w-36">Unit</th>
                        <th class="px-3 py-2 w-44">Quantity</th>
                        <th class="px-3 py-2 w-32">Unit Cost (₱)</th>
                        <th class="px-3 py-2 w-28 text-right">Amount</th>
                        <th class="px-3 py-2 w-12 rounded-r-lg"></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(line, i) in form.lines" :key="line._k">
                        <tr class="border-b border-cpsu-border transition-colors duration-700"
                            :class="line._flash ? 'bg-cpsu-green/10' : ''">
                            <td class="px-3 py-2">
                                <p class="font-medium" x-text="line._name"></p>
                                <p class="text-xs text-gray-400 font-mono" x-text="line._stock"></p>
                            </td>
                            <td class="px-3 py-2">
                                <select x-model.number="line.unit_id"
                                        class="w-full rounded-lg border border-cpsu-border px-2 py-1.5 text-sm bg-white focus:border-cpsu-green outline-none">
                                    {{-- Server-rendered so x-model finds the item's own
                                         unit already present when the row is added. --}}
                                    @foreach ($units as $u)
                                        <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->abbreviation }})</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="px-3 py-2">
                                <div class="flex items-stretch">
                                    <button type="button" @click="stepQty(i, -1)" title="Decrease"
                                            class="rounded-l-lg border border-r-0 border-cpsu-border px-2 text-gray-500 hover:bg-cpsu-bg hover:text-cpsu-green transition">
                                        <i data-lucide="minus" class="w-3.5 h-3.5"></i>
                                    </button>
                                    <input type="number" step="0.01" min="0.01" x-model.number="line.quantity"
                                           class="w-16 border-y border-cpsu-border px-2 py-1.5 text-sm text-center focus:border-cpsu-green outline-none"
                                           :class="lineErr(i,'quantity') ? 'border-cpsu-danger' : ''">
                                    <button type="button" @click="stepQty(i, 1)" title="Increase"
                                            class="rounded-r-lg border border-l-0 border-cpsu-border px-2 text-gray-500 hover:bg-cpsu-bg hover:text-cpsu-green transition">
                                        <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                                    </button>
                                </div>
                            </td>
                            <td class="px-3 py-2">
                                <input type="number" step="0.01" min="0" x-model.number="line.unit_cost" placeholder="0.00"
                                       class="w-full rounded-lg border border-cpsu-border px-2 py-1.5 text-sm text-right focus:border-cpsu-green outline-none">
                            </td>
                            <td class="px-3 py-2 text-right font-semibold whitespace-nowrap" x-text="money(lineAmount(line))"></td>
                            <td class="px-3 py-2 text-center">
                                <button type="button" @click="removeLine(i)" class="text-cpsu-danger hover:bg-red-50 rounded-lg p-1.5 transition">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="!form.lines.length">
                        <td colspan="6" class="px-3 py-8 text-center text-gray-400 text-sm">
                            No items yet — search above to add lines.
                        </td>
                    </tr>
                </tbody>
                <tfoot x-show="form.lines.length">
                    <tr class="text-xs font-semibold text-gray-600">
                        <td class="px-3 py-2 text-right" colspan="2">Total quantity</td>
                        <td class="px-3 py-2 text-center" x-text="totalQty()"></td>
                        <td class="px-3 py-2 text-right uppercase">Total Amount</td>
                        <td class="px-3 py-2 text-right text-sm text-cpsu-black" x-text="'₱' + money(totalAmount())"></td>
                        <td></td>
                    </tr>
                </tfoot>
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
        po_number: '', fund_cluster_id: '', supplier_id: '', received_at: @js(now()->format('Y-m-d')),
        remarks: '', lines: [],
      },
      init() {
        var self = this;
        this.picker = new TomSelect('#item-picker', {
          create: false, maxItems: 1, allowEmptyOption: false,
          onChange: function (val) {
            if (val) {
              self.addLine(val);
              self.picker.clear();
              // Keep the search open so several items can be added in a row.
              self.$nextTick(function () { self.picker.focus(); });
            }
          },
        });
      },
      addLine(itemId) {
        var it = this.items.find(function (x) { return String(x.id) === String(itemId); });
        if (!it) return;
        // If the item is already on the list, bump its quantity instead of
        // creating a duplicate row — one item, one line.
        var existing = this.form.lines.find(function (l) { return String(l.item_id) === String(it.id); });
        if (existing) {
          existing.quantity = Math.round(((existing.quantity || 0) + 1) * 100) / 100;
          this.flash(existing);
          return;
        }
        var line = {
          _k: ++this._key, _name: it.name, _stock: it.stock_number || '', _flash: false,
          item_id: it.id, unit_id: it.unit_id, quantity: 1,
          unit_cost: Number(it.unit_cost) || 0, // prefill with the item's current cost
        };
        this.form.lines.push(line);
        this.flash(line);
        this.$nextTick(function () { if (window.lucide) window.lucide.createIcons(); });
      },
      lineAmount(line) {
        return Math.round(((Number(line.quantity) || 0) * (Number(line.unit_cost) || 0)) * 100) / 100;
      },
      totalAmount() {
        var self = this;
        return Math.round(this.form.lines.reduce(function (s, l) { return s + self.lineAmount(l); }, 0) * 100) / 100;
      },
      money(n) {
        return (Number(n) || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      },
      stepQty(i, delta) {
        var q = (this.form.lines[i].quantity || 0) + delta;
        this.form.lines[i].quantity = Math.max(0.01, Math.round(q * 100) / 100);
      },
      totalQty() {
        var t = this.form.lines.reduce(function (sum, l) { return sum + (Number(l.quantity) || 0); }, 0);
        return Math.round(t * 100) / 100;
      },
      flash(line) {
        line._flash = true;
        setTimeout(function () { line._flash = false; }, 700);
      },
      clearLines() {
        if (!this.form.lines.length) return;
        if (!confirm('Remove all ' + this.form.lines.length + ' item line(s)?')) return;
        this.form.lines = [];
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
