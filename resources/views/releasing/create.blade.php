@extends('layouts.app')

@section('title', 'New Release')
@section('header', 'New Release (RIS)')
@section('subheader', 'Pick a PO to pull its items in, or search stock directly')

@section('content')
<div class="mb-3">
    <a href="{{ route('releases.index') }}" class="inline-flex items-center gap-1 text-xs text-gray-500 hover:text-cpsu-green">
        <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Back to Releases
    </a>
</div>

<form
    x-data="releasingForm({
        items: {{ Illuminate\Support\Js::from($items) }},
        units: {{ Illuminate\Support\Js::from($units) }},
        accountTitles: {{ Illuminate\Support\Js::from($accountTitles) }},
        fundClusters: {{ Illuminate\Support\Js::from($fundClusters) }},
        locations: {{ Illuminate\Support\Js::from($locations) }},
        storeUrl: @js(route('releases.store')),
        releasableUrl: @js(route('deliveries.releasable', ['delivery' => '__ID__'])),
        today: @js(now()->format('Y-m-d')),
    })"
    @submit.prevent="submit()"
    class="space-y-3"
>
    {{-- Header. Folds to one summary line once it is filled in, so the item
         list gets the screen. --}}
    <div class="bg-white rounded-xl border border-cpsu-border shadow-sm" data-aos="fade-up">
        <button type="button" @click="headerOpen = !headerOpen"
                class="w-full flex items-center gap-2 px-4 py-2.5 text-left">
            <i data-lucide="file-text" class="w-4 h-4 text-cpsu-green shrink-0"></i>
            <h3 class="font-bold text-sm shrink-0">Release Details</h3>
            <span x-show="!headerOpen" x-cloak class="text-xs text-gray-500 truncate" x-text="summary()"></span>
            <span x-show="headerErrors" x-cloak class="text-xs text-cpsu-danger shrink-0">· check the fields</span>
            <span class="ml-auto shrink-0 inline-flex transition-transform" :class="headerOpen ? 'rotate-180' : ''">
                <i data-lucide="chevron-down" class="w-4 h-4 text-gray-400"></i>
            </span>
        </button>

        <div x-show="headerOpen" x-cloak class="px-4 pb-4 grid grid-cols-2 lg:grid-cols-4 gap-x-3 gap-y-2">
            <div class="space-y-1 col-span-2 lg:col-span-1">
                <label class="block text-xs font-medium text-gray-600">PO Number</label>
                {{-- Picking a PO loads that delivery's items below. One PO is
                     routinely issued to several offices, one RIS each, so the
                     same PO can be selected again for the next office. --}}
                <select id="po-picker" placeholder="Search PO number or supplier…" autocomplete="off">
                    <option value="">— No PO / issue from stock —</option>
                    @foreach ($deliveries as $d)
                        <option value="{{ $d->id }}">
                            {{ $d->poLabel() }}{{ $d->supplier?->name ? ' — '.$d->supplier->name : '' }}
                            ({{ $d->received_at?->format('M d, Y') }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="space-y-1">
                <label class="block text-xs font-medium text-gray-600">Fund Cluster <span class="text-cpsu-danger">*</span></label>
                <select x-model="form.fund_cluster_id"
                        class="w-full rounded-lg border px-2.5 py-1.5 text-sm bg-white outline-none focus:ring-2 focus:ring-cpsu-green/20"
                        :class="err('fund_cluster_id') ? 'border-cpsu-danger' : 'border-cpsu-border focus:border-cpsu-green'">
                    <option value="">Select fund cluster</option>
                    @foreach ($fundClusters as $fc)
                        <option value="{{ $fc->id }}">{{ $fc->code }} — {{ $fc->name }}</option>
                    @endforeach
                </select>
                <p x-show="err('fund_cluster_id')" x-cloak x-text="err('fund_cluster_id')" class="text-xs text-cpsu-danger"></p>
            </div>
            <div class="space-y-1">
                <label class="block text-xs font-medium text-gray-600">Receiving Campus / Office <span class="text-cpsu-danger">*</span></label>
                <select x-model="form.location_id"
                        class="w-full rounded-lg border px-2.5 py-1.5 text-sm bg-white outline-none focus:ring-2 focus:ring-cpsu-green/20"
                        :class="err('location_id') ? 'border-cpsu-danger' : 'border-cpsu-border focus:border-cpsu-green'">
                    <option value="">Select destination</option>
                    @foreach ($locations as $loc)
                        <option value="{{ $loc->id }}">{{ $loc->name }} [{{ ucfirst($loc->type) }}]</option>
                    @endforeach
                </select>
                <p x-show="err('location_id')" x-cloak x-text="err('location_id')" class="text-xs text-cpsu-danger"></p>
            </div>
            <div class="space-y-1">
                <label class="block text-xs font-medium text-gray-600">Date Released <span class="text-cpsu-danger">*</span></label>
                <input x-model="form.released_at" type="date" max="{{ now()->format('Y-m-d') }}"
                       class="w-full rounded-lg border px-2.5 py-1.5 text-sm outline-none focus:ring-2 focus:ring-cpsu-green/20"
                       :class="err('released_at') ? 'border-cpsu-danger' : 'border-cpsu-border focus:border-cpsu-green'">
                <p x-show="err('released_at')" x-cloak x-text="err('released_at')" class="text-xs text-cpsu-danger"></p>
            </div>
        </div>
    </div>

    {{-- PO status. Outside the fold, so it stays readable with the header
         collapsed, and one line high. --}}
    <p x-show="poLoading" x-cloak class="text-xs text-gray-500 flex items-center gap-1.5">
        <i data-lucide="loader-2" class="w-3.5 h-3.5 animate-spin"></i> Loading the items on this PO…
    </p>

    <div x-show="poInfo && !poLoading" x-cloak
         class="rounded-lg border border-cpsu-green/30 bg-cpsu-green/5 px-3 py-1.5 text-xs flex flex-wrap items-center gap-x-3 gap-y-1">
        <span class="inline-flex items-center gap-1 font-semibold text-cpsu-green">
            <i data-lucide="package-check" class="w-3.5 h-3.5"></i>
            <span x-text="poInfo ? poInfo.po_label : ''"></span>
        </span>
        <span class="text-gray-500" x-show="poInfo && poInfo.supplier" x-text="poInfo ? poInfo.supplier : ''"></span>
        <span class="text-gray-400" x-show="poInfo && poInfo.received_at"
              x-text="poInfo ? 'Received ' + poInfo.received_at : ''"></span>
        <span class="text-gray-500 ml-auto hidden md:inline">One PO, one RIS per office — save this, then start the next.</span>
    </div>

    <div x-show="poSkipped.length" x-cloak
         class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs text-amber-800 flex items-center gap-1.5">
        <i data-lucide="alert-triangle" class="w-3.5 h-3.5 shrink-0"></i>
        <span>Left out — nothing on hand: <b x-text="poSkipped.join(', ')"></b></span>
    </div>

    {{-- Line items --}}
    <div class="bg-white rounded-xl border border-cpsu-border shadow-sm p-4" data-aos="fade-up">
        {{-- Title, search and the row actions share one line. --}}
        <div class="flex flex-wrap items-center gap-2 mb-3">
            <h3 class="font-bold text-sm flex items-center gap-2 shrink-0">
                <i data-lucide="package-minus" class="w-4 h-4 text-cpsu-green"></i> Items to Release
            </h3>
            <div class="flex-1 min-w-[240px]">
                <select id="item-picker" placeholder="Search item by name or stock number to add…" autocomplete="off">
                    <option value="">Search item…</option>
                    @foreach ($items as $it)
                        <option value="{{ $it->id }}">{{ $it->name }} {{ $it->stock_number ? '('.$it->stock_number.')' : '' }}</option>
                    @endforeach
                </select>
            </div>
            <span class="text-xs text-gray-400 shrink-0" x-text="form.lines.length + ' line' + (form.lines.length === 1 ? '' : 's')"></span>
            <button type="button" x-show="form.delivery_id" x-cloak @click="loadPo(true)"
                    class="text-xs text-cpsu-green hover:underline inline-flex items-center gap-1 shrink-0">
                <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i> Reload from PO
            </button>
            <button type="button" x-show="form.lines.length" @click="clearLines()"
                    class="text-xs text-cpsu-danger hover:underline inline-flex items-center gap-1 shrink-0">
                <i data-lucide="eraser" class="w-3.5 h-3.5"></i> Clear all
            </button>
        </div>
        <p x-show="err('lines')" x-cloak x-text="err('lines')" class="text-xs text-cpsu-danger mb-2"></p>

        <div class="grid grid-cols-1 lg:grid-cols-2 2xl:grid-cols-3 gap-2">
            <template x-for="(line, i) in form.lines" :key="line._k">
                <div class="rounded-lg border p-2.5 transition-colors duration-700"
                     :class="overQty(line) ? 'border-cpsu-danger bg-red-50/40' : (line._flash ? 'border-cpsu-green bg-cpsu-green/10' : 'border-cpsu-border')">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-medium text-sm" x-text="line._name"></p>
                            <p class="text-xs text-gray-400 font-mono" x-text="line._stock"></p>
                        </div>
                        <div class="text-right shrink-0">
                            <span class="text-xs text-gray-400">On hand</span>
                            <p class="text-sm font-bold" :class="overQty(line) ? 'text-cpsu-danger' : 'text-cpsu-green'"
                               x-text="money(line._onhand)"></p>
                        </div>
                        <button type="button" @click="removeLine(i)" class="text-cpsu-danger hover:bg-red-50 rounded-lg p-1.5 transition shrink-0">
                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                        </button>
                    </div>

                    {{-- What this PO delivered, and where it has gone so far.
                         Purely informational: the same item may be issued to
                         more than one office on the same PO. --}}
                    <div x-show="line._po" x-cloak class="mt-1.5 flex flex-wrap items-center gap-1 text-xs">
                        <span class="inline-flex px-2 py-0.5 rounded-full bg-cpsu-bg text-gray-600"
                              x-text="'PO delivered ' + money(line._po ? line._po.delivered : 0)"></span>
                        <span x-show="line._po && line._po.released_on_po > 0"
                              class="inline-flex px-2 py-0.5 rounded-full bg-amber-100 text-amber-700"
                              x-text="'already issued ' + money(line._po ? line._po.released_on_po : 0)"></span>
                        <template x-for="off in (line._po ? line._po.offices : [])" :key="off.location_id">
                            <span class="inline-flex px-2 py-0.5 rounded-full"
                                  :class="String(off.location_id) === String(form.location_id)
                                          ? 'bg-cpsu-green/15 text-cpsu-green font-semibold'
                                          : 'bg-gray-100 text-gray-500'"
                                  x-text="off.location + ' · ' + money(off.quantity)"></span>
                        </template>
                    </div>

                    <div class="grid grid-cols-3 gap-2 mt-2">
                        <div>
                            <label class="text-xs text-gray-500">Account Title (RCA)</label>
                            <select x-model.number="line.account_title_id"
                                    class="w-full rounded-lg border border-cpsu-border px-2 py-1.5 text-sm bg-white focus:border-cpsu-green outline-none">
                                {{-- Rendered server-side: the options must exist before
                                     x-model binds, or the select falls back to the first
                                     one instead of the item's own account title. --}}
                                @foreach ($accountTitles as $at)
                                    <option value="{{ $at->id }}">{{ $at->name }} — {{ $at->rca_code }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="text-xs text-gray-500">Unit</label>
                            <select x-model.number="line.unit_id"
                                    class="w-full rounded-lg border border-cpsu-border px-2 py-1.5 text-sm bg-white focus:border-cpsu-green outline-none">
                                @foreach ($units as $u)
                                    <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->abbreviation }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="text-xs text-gray-500">Quantity</label>
                            <div class="flex items-stretch">
                                <button type="button" @click="stepQty(i, -1)" title="Decrease"
                                        class="rounded-l-lg border border-r-0 px-2 text-gray-500 hover:bg-cpsu-bg hover:text-cpsu-green transition"
                                        :class="overQty(line) ? 'border-cpsu-danger' : 'border-cpsu-border'">
                                    <i data-lucide="minus" class="w-3.5 h-3.5"></i>
                                </button>
                                <input type="number" step="0.01" min="0.01" x-model.number="line.quantity"
                                       class="w-full border-y px-2 py-1.5 text-sm text-center outline-none"
                                       :class="overQty(line) ? 'border-cpsu-danger focus:border-cpsu-danger' : 'border-cpsu-border focus:border-cpsu-green'">
                                <button type="button" @click="stepQty(i, 1)" title="Increase"
                                        class="rounded-r-lg border border-l-0 px-2 text-gray-500 hover:bg-cpsu-bg hover:text-cpsu-green transition"
                                        :class="overQty(line) ? 'border-cpsu-danger' : 'border-cpsu-border'">
                                    <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                                </button>
                            </div>
                            <p x-show="overQty(line)" x-cloak class="text-xs text-cpsu-danger mt-0.5">Exceeds on-hand stock.</p>
                        </div>
                    </div>
                </div>
            </template>
            <div x-show="!form.lines.length" class="py-6 text-center text-gray-400 text-sm lg:col-span-2 2xl:col-span-3">
                No items yet — pick a PO above, or search to add lines.
            </div>
        </div>
    </div>

    {{-- Actions. Sticky so a long list never has to be scrolled past to save,
         and the total and any stock error stay in view while typing. --}}
    <div class="sticky bottom-0 z-20 flex items-center gap-2 rounded-xl border border-cpsu-border bg-white/95 backdrop-blur px-3 py-2 shadow-sm">
        <p x-show="hasStockError" x-cloak class="text-xs text-cpsu-danger flex items-center gap-1">
            <i data-lucide="alert-triangle" class="w-3.5 h-3.5"></i> Some lines exceed available stock.
        </p>
        <span x-show="form.lines.length && !hasStockError" x-cloak class="text-xs text-gray-500 hidden sm:inline">
            <span x-text="form.lines.length"></span> line(s) · total qty
            <b class="text-cpsu-green" x-text="totalQty()"></b>
        </span>
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
      locations: cfg.locations, fundClusters: cfg.fundClusters,
      submitting: false, errors: {}, _key: 0,
      poLoading: false, poInfo: null, poSkipped: [],
      headerOpen: true,
      form: {
        fund_cluster_id: '', delivery_id: '', location_id: '', released_at: cfg.today,
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

        // Selecting a PO fills the lines below with that delivery's items.
        this.poPicker = new TomSelect('#po-picker', {
          create: false, maxItems: 1, allowEmptyOption: true,
          onChange: function (val) {
            self.form.delivery_id = val || '';
            self.loadPo();
          },
        });
      },

      /* ---------------- PO-driven fill ---------------- */

      /**
       * Pull the chosen PO's items in. Replacing a non-empty list is a
       * deliberate act, so it is confirmed first (and skipped when the user
       * says no, with the picker put back to what it was).
       */
      async loadPo(force) {
        var id = this.form.delivery_id;

        if (!id) { this.poInfo = null; this.poSkipped = []; return; }

        var manual = this.form.lines.some(function (l) { return !l._po; });
        if ((force || manual) && this.form.lines.length &&
            !confirm('Replace the ' + this.form.lines.length + ' line(s) below with the items on this PO?')) {
          return;
        }

        this.poLoading = true;
        try {
          var res = await fetch(cfg.releasableUrl.replace('__ID__', id), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          });
          if (!res.ok) throw new Error('lookup failed');
          var data = await res.json();

          this.poInfo = data.delivery;
          // Convenience only — never overwrite a cluster the user chose.
          if (!this.form.fund_cluster_id && data.delivery.fund_cluster_id) {
            this.form.fund_cluster_id = String(data.delivery.fund_cluster_id);
          }
          this.fillFromPo(data.lines || []);
          // Details are settled; give the screen to the item list.
          if (this.form.location_id) this.headerOpen = false;
        } catch (e) {
          CPSU.toast('Could not load the items on that PO.', 'error');
        }
        this.poLoading = false;
      },

      /**
       * Build one line per item on the PO. Items already issued to another
       * office on this same PO stay on the list — one PO is routinely split
       * between offices — so the only thing that drops a line is having no
       * stock left to give.
       */
      fillFromPo(poLines) {
        var self = this;
        var kept = [], skipped = [];

        poLines.forEach(function (l) {
          var onHand = Number(l.on_hand) || 0;
          if (onHand <= 0) { skipped.push(l.name); return; }

          // Prefer what this PO still owes; if it is all spoken for already,
          // start at 1 so issuing to a second office is a single edit.
          var qty = Number(l.suggested) > 0 ? Number(l.suggested) : Math.min(1, onHand);

          kept.push({
            _k: ++self._key, _name: l.name, _stock: l.stock_number || '',
            _onhand: onHand, _po: l, _flash: true,
            item_id: l.item_id,
            account_title_id: l.account_title_id || (self.accountTitles[0] ? self.accountTitles[0].id : ''),
            unit_id: l.unit_id,
            quantity: qty,
          });
        });

        this.form.lines = kept;
        this.poSkipped = skipped;

        if (!kept.length) {
          CPSU.toast(skipped.length ? 'Nothing on this PO has stock left to release.' : 'This PO has no items yet.', 'error');
        } else {
          CPSU.toast(kept.length + ' item' + (kept.length === 1 ? '' : 's') + ' loaded from the PO.', 'success');
        }

        setTimeout(function () {
          self.form.lines.forEach(function (l) { l._flash = false; });
        }, 700);

        this.$nextTick(function () { if (window.lucide) window.lucide.createIcons(); });
      },

      /* ---------------- lines ---------------- */

      addLine(itemId) {
        var it = this.items.find(function (x) { return String(x.id) === String(itemId); });
        if (!it) return;
        // If the item is already on the list, bump its quantity instead of
        // creating a duplicate row — one item, one line on a single RIS.
        var existing = this.form.lines.find(function (l) { return String(l.item_id) === String(it.id); });
        if (existing) {
          existing.quantity = Math.round(((existing.quantity || 0) + 1) * 100) / 100;
          this.flash(existing);
          return;
        }
        var line = {
          _k: ++this._key, _name: it.name, _stock: it.stock_number || '', _onhand: Number(it.on_hand_qty),
          _po: null, _flash: false,
          item_id: it.id,
          account_title_id: it.account_title_id || (this.accountTitles[0] ? this.accountTitles[0].id : ''),
          unit_id: it.unit_id, quantity: 1,
        };
        this.form.lines.push(line);
        this.flash(line);
        this.$nextTick(function () { if (window.lucide) window.lucide.createIcons(); });
      },
      stepQty(i, delta) {
        var q = (this.form.lines[i].quantity || 0) + delta;
        this.form.lines[i].quantity = Math.max(0.01, Math.round(q * 100) / 100);
      },
      totalQty() {
        var t = this.form.lines.reduce(function (sum, l) { return sum + (Number(l.quantity) || 0); }, 0);
        return Math.round(t * 100) / 100;
      },
      money(n) {
        return (Number(n) || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
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
      overQty(line) { return Number(line.quantity) > Number(line._onhand); },
      get hasStockError() { return this.form.lines.some((l) => this.overQty(l)); },
      /** One-line stand-in for the header while it is folded away. */
      summary() {
        var self = this;
        var fc = this.fundClusters.find(function (x) { return String(x.id) === String(self.form.fund_cluster_id); });
        var loc = this.locations.find(function (x) { return String(x.id) === String(self.form.location_id); });

        return [
          this.poInfo ? this.poInfo.po_label : null,
          fc ? fc.code : null,
          loc ? loc.name : 'no office yet',
          this.form.released_at,
        ].filter(Boolean).join(' · ');
      },
      // A folded header must still announce that the error is inside it.
      get headerErrors() {
        var self = this;
        return ['fund_cluster_id', 'delivery_id', 'location_id', 'released_at']
          .some(function (f) { return !!self.errors[f]; });
      },
      err(field) { return this.errors[field] ? this.errors[field][0] : ''; },
      payload() {
        return {
          fund_cluster_id: this.form.fund_cluster_id,
          delivery_id: this.form.delivery_id || null,
          location_id: this.form.location_id,
          released_at: this.form.released_at,
          remarks: this.form.remarks || null,
          lines: this.form.lines.map(function (l) {
            return {
              item_id: l.item_id,
              account_title_id: l.account_title_id,
              unit_id: l.unit_id,
              quantity: Number(l.quantity),
            };
          }),
        };
      },
      async submit() {
        if (!this.form.lines.length) { CPSU.toast('Add at least one item line.', 'error'); return; }
        if (this.hasStockError) { CPSU.toast('Fix lines that exceed on-hand stock.', 'error'); return; }
        this.submitting = true; this.errors = {};
        try {
          var res = await fetch(cfg.storeUrl, {
            method: 'POST',
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
          CPSU.toast('Release saved. Stock updated.', 'success');
          setTimeout(function () { window.location = d.redirect; }, 600);
        } catch (e) { CPSU.toast('Network error.', 'error'); this.submitting = false; }
      },
    };
  }
</script>
@endpush
@endsection
