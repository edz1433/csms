@extends('layouts.app')

@section('title', 'Items / Stocks')
@section('header', 'Items & Stock Levels')
@section('subheader', 'Master item list with on-hand quantities')

@section('content')
<x-setup.scaffold
    resource="items"
    singular="Item"
    :store-url="route('items.store')"
    :update-url="route('items.update', '__ID__')"
    :ajax-url="route('items.index')"
    :can-write="auth()->user()->canWrite()"
    blurb="On-hand quantity is the authoritative stock level, updated by Receiving and Releasing."
    :blank="['id' => null, 'stock_number' => '', 'name' => '', 'description' => '', 'unit_id' => '', 'account_title_id' => '', 'unit_cost' => '', 'on_hand_qty' => 0, 'is_active' => true]"
    :order="[[1, 'asc']]"
    :columns="[
        ['data' => 'stock_number', 'title' => 'Stock No.'],
        ['data' => 'name', 'title' => 'Item'],
        ['data' => 'unit', 'title' => 'Unit', 'className' => 'text-center'],
        ['data' => 'account', 'title' => 'Account Title / RCA'],
        ['data' => 'unit_cost', 'title' => 'Unit Cost', 'className' => 'text-right'],
        ['data' => 'on_hand', 'title' => 'On Hand', 'className' => 'text-right'],
        ['data' => 'status', 'title' => 'Status', 'orderable' => false, 'searchable' => false, 'className' => 'text-center'],
        ['data' => 'action', 'title' => '', 'orderable' => false, 'searchable' => false, 'className' => 'text-right'],
    ]"
>
    {{-- Consolidated QR sheet: every active item's tag in one printable file. --}}
    <x-slot:toolbar>
        @if (auth()->user()->canWrite())
            <x-action-guard>
                <x-ui.button variant="ghost" icon="upload"
                             onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'items-import' }))"
                             title="Add many items at once from a CSV list">
                    Import
                </x-ui.button>
            </x-action-guard>
        @endif
        <x-ui.button variant="ghost" icon="qr-code" :href="route('inventory.labels')"
                     target="_blank" rel="noopener" title="Print one QR tag per item">
            QR Tags
        </x-ui.button>
    </x-slot:toolbar>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="space-y-1">
            <label class="block text-sm font-medium text-cpsu-black">Item Code</label>
            <input type="text" x-model="form.stock_number" readonly
                   class="w-full rounded-lg border border-cpsu-border px-3 py-2 text-sm bg-gray-50 text-gray-500 cursor-not-allowed font-mono"
                   :placeholder="mode === 'create' ? 'Auto-generated (e.g. CS00001)' : ''">
            <p class="text-xs text-gray-400">Assigned automatically, starting at CS00001.</p>
        </div>
        <x-setup.select label="Default Unit" name="unit_id" required :options="$units" placeholder="Select unit" />
    </div>
    <x-setup.field label="Item Name" name="name" required placeholder="e.g. Bond Paper A4 (sub. 20)" />

    <div class="space-y-1">
        <label class="block text-sm font-medium text-cpsu-black">Description</label>
        <textarea x-model="form.description" rows="2"
                  class="w-full rounded-lg border border-cpsu-border px-3 py-2 text-sm outline-none focus:border-cpsu-green focus:ring-2 focus:ring-cpsu-green/20"
                  placeholder="Optional notes / specification"></textarea>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <x-setup.select label="Default Account Title" name="account_title_id" :options="$accountTitles"
            placeholder="Select account title (optional)" />
        <div class="space-y-1">
            <label class="block text-sm font-medium text-cpsu-black">Unit Cost (₱)</label>
            <input type="number" step="0.01" min="0" x-model="form.unit_cost"
                   class="w-full rounded-lg border border-cpsu-border px-3 py-2 text-sm outline-none focus:border-cpsu-green focus:ring-2 focus:ring-cpsu-green/20"
                   placeholder="0.00">
            <p class="text-xs text-gray-400">Used to price issuances on the RSMI report.</p>
        </div>
        <div class="space-y-1">
            <label class="block text-sm font-medium text-cpsu-black">On Hand</label>
            <input type="number" step="0.01" min="0" inputmode="decimal" x-model="form.on_hand_qty"
                   class="w-full rounded-lg border border-cpsu-border px-3 py-2 text-sm outline-none focus:border-cpsu-green focus:ring-2 focus:ring-cpsu-green/20"
                   placeholder="0.00">
            <p class="text-xs text-gray-400">Accepts decimal quantities.</p>
        </div>
    </div>

    <label class="flex items-center gap-2 text-sm text-cpsu-black select-none">
        <input type="checkbox" x-model="form.is_active" class="rounded border-cpsu-border text-cpsu-green focus:ring-cpsu-green/30">
        Active (available for receiving &amp; releasing)
    </label>
