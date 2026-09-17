{{-- Create or import items without leaving the delivery being entered.
     Both tools announce what they made on the window as `items-created`;
     the receiving form listens and puts each one straight on the delivery,
     so a brand-new item never has to be looked up again afterwards. --}}
<div x-data="receivingItemTools({
        storeUrl: @js(route('items.store')),
        importUrl: @js(route('items.import')),
        defaultUnitId: @js(optional($units->firstWhere('abbreviation', 'pcs') ?? $units->first())->id),
     })">

    {{-- ============ One new item ============ --}}
    <x-ui.modal name="receiving-new-item" title="New item" max-width="max-w-2xl">
        <div class="space-y-4">
            <p class="text-xs text-gray-500 flex items-start gap-1.5">
                <i data-lucide="info" class="w-3.5 h-3.5 mt-0.5 shrink-0 text-cpsu-green"></i>
                Added to the master item list with an automated code (CS00001, …) and <b class="mx-1">no stock</b> —
                the quantity you enter on the delivery is what puts it on hand.
            </p>

            <div class="space-y-1">
                <label class="block text-sm font-medium text-cpsu-black">Item Name <span class="text-cpsu-danger">*</span></label>
                <input type="text" x-model="form.name" x-ref="newItemName" @keydown.enter.prevent="save()"
                       placeholder="e.g. Bond Paper A4 (sub. 20)"
                       class="w-full rounded-lg border px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-cpsu-green/20"
                       :class="errors.name ? 'border-cpsu-danger' : 'border-cpsu-border focus:border-cpsu-green'">
                <p x-show="errors.name" x-cloak x-text="errors.name" class="text-xs text-cpsu-danger"></p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="space-y-1">
                    <label class="block text-sm font-medium text-cpsu-black">Unit <span class="text-cpsu-danger">*</span></label>
                    <select x-model="form.unit_id"
                            class="w-full rounded-lg border px-3 py-2 text-sm bg-white outline-none focus:ring-2 focus:ring-cpsu-green/20"
                            :class="errors.unit_id ? 'border-cpsu-danger' : 'border-cpsu-border focus:border-cpsu-green'">
                        @foreach ($units as $u)
                            <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->abbreviation }})</option>
                        @endforeach
                    </select>
                    <p x-show="errors.unit_id" x-cloak x-text="errors.unit_id" class="text-xs text-cpsu-danger"></p>
                </div>
                <div class="space-y-1">
                    <label class="block text-sm font-medium text-cpsu-black">Unit Cost (₱)</label>
                    <input type="number" step="0.01" min="0" x-model="form.unit_cost" placeholder="0.00"
                           class="w-full rounded-lg border border-cpsu-border px-3 py-2 text-sm text-right outline-none focus:border-cpsu-green focus:ring-2 focus:ring-cpsu-green/20">
                </div>
                <div class="space-y-1">
                    <label class="block text-sm font-medium text-cpsu-black">Account Title</label>
                    <select x-model="form.account_title_id"
                            class="w-full rounded-lg border border-cpsu-border px-3 py-2 text-sm bg-white outline-none focus:border-cpsu-green focus:ring-2 focus:ring-cpsu-green/20">
                        <option value="">— None —</option>
                        @foreach ($accountTitles as $at)
                            <option value="{{ $at->id }}">{{ $at->name }} — {{ $at->rca_code }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="space-y-1">
                <label class="block text-sm font-medium text-cpsu-black">Description</label>
                <textarea x-model="form.description" rows="2" placeholder="Optional notes / specification"
                          class="w-full rounded-lg border border-cpsu-border px-3 py-2 text-sm outline-none focus:border-cpsu-green focus:ring-2 focus:ring-cpsu-green/20"></textarea>
            </div>

            <label class="flex items-center gap-2 text-sm text-cpsu-black select-none">
                <input type="checkbox" x-model="addToDelivery" class="rounded border-cpsu-border text-cpsu-green focus:ring-cpsu-green/30">
                Add it to this delivery straight away
            </label>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-cpsu-border">
                <x-ui.button variant="ghost" x-on:click="$dispatch('close-modal', 'receiving-new-item')">Cancel</x-ui.button>
                <x-ui.button variant="primary" icon="plus" x-on:click="save(true)" x-bind:disabled="saving">
                    <span x-show="!saving">Save &amp; add another</span>
                    <span x-show="saving" x-cloak>Saving…</span>
                </x-ui.button>
                <x-ui.button variant="primary" icon="check" x-on:click="save()" x-bind:disabled="saving">
                    <span x-show="!saving">Save item</span>
                    <span x-show="saving" x-cloak>Saving…</span>
                </x-ui.button>
            </div>
        </div>
    </x-ui.modal>

    {{-- ============ Bulk import ============ --}}
    <x-ui.modal name="receiving-import-items" title="Import items onto this delivery" max-width="max-w-2xl">
        <div class="space-y-4">

            <div class="rounded-xl border border-cpsu-green/30 bg-cpsu-green/5 p-3 flex flex-wrap items-center gap-3">
                <i data-lucide="file-down" class="w-7 h-7 text-cpsu-green shrink-0"></i>
                <div class="flex-1 min-w-[14rem]">
                    <p class="text-sm font-bold text-cpsu-black">Three columns: Item Name, Unit Cost, Description.</p>
                    <p class="text-xs text-gray-500 mt-0.5">
                        Only the name is required. No header row? The columns are read in that order — a single
                        column of names works too. Excel: File ▸ Save As ▸ CSV.
                    </p>
                </div>
                <a href="{{ route('items.import.template') }}"
                   class="inline-flex items-center justify-center gap-2 rounded-lg bg-cpsu-green hover:bg-cpsu-green-dark text-white font-semibold px-3 py-2 text-xs transition active:scale-95 shrink-0">
                    <i data-lucide="download" class="w-3.5 h-3.5"></i> CSV template
                </a>
            </div>

            <div>
                <label class="flex items-center gap-3 rounded-xl border-2 border-dashed px-4 py-4 cursor-pointer transition"
                       :class="dragging ? 'border-cpsu-green bg-cpsu-green/5' : 'border-cpsu-border hover:border-cpsu-green/60'"
                       @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false"
                       @drop.prevent="drop($event)">
                    <i data-lucide="file-spreadsheet" class="w-8 h-8 text-cpsu-green shrink-0"></i>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-cpsu-black" x-text="fileName || 'Choose a .csv / .txt file or drop it here'"></p>
                        <p class="text-xs text-gray-400" x-text="fileName ? fileSize : 'Up to 5,000 rows per import.'"></p>
                    </div>
                    <input type="file" accept=".csv,.txt,.tsv,text/csv,text/plain" class="hidden" x-ref="file" @change="pick($event)">
                    <span class="ml-auto shrink-0 text-xs font-semibold text-cpsu-green" x-show="!fileName">Browse</span>
                    <button type="button" x-show="fileName" x-cloak @click.prevent.stop="clearFile()"
                            class="ml-auto shrink-0 text-xs font-semibold text-cpsu-danger hover:underline">Remove</button>
                </label>
            </div>

            <label class="flex items-start gap-2 text-sm text-cpsu-black select-none">
                <input type="checkbox" x-model="updateExisting" class="mt-0.5 rounded border-cpsu-border text-cpsu-green focus:ring-cpsu-green/30">
                <span>
                    Update items that already exist
                    <span class="block text-xs text-gray-400">Matched by item name; only cost and description are overwritten. Off: an item already on the list is left untouched and reported as a duplicate.</span>
                </span>
            </label>

            <label class="flex items-start gap-2 text-sm text-cpsu-black select-none">
                <input type="checkbox" x-model="addToDelivery" class="mt-0.5 rounded border-cpsu-border text-cpsu-green focus:ring-cpsu-green/30">
                <span>
                    Put every imported item on this delivery
                    <span class="block text-xs text-gray-400">Each one arrives as a line with quantity 1 and the cost from the file — set the real quantities, then save the delivery.</span>
                </span>
            </label>

            <p class="text-xs text-gray-500 flex items-start gap-1.5">
                <i data-lucide="info" class="w-3.5 h-3.5 mt-0.5 shrink-0 text-cpsu-green"></i>
                Imported items are created <b class="mx-1">Active</b>, measured in <b class="mx-1">pcs</b> and with
                <b class="mx-1">no stock</b> — what you enter on the delivery is what puts them on hand.
            </p>

            <template x-if="result">
                <div class="rounded-xl border p-4"
                     :class="result.created || result.updated ? 'border-cpsu-green/40 bg-cpsu-green/5' : 'border-amber-300 bg-amber-50'">
                    <p class="text-sm font-bold text-cpsu-black" x-text="message"></p>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mt-3">
                        <template x-for="stat in stats()" :key="stat.label">
                            <div class="rounded-lg bg-white border border-cpsu-border px-3 py-2">
                                <p class="text-[10px] uppercase tracking-wide text-gray-400" x-text="stat.label"></p>
                                <p class="text-lg font-extrabold" x-text="stat.value"></p>
                            </div>
                        </template>
                    </div>
                    <template x-if="result.errors && result.errors.length">
                        <div class="mt-3">
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Rows not imported</p>
                            <ul class="mt-1 space-y-0.5 max-h-32 overflow-auto">
                                <template x-for="(line, i) in result.errors" :key="i">
                                    <li class="text-xs text-gray-600" x-text="line"></li>
                                </template>
                            </ul>
                        </div>
                    </template>
                </div>
            </template>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-cpsu-border">
                <x-ui.button variant="ghost" x-on:click="$dispatch('close-modal', 'receiving-import-items')">Close</x-ui.button>
                <x-ui.button variant="primary" icon="upload" x-on:click="importItems()" x-bind:disabled="importing">
                    <span x-show="!importing">Import items</span>
                    <span x-show="importing" x-cloak>Importing…</span>
                </x-ui.button>
            </div>
        </div>
    </x-ui.modal>
</div>

@push('scripts')
<script>
  function receivingItemTools(cfg) {
    return {
      // Both tools default to putting what they make on the open delivery —
      // that is the whole point of running them from this screen.
      addToDelivery: true,

      /* ---------- new item ---------- */
      saving: false, errors: {},
      form: { name: '', unit_id: cfg.defaultUnitId || '', unit_cost: '', account_title_id: '', description: '' },

      /* ---------- import ---------- */
      importing: false, updateExisting: false,
      fileName: '', fileSize: '', dragging: false,
      result: null, message: '',

      init() {
        var self = this;
        // Every open starts clean, so an old result is never mistaken for the
        // outcome of what is on screen now.
        window.addEventListener('open-modal', function (e) {
          if (e.detail === 'receiving-new-item') { self.resetForm(); }
          if (e.detail === 'receiving-import-items') { self.resetImport(); }
        });
      },

      /** Hand the new items to the delivery form (and to any item table). */
      announce(items) {
        if (!items || !items.length) return;
        window.dispatchEvent(new CustomEvent('items-created', {
          detail: { items: items, addToDelivery: this.addToDelivery },
        }));
        if (window.reloadTable && window.reloadTable['items']) { window.reloadTable['items'](); }
      },

      /* ================= new item ================= */

      resetForm() {
        this.errors = {};
        this.form = { name: '', unit_id: cfg.defaultUnitId || '', unit_cost: '', account_title_id: '', description: '' };
        this.$nextTick(function () { this.$refs.newItemName && this.$refs.newItemName.focus(); }.bind(this));
      },

      async save(keepOpen) {
        if (this.saving) return;

        if (!String(this.form.name || '').trim()) {
          this.errors = { name: 'Give the item a name.' };
          return;
        }

        this.saving = true; this.errors = {};

        try {
          var res = await fetch(cfg.storeUrl, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({
              name: this.form.name,
              unit_id: this.form.unit_id,
              account_title_id: this.form.account_title_id || null,
              unit_cost: this.form.unit_cost === '' ? 0 : Number(this.form.unit_cost),
              description: this.form.description || null,
              is_active: true,
              // Receiving puts the stock on: the item itself starts at zero.
              on_hand_qty: 0,
            }),
          });

          if (res.status === 422) {
            var j422 = await res.json();
            var flat = {};
            Object.keys(j422.errors || {}).forEach(function (k) { flat[k] = j422.errors[k][0]; });
            this.errors = flat;
            CPSU.toast(j422.message || 'Please fix the errors.', 'error');
            this.saving = false;
            return;
          }

          if (!res.ok) { CPSU.toast('The item could not be saved.', 'error'); this.saving = false; return; }

          var j = await res.json();
          this.announce(j.item ? [j.item] : []);
          CPSU.toast(this.addToDelivery ? 'Item created and added to this delivery.' : 'Item created.', 'success');

          if (keepOpen) {
            this.resetForm();
          } else {
            this.$dispatch('close-modal', 'receiving-new-item');
          }
        } catch (e) {
          CPSU.toast('Network error — the item was not saved.', 'error');
        }

        this.saving = false;
      },

      /* ================= import ================= */

      resetImport() {
        this.updateExisting = false; this.result = null; this.message = ''; this.clearFile();
      },

      pick(e) { this.take(e.target.files && e.target.files[0]); },

      drop(e) {
        this.dragging = false;
        var file = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
        if (file) { this.$refs.file.files = e.dataTransfer.files; this.take(file); }
      },

      take(file) {
        if (!file) return;
        this.fileName = file.name;
        this.fileSize = file.size > 1048576
          ? (file.size / 1048576).toFixed(1) + ' MB'
          : Math.max(1, Math.round(file.size / 1024)) + ' KB';
      },

      clearFile() {
        this.fileName = ''; this.fileSize = '';
        if (this.$refs.file) { this.$refs.file.value = ''; }
      },

      stats() {
        var r = this.result || {};
        return [
          { label: 'Added', value: r.created || 0 },
          { label: 'Updated', value: r.updated || 0 },
          { label: 'Duplicates', value: r.skipped || 0 },
          { label: 'Rows read', value: r.rows || 0 },
        ];
      },

      async importItems() {
        if (this.importing) return;

        if (!this.fileName || !this.$refs.file.files[0]) {
          CPSU.toast('Choose a CSV file first.', 'error');
          return;
        }

        var body = new FormData();
        body.append('file', this.$refs.file.files[0]);
        body.append('update_existing', this.updateExisting ? 1 : 0);
        body.append('with_items', this.addToDelivery ? 1 : 0);

        this.importing = true; this.result = null;

        try {
          var res = await fetch(cfg.importUrl, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: body,
          });
          var j = await res.json().catch(function () { return {}; });

          if (!res.ok) {
            var msg = j.message || 'That list could not be imported.';
            if (j.errors) { msg = Object.values(j.errors)[0][0]; }
            CPSU.toast(msg, 'error');
            this.importing = false;
            return;
          }

          this.result = j.summary || {};
          this.message = j.message || 'Import finished.';

          if (this.addToDelivery) {
            this.announce(j.items || []);

            // The form can only usefully hold so many rows; say plainly which
            // items did not make it onto the delivery.
            if (j.items_truncated) {
              this.message += ' Only the first ' + (j.items || []).length + ' were added to this delivery — '
                + 'the rest are on the item list and can be searched for.';
            }
          } else {
            if (window.reloadTable && window.reloadTable['items']) { window.reloadTable['items'](); }
          }

          CPSU.toast(this.message, (this.result.created || this.result.updated) ? 'success' : 'info');
          this.clearFile();
          this.$nextTick(function () { window.lucide && lucide.createIcons(); });
        } catch (e) {
          CPSU.toast('Network error — nothing was imported.', 'error');
        }

        this.importing = false;
      },
    };
  }
</script>
@endpush
