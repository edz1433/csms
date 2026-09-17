{{-- Shared by receiving/create and receiving/edit.
     $delivery is null on create, and the delivery being amended on edit. --}}
@php
    /** @var \App\Models\Delivery|null $delivery */
    $delivery = $delivery ?? null;
    $editing = (bool) $delivery;

    $existingLines = $editing
        ? $delivery->items->map(fn ($line) => [
            'id' => $line->id,
            'item_id' => $line->item_id,
            'unit_id' => $line->unit_id,
            'ordered_qty' => $line->ordered_qty !== null ? (float) $line->ordered_qty : null,
            'quantity' => (float) $line->quantity,
            'unit_cost' => (float) $line->unit_cost,
            'received_at' => $line->received_at?->format('Y-m-d'),
            '_name' => $line->item?->name,
            '_stock' => $line->item?->stock_number ?? '',
            '_orig' => (float) $line->quantity, // quantity already credited to stock
        ])->values()
        : collect();
@endphp

<form
    x-data="receivingForm({
        items: {{ Illuminate\Support\Js::from($items) }},
        units: {{ Illuminate\Support\Js::from($units) }},
        fundClusters: {{ Illuminate\Support\Js::from($fundClusters) }},
        suppliers: {{ Illuminate\Support\Js::from($suppliers) }},
        submitUrl: @js($editing ? route('deliveries.update', $delivery) : route('deliveries.store')),
        method: @js($editing ? 'PUT' : 'POST'),
        editing: @js($editing),
        initial: {{ Illuminate\Support\Js::from([
            'po_number' => $editing ? $delivery->po_number : '',
            'fund_cluster_id' => $editing ? ($delivery->fund_cluster_id ?? '') : '',
            'supplier_id' => $editing ? ($delivery->supplier_id ?? '') : '',
            'received_at' => $editing ? $delivery->received_at?->format('Y-m-d') : now()->format('Y-m-d'),
            'remarks' => $editing ? $delivery->remarks : '',
            'lines' => $existingLines,
        ]) }},
    })"
    @submit.prevent="submit()"
    @items-created.window="absorbItems($event.detail)"
    class="space-y-3"