</x-setup.scaffold>

{{-- ============ Bulk import ============
     A CSV / pasted list becomes items in one go. Everything a row leaves out
     falls back to the defaults: unit pcs, status Active, cost and qty 0. --}}
@if (auth()->user()->canWrite())
<x-action-guard>
<div x-data="itemImport({ importUrl: @js(route('items.import')) })">
    <x-ui.modal name="items-import" title="Import items" max-width="max-w-2xl">
        <div class="space-y-4">

            {{-- Start here: the template is the format, already filled in. --}}
            <div class="rounded-xl border border-cpsu-green/30 bg-cpsu-green/5 p-4 flex flex-wrap items-center gap-3">
                <i data-lucide="file-down" class="w-8 h-8 text-cpsu-green shrink-0"></i>
                <div class="flex-1 min-w-[14rem]">
                    <p class="text-sm font-bold text-cpsu-black">Not sure of the format? Start from the template.</p>
                    <p class="text-xs text-gray-500 mt-0.5">
                        A ready-made CSV with the three column headings and example rows — fill it in
                        (Excel: File ▸ Save As ▸ CSV) and upload it below.
                    </p>
                </div>
                <a href="{{ route('items.import.template') }}"
                   class="inline-flex items-center justify-center gap-2 rounded-lg bg-cpsu-green hover:bg-cpsu-green-dark text-white font-semibold px-4 py-2 text-sm transition active:scale-95 shrink-0">
                    <i data-lucide="download" class="w-4 h-4"></i> Download CSV template
                </a>
            </div>

            {{-- What the importer expects: three columns, nothing else. --}}
            <div class="rounded-xl border border-cpsu-border bg-cpsu-bg/60 p-4">
                <p class="text-sm text-cpsu-black">
                    Three columns are read from the file — <b>Item Name</b>, <b>Unit Cost</b>, <b>Description</b>.
                    Only the name is required; any other column is ignored.
                </p>
                <div class="mt-3 overflow-x-auto">
                    <table class="text-xs w-full min-w-[30rem]">
                        <thead>
                            <tr class="text-left text-gray-500 uppercase tracking-wide">
                                <th class="py-1 pr-3 font-semibold">Column</th>
                                <th class="py-1 pr-3 font-semibold">Accepted headings</th>
                                <th class="py-1 font-semibold">Blank / NULL becomes</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-600">
                            <tr class="border-t border-cpsu-border/70">
                                <td class="py-1 pr-3 font-semibold text-cpsu-black">1. Item name</td>
                                <td class="py-1 pr-3">name · item · particulars</td>
                                <td class="py-1 text-cpsu-danger">row skipped</td>
                            </tr>
                            <tr class="border-t border-cpsu-border/70">
                                <td class="py-1 pr-3 font-semibold text-cpsu-black">2. Unit cost</td>
                                <td class="py-1 pr-3">unit cost · cost · price</td>
                                <td class="py-1">0.00</td>
                            </tr>
                            <tr class="border-t border-cpsu-border/70">
                                <td class="py-1 pr-3 font-semibold text-cpsu-black">3. Description</td>
                                <td class="py-1 pr-3">description · specification · remarks</td>
                                <td class="py-1">empty (NULL)</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="text-xs text-gray-500 mt-3">
                    No header row? The columns are read in that same order — and a single column of names works too.
                    Empty cells, <span class="font-mono">NULL</span>, <span class="font-mono">N/A</span> and
                    <span class="font-mono">-</span> all count as blank.
                </p>
            </div>

            {{-- File --}}
            <div>
                <label class="block text-sm font-medium text-cpsu-black mb-1">CSV file <span class="text-cpsu-danger">*</span></label>
                <label class="flex items-center gap-3 rounded-xl border-2 border-dashed px-4 py-4 cursor-pointer transition"
                       :class="dragging ? 'border-cpsu-green bg-cpsu-green/5' : 'border-cpsu-border hover:border-cpsu-green/60'"
                       @dragover.prevent="dragging = true" @dragleave.prevent="dragging = false"
                       @drop.prevent="drop($event)">
                    <i data-lucide="file-spreadsheet" class="w-8 h-8 text-cpsu-green shrink-0"></i>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-cpsu-black" x-text="fileName || 'Choose a .csv / .txt file or drop it here'"></p>
                        <p class="text-xs text-gray-400" x-text="fileName ? fileSize : 'Excel: File ▸ Save As ▸ CSV first. Up to 5,000 rows per import.'"></p>
                    </div>
                    <input type="file" accept=".csv,.txt,.tsv,text/csv,text/plain" class="hidden" x-ref="file" @change="pick($event)">
                    <span class="ml-auto shrink-0 text-xs font-semibold text-cpsu-green" x-show="!fileName">Browse</span>
                    <button type="button" x-show="fileName" x-cloak @click.prevent.stop="clearFile()"
                            class="ml-auto shrink-0 text-xs font-semibold text-cpsu-danger hover:underline">Remove</button>
                </label>
            </div>

            {{-- Options --}}
            <label class="flex items-start gap-2 text-sm text-cpsu-black select-none">
                <input type="checkbox" x-model="updateExisting" class="mt-0.5 rounded border-cpsu-border text-cpsu-green focus:ring-cpsu-green/30">
                <span>
                    Update items that already exist
                    <span class="block text-xs text-gray-400">Matched by item name; only cost and description are overwritten. Off: an item already on the list is left untouched and reported as a duplicate.</span>
                </span>
            </label>

            <p class="text-xs text-gray-500 flex items-start gap-1.5">
                <i data-lucide="info" class="w-3.5 h-3.5 mt-0.5 shrink-0 text-cpsu-green"></i>
                Every imported item is created <b class="mx-1">Active</b>, measured in <b class="mx-1">pcs</b>, with no
                stock on hand and an automated code (CS00001, …) — set the unit, quantity or account title afterwards by
                editing the item. A very long name is kept in full: the overflow becomes its description.
            </p>

            {{-- Result --}}
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
                <x-ui.button variant="ghost" x-on:click="$dispatch('close-modal', 'items-import')">Close</x-ui.button>
                <x-ui.button variant="primary" icon="upload" x-on:click="submit()" x-bind:disabled="submitting">
                    <span x-show="!submitting">Import items</span>
                    <span x-show="submitting" x-cloak>Importing…</span>
                </x-ui.button>
            </div>
        </div>
    </x-ui.modal>