>
    @if ($editing && $delivery->iar)
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800 flex items-center gap-2">
            <i data-lucide="alert-triangle" class="w-3.5 h-3.5 shrink-0"></i>
            <span>Covered by IAR <b>{{ $delivery->iar->iar_number }}</b> — changes here change what that IAR reports.</span>
        </div>
    @endif

    {{-- Header. Collapsible: once the details are set, entering a long item
         list is what the screen is for, so the header folds to one summary
         line and the table starts near the top of the viewport. --}}
    <div class="bg-white rounded-xl border border-cpsu-border shadow-sm" data-aos="fade-up">
        <button type="button" @click="headerOpen = !headerOpen"
                class="w-full flex items-center gap-2 px-4 py-2.5 text-left">
            <i data-lucide="file-text" class="w-4 h-4 text-cpsu-green shrink-0"></i>
            <h3 class="font-bold text-sm shrink-0">Delivery Details</h3>
            {{-- Collapsed: everything the header holds, on one line. --}}
            <span x-show="!headerOpen" x-cloak class="text-xs text-gray-500 truncate" x-text="summary()"></span>
            <span x-show="headerErrors" x-cloak class="text-xs text-cpsu-danger shrink-0">· check the fields</span>
            <span class="ml-auto shrink-0 inline-flex transition-transform"
                  :class="headerOpen ? 'rotate-180' : ''">
                <i data-lucide="chevron-down" class="w-4 h-4 text-gray-400"></i>
            </span>
        </button>

        <div x-show="headerOpen" x-cloak class="px-4 pb-4">
            <div class="grid grid-cols-2 lg:grid-cols-5 gap-x-3 gap-y-2">
                <div class="space-y-1">
                    <label class="block text-xs font-medium text-gray-600">PO Number</label>
                    <input x-model="form.po_number" type="text" placeholder="PO-2026-0142 — optional"
                           class="w-full rounded-lg border px-2.5 py-1.5 text-sm outline-none focus:ring-2 focus:ring-cpsu-green/20"
                           :class="err('po_number') ? 'border-cpsu-danger' : 'border-cpsu-border focus:border-cpsu-green'">
                    <p x-show="err('po_number')" x-cloak x-text="err('po_number')" class="text-xs text-cpsu-danger"></p>
                </div>
                <div class="space-y-1">
                    <label class="block text-xs font-medium text-gray-600">Fund Cluster</label>
                    <select x-model="form.fund_cluster_id"
                            class="w-full rounded-lg border px-2.5 py-1.5 text-sm bg-white outline-none focus:ring-2 focus:ring-cpsu-green/20"
                            :class="err('fund_cluster_id') ? 'border-cpsu-danger' : 'border-cpsu-border focus:border-cpsu-green'">
                        <option value="">— None —</option>
                        @foreach ($fundClusters as $fc)
                            <option value="{{ $fc->id }}">{{ $fc->code }} — {{ $fc->name }}</option>
                        @endforeach
                    </select>
                    <p x-show="err('fund_cluster_id')" x-cloak x-text="err('fund_cluster_id')" class="text-xs text-cpsu-danger"></p>
                </div>
                <div class="space-y-1">
                    <label class="block text-xs font-medium text-gray-600">Supplier</label>
                    <select x-model="form.supplier_id"
                            class="w-full rounded-lg border border-cpsu-border px-2.5 py-1.5 text-sm bg-white outline-none focus:border-cpsu-green focus:ring-2 focus:ring-cpsu-green/20">
                        <option value="">— None / walk-in —</option>
                        @foreach ($suppliers as $s)
                            <option value="{{ $s->id }}">{{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="space-y-1">
                    <label class="block text-xs font-medium text-gray-600" title="First / main receipt date. Later batches carry their own date per line.">
                        Date Received <span class="text-cpsu-danger">*</span>
                    </label>
                    <input x-model="form.received_at" type="date" max="{{ now()->format('Y-m-d') }}"
                           class="w-full rounded-lg border px-2.5 py-1.5 text-sm outline-none focus:ring-2 focus:ring-cpsu-green/20"
                           :class="err('received_at') ? 'border-cpsu-danger' : 'border-cpsu-border focus:border-cpsu-green'">
                    <p x-show="err('received_at')" x-cloak x-text="err('received_at')" class="text-xs text-cpsu-danger"></p>
                </div>
                <div class="space-y-1 col-span-2 lg:col-span-1">
                    <label class="block text-xs font-medium text-gray-600">Remarks</label>
                    <input x-model="form.remarks" type="text" placeholder="Optional"
                           class="w-full rounded-lg border border-cpsu-border px-2.5 py-1.5 text-sm outline-none focus:border-cpsu-green focus:ring-2 focus:ring-cpsu-green/20">
                </div>
            </div>
        </div>
    </div>

    @if (! $editing)
        {{-- Step 1 of 2. Saving the header first means a long item list is
             entered against a delivery that already exists, so it can never be
             lost to a validation error up here. --}}
        <p class="text-xs text-gray-500 flex items-center gap-1.5">
            <i data-lucide="list-ordered" class="w-3.5 h-3.5 shrink-0 text-cpsu-green"></i>
            <span><b>Step 1 of 2</b> — save these details; the next screen is where the items go.</span>
        </p>
    @endif

    {{-- Line items — entered against a delivery that already exists. --}}
    @if ($editing)
    <div class="bg-white rounded-xl border border-cpsu-border shadow-sm p-4" data-aos="fade-up">
        {{-- Title and the add-item search share one row: the table then starts
             roughly a screen higher than it used to. --}}
        <div class="flex flex-wrap items-center gap-2 mb-3">
            <h3 class="font-bold text-sm flex items-center gap-2 shrink-0">
                <i data-lucide="package-plus" class="w-4 h-4 text-cpsu-green"></i> Items Received
            </h3>
            <div class="flex-1 min-w-[240px]">
                <select id="item-picker" placeholder="Search item by name or stock number to add…" autocomplete="off">
                    <option value="">Search item…</option>
                    @foreach ($items as $it)
                        <option value="{{ $it->id }}">{{ $it->name }} {{ $it->stock_number ? '('.$it->stock_number.')' : '' }}</option>
                    @endforeach
                </select>
            </div>
            @if (auth()->user()->canWrite())
                {{-- An item that is not on the master list yet is created or
                     imported right here and lands on the delivery at once. --}}
                <x-action-guard>
                    <button type="button"
                            @click="$dispatch('open-modal', 'receiving-new-item')"
                            class="shrink-0 inline-flex items-center gap-1 rounded-lg border border-cpsu-border px-2.5 py-1.5 text-xs font-semibold text-cpsu-black hover:bg-cpsu-bg transition"
                            title="Create an item that is not on the list yet">
                        <i data-lucide="plus" class="w-3.5 h-3.5 text-cpsu-green"></i> New item
                    </button>
                    <button type="button"
                            @click="$dispatch('open-modal', 'receiving-import-items')"
                            class="shrink-0 inline-flex items-center gap-1 rounded-lg border border-cpsu-border px-2.5 py-1.5 text-xs font-semibold text-cpsu-black hover:bg-cpsu-bg transition"
                            title="Add many items at once from a CSV list">
                        <i data-lucide="upload" class="w-3.5 h-3.5 text-cpsu-green"></i> Import
                    </button>
                </x-action-guard>
            @endif
            <span class="text-xs text-gray-400 shrink-0" x-text="form.lines.length + ' line' + (form.lines.length === 1 ? '' : 's')"></span>
            <button type="button" x-show="form.lines.length" @click="clearLines()"
                    class="text-xs text-cpsu-danger hover:underline inline-flex items-center gap-1 shrink-0">
                <i data-lucide="eraser" class="w-3.5 h-3.5"></i> Clear all
            </button>
        </div>
        <p x-show="err('lines')" x-cloak x-text="err('lines')" class="text-xs text-cpsu-danger mb-2"></p>

        {{-- Rows --}}
        <div class="overflow-x-auto">
            <table class="w-full text-sm min-w-[860px]">
                <thead x-show="form.lines.length">
                    <tr class="text-left text-xs uppercase text-gray-500 bg-cpsu-bg">
                        <th class="px-2 py-1.5 rounded-l-lg">Item</th>
                        <th class="px-2 py-1.5 w-32">Unit</th>
                        <th class="px-2 py-1.5 w-28">Ordered</th>
                        <th class="px-2 py-1.5 w-44">Received</th>
                        <th class="px-2 py-1.5 w-24 text-right">Balance</th>
                        <th class="px-2 py-1.5 w-28">Unit Cost (₱)</th>
                        <th class="px-2 py-1.5 w-40">Date Received</th>
                        <th class="px-2 py-1.5 w-28 text-right">Amount</th>
                        <th class="px-2 py-1.5 w-12 rounded-r-lg"></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(line, i) in form.lines" :key="line._k">
                        <tr class="border-b border-cpsu-border transition-colors duration-700 align-top"
                            :class="line._flash ? 'bg-cpsu-green/10' : ''">
                            <td class="px-2 py-1.5">
                                <p class="font-medium" x-text="line._name"></p>
                                <p class="text-xs text-gray-400 font-mono" x-text="line._stock"></p>
                            </td>
                            <td class="px-2 py-1.5">
                                <select x-model.number="line.unit_id"
                                        class="w-full rounded-lg border border-cpsu-border px-2 py-1.5 text-sm bg-white focus:border-cpsu-green outline-none">
                                    {{-- Server-rendered so x-model finds the item's own
                                         unit already present when the row is added. --}}
                                    @foreach ($units as $u)
                                        <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->abbreviation }})</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="px-2 py-1.5">
                                {{-- PO quantity: what the supplier still owes is measured against this. --}}
                                <input type="number" step="0.01" min="0" x-model.number="line.ordered_qty" placeholder="—"
                                       title="Quantity on the purchase order (optional)"
                                       class="w-full rounded-lg border border-cpsu-border px-2 py-1.5 text-sm text-center focus:border-cpsu-green outline-none">
                            </td>
                            <td class="px-2 py-1.5">
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
                                {{-- Total received to date on this line; the hint spells out
                                     what this save will do to on-hand stock. --}}
                                <p x-show="line._orig !== undefined && delta(line) !== 0" x-cloak
                                   class="text-xs mt-1"
                                   :class="delta(line) > 0 ? 'text-cpsu-green' : 'text-cpsu-danger'"
                                   x-text="(delta(line) > 0 ? '+' : '') + money(delta(line)) + ' to stock (was ' + money(line._orig) + ')'"></p>
                            </td>
                            <td class="px-2 py-1.5 text-right whitespace-nowrap">
                                <span x-show="line.ordered_qty === null || line.ordered_qty === ''" class="text-gray-300">—</span>
                                <span x-show="line.ordered_qty !== null && line.ordered_qty !== ''" x-cloak
                                      class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold"
                                      :class="balance(line) > 0 ? 'bg-amber-100 text-amber-700' : 'bg-cpsu-green/10 text-cpsu-green'"
                                      x-text="balance(line) > 0 ? money(balance(line)) + ' due' : 'Complete'"></span>
                            </td>
                            <td class="px-2 py-1.5">
                                <input type="number" step="0.01" min="0" x-model.number="line.unit_cost" placeholder="0.00"
                                       class="w-full rounded-lg border border-cpsu-border px-2 py-1.5 text-sm text-right focus:border-cpsu-green outline-none">
                            </td>
                            <td class="px-2 py-1.5">
                                {{-- Per-line date: a batch that arrived a week later keeps its own. --}}
                                <input type="date" max="{{ now()->format('Y-m-d') }}" x-model="line.received_at"
                                       class="w-full rounded-lg border border-cpsu-border px-2 py-1.5 text-sm focus:border-cpsu-green outline-none">
                            </td>
                            <td class="px-2 py-1.5 text-right font-semibold whitespace-nowrap" x-text="money(lineAmount(line))"></td>
                            <td class="px-2 py-1.5 text-center">
                                <button type="button" @click="removeLine(i)" class="text-cpsu-danger hover:bg-red-50 rounded-lg p-1.5 transition">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="!form.lines.length">
                        <td colspan="9" class="px-2 py-6 text-center text-gray-400 text-sm">
                            No items yet — search above to add lines.
                        </td>
                    </tr>
                </tbody>
                <tfoot x-show="form.lines.length">
                    <tr class="text-xs font-semibold text-gray-600">
                        <td class="px-2 py-1.5 text-right" colspan="3">Total received</td>
                        <td class="px-2 py-1.5 text-center" x-text="totalQty()"></td>
                        <td class="px-2 py-1.5 text-right" x-text="totalBalance() > 0 ? money(totalBalance()) + ' due' : ''"></td>
                        <td class="px-2 py-1.5 text-right uppercase" colspan="2">Total Amount</td>
                        <td class="px-2 py-1.5 text-right text-sm text-cpsu-black" x-text="'₱' + money(totalAmount())"></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    @endif

    {{-- Actions. Sticky so a long item list never has to be scrolled past
         to reach Save, and the running total stays in view while typing. --}}
    <div class="sticky bottom-0 z-20 flex items-center gap-2 rounded-xl border border-cpsu-border bg-white/95 backdrop-blur px-3 py-2 shadow-sm">
        @if ($editing)
            <span x-show="form.lines.length" x-cloak class="text-xs text-gray-500 hidden sm:inline">
                <span x-text="form.lines.length"></span> line(s) ·
                <b class="text-cpsu-black" x-text="'₱' + money(totalAmount())"></b>
            </span>
        @endif
        <div class="flex items-center gap-2 ml-auto">
            <x-ui.button variant="ghost" :href="$editing ? route('deliveries.show', $delivery) : route('deliveries.index')">Cancel</x-ui.button>
            <x-ui.button variant="primary" icon="save" type="submit" x-bind:disabled="submitting">
                <span x-show="!submitting">{{ $editing ? 'Update Delivery' : 'Save & Add Items' }}</span>
                <span x-show="submitting" x-cloak>Saving…</span>
            </x-ui.button>
        </div>
    </div>
</form>

@if ($editing && auth()->user()->canWrite())
    <x-action-guard>
        @include('receiving.partials.item-tools')
    </x-action-guard>
@endif

@push('scripts')
<script>
  function receivingForm(cfg) {
    return {
      items: cfg.items, units: cfg.units,
      fundClusters: cfg.fundClusters, suppliers: cfg.suppliers,
      submitting: false, errors: {}, _key: 0,
      // Folded away once the details are filled in, so the item table sits at
      // the top of the screen where the work actually happens.
      headerOpen: true,
      form: {
        po_number: '', fund_cluster_id: '', supplier_id: '', received_at: '',
        remarks: '', lines: [],
      },
      init() {
        var self = this;

        // Seed from the server payload (blank on create, the saved delivery on edit).
        this.form.po_number = cfg.initial.po_number || '';
        this.form.fund_cluster_id = cfg.initial.fund_cluster_id || '';
        this.form.supplier_id = cfg.initial.supplier_id || '';
        this.form.received_at = cfg.initial.received_at || '';
        this.form.remarks = cfg.initial.remarks || '';

        // Open when there is still something to fill in up here, otherwise
        // start folded and give the screen to the items.
        this.headerOpen = !cfg.editing || !this.form.po_number;
        this.form.lines = (cfg.initial.lines || []).map(function (l) {
          l._k = ++self._key;
          l._flash = false;
          if (l.ordered_qty === null) l.ordered_qty = '';
          return l;
        });

        // The item picker is only rendered once the delivery exists (step 2).
        if (document.getElementById('item-picker')) {
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
        }

        this.$nextTick(function () { if (window.lucide) window.lucide.createIcons(); });
      },
      /**
       * @param {boolean} bump  Re-picking an item from the search adds one to
       *   the row it already has; an item arriving from the New item / Import
       *   tools must not, or a name that was already listed silently gains a
       *   quantity nobody asked for.
       */
      addLine(itemId, bump) {
        if (bump === undefined) bump = true;

        var it = this.items.find(function (x) { return String(x.id) === String(itemId); });
        if (!it) return;
        // If the item is already on the list, bump its quantity instead of
        // creating a duplicate row — one item, one line.
        var existing = this.form.lines.find(function (l) { return String(l.item_id) === String(it.id); });
        if (existing) {
          if (bump) existing.quantity = Math.round(((existing.quantity || 0) + 1) * 100) / 100;
          this.flash(existing);
          return;
        }
        var line = {
          _k: ++this._key, _name: it.name, _stock: it.stock_number || '', _flash: false,
          id: null, item_id: it.id, unit_id: it.unit_id, ordered_qty: '', quantity: 1,
          unit_cost: Number(it.unit_cost) || 0, // prefill with the item's current cost
          received_at: this.form.received_at,
        };
        this.form.lines.push(line);
        this.flash(line);
        this.$nextTick(function () { if (window.lucide) window.lucide.createIcons(); });
      },
      /**
       * Items just created or imported from this screen. They are registered
       * with the search picker first — otherwise they could not be found again
       * after being removed from the delivery — and then added as lines.
       */
      absorbItems(detail) {
        var self = this;
        var list = (detail && detail.items) || [];
        if (!list.length) return;

        list.forEach(function (it) {
          if (!self.items.some(function (x) { return String(x.id) === String(it.id); })) {
            self.items.push(it);

            if (self.picker) {
              self.picker.addOption({
                value: String(it.id),
                text: it.name + (it.stock_number ? ' (' + it.stock_number + ')' : ''),
              });
            }
          }

          if (detail && detail.addToDelivery === false) return;

          self.addLine(it.id, false);
        });

        if (self.picker) self.picker.refreshOptions(false);

        this.$nextTick(function () { if (window.lucide) window.lucide.createIcons(); });
      },
      lineAmount(line) {
        return Math.round(((Number(line.quantity) || 0) * (Number(line.unit_cost) || 0)) * 100) / 100;
      },
      // Quantity still owed by the supplier on this line.
      balance(line) {
        if (line.ordered_qty === null || line.ordered_qty === '') return 0;
        return Math.max(0, Math.round(((Number(line.ordered_qty) || 0) - (Number(line.quantity) || 0)) * 100) / 100);
      },
      totalBalance() {
        var self = this;
        return Math.round(this.form.lines.reduce(function (s, l) { return s + self.balance(l); }, 0) * 100) / 100;
      },
      // Stock movement this save will apply to the line (edit mode only).
      delta(line) {
        if (line._orig === undefined) return 0;
        return Math.round(((Number(line.quantity) || 0) - (Number(line._orig) || 0)) * 100) / 100;
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
      removeLine(i) {
        var line = this.form.lines[i];
        if (line._orig !== undefined && !confirm('Remove this line? ' + this.money(line._orig) + ' will be taken back out of stock.')) return;
        this.form.lines.splice(i, 1);
      },
      /** One-line stand-in for the header while it is folded away. */
      summary() {
        var self = this;
        var fc = this.fundClusters.find(function (x) { return String(x.id) === String(self.form.fund_cluster_id); });
        var sup = this.suppliers.find(function (x) { return String(x.id) === String(self.form.supplier_id); });

        return [
          this.form.po_number || 'PO pending',
          fc ? fc.code : null,
          sup ? sup.name : null,
          this.form.received_at,
        ].filter(Boolean).join(' · ');
      },
      // A folded header must still announce that the error is inside it.
      get headerErrors() {
        var self = this;
        return ['po_number', 'fund_cluster_id', 'supplier_id', 'received_at', 'remarks']
          .some(function (f) { return !!self.errors[f]; });
      },
      err(field) { return this.errors[field] ? this.errors[field][0] : ''; },
      lineErr(i, field) { return this.errors['lines.' + i + '.' + field] ? this.errors['lines.' + i + '.' + field][0] : ''; },
      payload() {
        return {
          po_number: this.form.po_number,
          fund_cluster_id: this.form.fund_cluster_id || null,
          supplier_id: this.form.supplier_id || null,
          received_at: this.form.received_at,
          remarks: this.form.remarks || null,
          lines: this.form.lines.map(function (l) {
            return {
              id: l.id || null,
              item_id: l.item_id,
              unit_id: l.unit_id,
              ordered_qty: (l.ordered_qty === '' || l.ordered_qty === null) ? null : Number(l.ordered_qty),
              quantity: Number(l.quantity),
              unit_cost: Number(l.unit_cost) || 0,
              received_at: l.received_at || null,
            };
          }),
        };
      },
      async submit() {
        // Creating only opens the header — the items are added on the next
        // screen. Clearing every line off a saved delivery is allowed, but it
        // hands stock back, so make it a deliberate answer.
        if (cfg.editing && !this.form.lines.length &&
            !confirm('Save this delivery with no items? Any quantity previously recorded is taken back out of stock.')) {
          return;
        }
        this.submitting = true; this.errors = {};
        try {
          var res = await fetch(cfg.submitUrl, {
            method: cfg.method,
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(this.payload()),
          });
          if (res.status === 422) {
            var j = await res.json(); this.errors = j.errors || {};
            if (this.headerErrors) this.headerOpen = true;
            CPSU.toast(j.message || 'Please fix the errors.', 'error'); this.submitting = false; return;
          }
          if (!res.ok) { CPSU.toast('Something went wrong.', 'error'); this.submitting = false; return; }
          var d = await res.json();
          CPSU.toast(d.message || (cfg.editing ? 'Delivery updated. Stock adjusted.' : 'Delivery saved.'), 'success');
          setTimeout(function () { window.location = d.redirect; }, 600);
        } catch (e) { CPSU.toast('Network error.', 'error'); this.submitting = false; }
      },
    };
  }
</script>
@endpush