</div>
</x-action-guard>

@push('scripts')
<script>
  function itemImport(cfg) {
    return {
      updateExisting: false, submitting: false,
      fileName: '', fileSize: '', dragging: false,
      result: null, message: '',

      init() {
        var self = this;
        // Every open starts clean, so an old summary is never mistaken for
        // the result of the list currently on screen.
        window.addEventListener('open-modal', function (e) {
          if (e.detail === 'items-import') { self.reset(); }
        });
      },

      reset() {
        this.updateExisting = false; this.result = null;
        this.message = ''; this.clearFile();
      },

      /* ---------- file ---------- */
      pick(e) { this.take(e.target.files && e.target.files[0]); },

      drop(e) {
        this.dragging = false;
        var file = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
        if (file) {
          this.$refs.file.files = e.dataTransfer.files;
          this.take(file);
        }
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

      /* ---------- submit ---------- */
      async submit() {
        if (this.submitting) return;

        if (!this.fileName || !this.$refs.file.files[0]) {
          CPSU.toast('Choose a CSV file first.', 'error');
          return;
        }

        var body = new FormData();
        body.append('file', this.$refs.file.files[0]);
        body.append('update_existing', this.updateExisting ? 1 : 0);

        this.submitting = true;
        this.result = null;

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
            this.submitting = false;
            return;
          }

          this.result = j.summary || {};
          this.message = j.message || 'Import finished.';
          CPSU.toast(this.message, (this.result.created || this.result.updated) ? 'success' : 'info');

          if (window.reloadTable['items']) { window.reloadTable['items'](); }
          this.clearFile();
          this.$nextTick(function () { window.lucide && lucide.createIcons(); });
        } catch (e) {
          CPSU.toast('Network error — nothing was imported.', 'error');
        }

        this.submitting = false;
      },
    };
  }
</script>
@endpush
@endif
@endsection

