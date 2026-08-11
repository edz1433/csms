@extends('layouts.app')

@section('title', 'QR Scanner')
@section('header', 'QR Scanner')
@section('subheader', 'Scan an item tag, set the quantity, repeat')

@section('content')
<div x-data="qrScanner({
        statusUrl: @js(route('inventory.status')),
        lookupUrl: @js(route('inventory.lookup')),
        countUrl: @js(route('inventory.count')),
        active: {{ $session ? 'true' : 'false' }},
        session: @js($session ? ['id' => $session->id, 'reference' => $session->reference, 'title' => $session->title] : null),
        progress: @js($progress ?? ['counted' => 0, 'total' => 0, 'remaining' => 0, 'variance' => 0, 'percent' => 0]),
     })"
     class="space-y-3 lg:grid lg:grid-cols-5 lg:gap-4 lg:space-y-0 pb-40 lg:pb-0">

    {{-- ══════════ Camera column ══════════ --}}
    <div class="lg:col-span-3 space-y-3">

        {{-- Status + progress, one compact strip --}}
        <div class="bg-white rounded-xl border border-cpsu-border shadow-sm px-4 py-3">
            <div class="flex items-center gap-2 flex-wrap">
                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide"
                      :class="active ? 'bg-cpsu-green/10 text-cpsu-green' : 'bg-red-100 text-cpsu-danger'">
                    <span class="h-1.5 w-1.5 rounded-full" :class="active ? 'bg-cpsu-green animate-pulse' : 'bg-cpsu-danger'"></span>
                    <span x-text="active ? 'Counting live' : 'No active inventory'"></span>
                </span>
                <span class="text-xs text-gray-400 font-mono" x-text="session ? session.reference : ''"></span>
                <span class="ml-auto text-sm font-bold" x-text="progress.counted + ' / ' + progress.total"></span>
            </div>
            <div class="h-1.5 rounded-full bg-cpsu-bg overflow-hidden mt-2">
                <div class="h-full rounded-full bg-cpsu-green" style="transition:width .5s ease"
                     :style="'width:' + progress.percent + '%'"></div>
            </div>
        </div>

        {{-- Live video --}}
        <div class="relative bg-black rounded-xl overflow-hidden shadow-sm">
            <div id="qr-reader" class="w-full" style="min-height:58vh"></div>

            {{-- Aiming frame --}}
            <div aria-hidden="true" class="pointer-events-none absolute inset-0 flex items-center justify-center" x-show="running">
                <div class="relative" style="width:62vw;max-width:260px;aspect-ratio:1/1">
                    <span class="absolute left-0 top-0 h-9 w-9 border-l-4 border-t-4 border-cpsu-gold rounded-tl-xl"></span>
                    <span class="absolute right-0 top-0 h-9 w-9 border-r-4 border-t-4 border-cpsu-gold rounded-tr-xl"></span>
                    <span class="absolute left-0 bottom-0 h-9 w-9 border-l-4 border-b-4 border-cpsu-gold rounded-bl-xl"></span>
                    <span class="absolute right-0 bottom-0 h-9 w-9 border-r-4 border-b-4 border-cpsu-gold rounded-br-xl"></span>
                </div>
            </div>

            {{-- Overlay: locked, starting, or camera trouble --}}
            <div class="absolute inset-0 flex flex-col items-center justify-center text-center px-6 bg-black/85"
                 x-show="!running" x-cloak>
                <template x-if="!active">
                    <div>
                        <div class="h-14 w-14 mx-auto rounded-2xl bg-cpsu-danger/20 flex items-center justify-center mb-3">
                            <i data-lucide="scan-line" class="w-7 h-7 text-cpsu-danger"></i>
                        </div>
                        <p class="text-white font-bold">No active inventory</p>
                        <p class="text-white/60 text-sm mt-1">Counts cannot be saved. This unlocks by itself once one is cast.</p>
                        <a href="{{ route('inventory.index') }}"
                           class="inline-flex items-center gap-2 mt-4 bg-white/10 hover:bg-white/20 text-white text-sm font-semibold rounded-lg px-4 py-2.5">
                            <i data-lucide="play" class="w-4 h-4"></i> Go to inventories
                        </a>
                    </div>
                </template>

                <template x-if="active && starting">
                    <div>
                        <div class="h-14 w-14 mx-auto rounded-2xl bg-white/10 flex items-center justify-center mb-3">
                            <i data-lucide="camera" class="w-7 h-7 text-white animate-pulse"></i>
                        </div>
                        <p class="text-white font-bold">Starting camera…</p>
                        <p class="text-white/60 text-sm mt-1">Allow camera access when your browser asks.</p>
                    </div>
                </template>

                <template x-if="active && !starting">
                    <div>
                        <div class="h-14 w-14 mx-auto rounded-2xl bg-white/10 flex items-center justify-center mb-3">
                            <i data-lucide="camera-off" class="w-7 h-7 text-white"></i>
                        </div>
                        <p class="text-white font-bold" x-text="cameraError ? 'Camera unavailable' : 'Camera is off'"></p>
                        <p class="text-white/60 text-sm mt-1 max-w-xs mx-auto leading-relaxed"
                           x-text="cameraError || 'Tap below to start the live view.'"></p>
                        <button type="button" @click="start()"
                                class="inline-flex items-center gap-2 mt-4 bg-cpsu-gold hover:brightness-95 text-cpsu-black text-sm font-bold rounded-lg px-5 py-3">
                            <i data-lucide="camera" class="w-4 h-4"></i>
                            <span x-text="cameraError ? 'Try again' : 'Start camera'"></span>
                        </button>

                        {{-- Plain HTTP: the two ways to get the camera back --}}
                        <div x-show="insecure" x-cloak class="mt-4 text-left max-w-sm mx-auto space-y-2">
                            <a :href="httpsUrl"
                               class="flex items-center gap-2 rounded-lg bg-white/10 hover:bg-white/20 px-3 py-2.5 text-white text-sm font-semibold">
                                <i data-lucide="lock" class="w-4 h-4 shrink-0"></i>
                                <span>Open this page over HTTPS</span>
                            </a>
                            <button type="button" @click="copyFlag()"
                                    class="w-full flex items-center gap-2 rounded-lg bg-white/10 hover:bg-white/20 px-3 py-2.5 text-white text-sm font-semibold text-left">
                                <i data-lucide="copy" class="w-4 h-4 shrink-0"></i>
                                <span>Copy the Chrome/Edge flag, then allow<br>
                                    <span class="font-mono text-[11px] text-white/70" x-text="origin"></span>
                                </span>
                            </button>
                            <p class="text-white/40 text-[11px] leading-relaxed">
                                Paste the flag in a new tab, add the address above to the list, choose Enabled and relaunch.
                                An item's own QR tag always works without any of this.
                            </p>
                        </div>

                        <p class="text-white/40 text-xs mt-3" x-show="!insecure">You can also type a stock number below.</p>
                    </div>
                </template>
            </div>

            {{-- Floating camera controls --}}
            <div class="absolute bottom-3 inset-x-0 flex justify-center gap-2" x-show="running" x-cloak>
                <button type="button" @click="flip()"
                        class="inline-flex items-center gap-1.5 rounded-full bg-black/60 backdrop-blur text-white text-xs font-semibold px-4 py-2.5">
                    <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i> Flip
                </button>
                <button type="button" @click="stop()"
                        class="inline-flex items-center gap-1.5 rounded-full bg-black/60 backdrop-blur text-white text-xs font-semibold px-4 py-2.5">
                    <i data-lucide="camera-off" class="w-3.5 h-3.5"></i> Stop
                </button>
            </div>
        </div>

        {{-- Manual entry — always available, whatever the browser allows --}}
        <div class="bg-white rounded-xl border border-cpsu-border shadow-sm p-3 flex items-center gap-2">
            <input x-model="manual" @keydown.enter.prevent="lookup(manual)" type="search" inputmode="text"
                   placeholder="Type a stock number, then Enter"
                   class="flex-1 min-w-0 rounded-lg border border-cpsu-border px-3 py-2.5 text-sm outline-none focus:border-cpsu-green">
            <x-ui.button variant="primary" icon="search" x-on:click="lookup(manual)">Find</x-ui.button>
        </div>
    </div>

    {{-- ══════════ Count panel ══════════
         A bottom sheet on phones, a side card from lg up. --}}
    <div class="lg:col-span-2">
        <div class="bg-white border-cpsu-border shadow-2xl transition-transform duration-200
                    fixed inset-x-0 bottom-0 z-40 rounded-t-2xl border-t max-h-[80vh] overflow-y-auto
                    lg:static lg:rounded-xl lg:border lg:shadow-sm lg:max-h-none lg:translate-y-0"
             :class="item ? 'translate-y-0' : 'translate-y-full lg:translate-y-0'">

            {{-- Grab handle (phones) --}}
            <div class="lg:hidden pt-2 pb-1 flex justify-center" x-show="item" x-cloak>
                <span class="h-1 w-10 rounded-full bg-gray-300"></span>
            </div>

            <template x-if="!item">
                <div class="hidden lg:block text-center py-12 text-gray-400 px-5">
                    <i data-lucide="qr-code" class="w-10 h-10 mx-auto mb-3 opacity-40"></i>
                    <p class="text-sm">Point the camera at an item tag.<br>Its count sheet opens here.</p>
                </div>
            </template>

            <template x-if="item">
                <div class="p-4 sm:p-5 space-y-4">
                    <div class="flex items-start gap-3">
                        <div class="min-w-0 flex-1">
                            <p class="text-xs text-gray-400 font-mono" x-text="item.stock_number"></p>
                            <h3 class="text-lg font-extrabold leading-tight" x-text="item.name"></h3>
                        </div>
                        <button type="button" @click="clearItem()"
                                class="p-2 -mr-1 rounded-lg text-gray-400 hover:text-cpsu-black hover:bg-cpsu-bg">
                            <i data-lucide="x" class="w-5 h-5"></i>
                        </button>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="rounded-lg bg-cpsu-bg p-3">
                            <p class="text-[11px] text-gray-400 uppercase tracking-wide">On record</p>
                            <p class="text-xl font-extrabold tabular-nums" x-text="fmt(item.system_qty)"></p>
                        </div>
                        <div class="rounded-lg p-3"
                             :class="variance() === null ? 'bg-cpsu-bg' : (variance() === 0 ? 'bg-cpsu-green/10' : 'bg-amber-100')">
                            <p class="text-[11px] text-gray-400 uppercase tracking-wide">Variance</p>
                            <p class="text-xl font-extrabold tabular-nums"
                               :class="variance() === null ? 'text-gray-400' : (variance() >= 0 ? 'text-cpsu-green' : 'text-cpsu-danger')"
                               x-text="variance() === null ? '—' : (variance() > 0 ? '+' : '') + fmt(variance())"></p>
                        </div>
                    </div>

                    {{-- Big touch targets for counting on a phone --}}
                    <div class="space-y-1">
                        <label class="block text-sm font-medium">Counted quantity</label>
                        <div class="flex items-stretch">
                            <button type="button" @click="step(-1)"
                                    class="rounded-l-xl border border-r-0 border-cpsu-border px-5 text-gray-500 active:bg-cpsu-bg">
                                <i data-lucide="minus" class="w-5 h-5"></i>
                            </button>
                            <input x-model.number="qty" type="number" step="0.01" min="0" inputmode="decimal"
                                   @keydown.enter.prevent="save()"
                                   class="w-full border-y border-cpsu-border px-3 py-3 text-center text-2xl font-extrabold outline-none focus:border-cpsu-green">
                            <button type="button" @click="step(1)"
                                    class="rounded-r-xl border border-l-0 border-cpsu-border px-5 text-gray-500 active:bg-cpsu-bg">
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

                    <x-ui.button variant="primary" icon="save" class="w-full py-3.5 text-base"
                                 x-on:click="save()" x-bind:disabled="saving || !active">
                        <span x-show="!saving">Save count</span>
                        <span x-show="saving" x-cloak>Saving…</span>
                    </x-ui.button>

                    <p class="text-xs text-gray-400 text-center" x-show="item.counted_at" x-cloak>
                        Counted at <span x-text="item.counted_at"></span> — saving overwrites it.
                    </p>
                </div>
            </template>
        </div>

        {{-- Recent scans (desktop only; the sheet owns the space on phones) --}}
        <div class="hidden lg:block bg-white rounded-xl border border-cpsu-border shadow-sm p-5 mt-4"
             x-show="recent.length" x-cloak>
            <h3 class="font-bold text-sm mb-3 flex items-center gap-2">
                <i data-lucide="history" class="w-4 h-4 text-cpsu-green"></i> Scanned this session
            </h3>
            <ul class="space-y-2 max-h-56 overflow-y-auto">
                <template x-for="(r, i) in recent" :key="i">
                    <li class="flex items-center gap-3 text-sm">
                        <span class="h-2 w-2 rounded-full shrink-0" :class="r.variance === 0 ? 'bg-cpsu-green' : 'bg-amber-400'"></span>
                        <span class="flex-1 truncate" x-text="r.name"></span>
                        <span class="tabular-nums font-semibold" x-text="fmt(r.qty) + ' ' + (r.unit || '')"></span>
                    </li>
                </template>
            </ul>
        </div>
    </div>
</div>

@push('scripts')
<script src="{{ asset('vendor/html5-qrcode/html5-qrcode.min.js') }}"></script>
<script>
  function qrScanner(cfg) {
    return {
      active: cfg.active, session: cfg.session, progress: cfg.progress,
      item: null, qty: 0, unitId: null, manual: '',
      running: false, starting: false, saving: false, busy: false,
      cameraError: '', insecure: false, facing: 'environment', recent: [],

      init() {
        var self = this;
        setInterval(function () { self.poll(); }, 5000);
        if (this.active) { this.start(); }
        // Free the camera when the tab is hidden; phones kill it anyway.
        document.addEventListener('visibilitychange', function () {
          if (document.hidden) { self.stop(); } else if (self.active && !self.running) { self.start(); }
        });
      },

      /* ---------------- camera ---------------- */
      /**
       * Never refuse up front: plain HTTP still works on some browsers, WebViews
       * and kiosk builds, so the camera is always attempted and only the real
       * failure is reported.
       */
      supportCheck() {
        if (!window.Html5Qrcode) { return 'The scanner library did not load. Refresh the page.'; }
        return '';
      },

      async start() {
        if (this.running || this.starting) return;
        this.cameraError = this.supportCheck();
        if (this.cameraError) return;

        this.shimLegacyCamera();
        this.starting = true;
        try {
          if (!this.reader) { this.reader = new Html5Qrcode('qr-reader', { verbose: false }); }
          await this.launch({ facingMode: this.facing });
          this.running = true;
        } catch (e) {
          // Some browsers reject a facingMode they cannot satisfy — fall back
          // to whatever camera they do report.
          try {
            var cams = await Html5Qrcode.getCameras();
            if (cams && cams.length) {
              await this.launch(cams[cams.length - 1].id);
              this.running = true;
            } else {
              this.cameraError = 'No camera was found on this device.';
            }
          } catch (inner) {
            this.cameraError = this.explain(inner || e);
          }
        }
        this.starting = false;
        this.$nextTick(function () { if (window.lucide) window.lucide.createIcons(); });
      },

      launch(source) {
        var self = this;
        return this.reader.start(
          source,
          {
            fps: 10,
            qrbox: function (w, h) {
              var edge = Math.floor(Math.min(w, h) * 0.72);
              return { width: edge, height: edge };
            },
          },
          function (text) { self.onDecode(text); },
          function () { /* per-frame misses are normal */ }
        );
      },

      /**
       * Older browsers expose the camera through the pre-standard callback API,
       * which some of them still allow on plain HTTP. Promisify it onto
       * navigator.mediaDevices so the scanner library can use it.
       */
      shimLegacyCamera() {
        if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) return;

        var legacy = navigator.getUserMedia || navigator.webkitGetUserMedia
                  || navigator.mozGetUserMedia || navigator.msGetUserMedia;
        if (!legacy) return;

        if (!navigator.mediaDevices) { navigator.mediaDevices = {}; }
        navigator.mediaDevices.getUserMedia = function (constraints) {
          return new Promise(function (resolve, reject) {
            legacy.call(navigator, constraints, resolve, reject);
          });
        };
      },

      explain(e) {
        var name = (e && (e.name || e.message)) || '';

        // On an insecure origin the browser refuses before it ever asks, which
        // surfaces as a missing API or a blank permission error.
        if (!window.isSecureContext && (/NotAllowed|Permission|undefined|mediaDevices|not supported|secure/i.test(name) || !name)) {
          this.insecure = true;
          return 'This page is on plain HTTP, and browsers only hand over the camera on HTTPS or localhost.';
        }
        if (/NotAllowed|Permission/i.test(name)) {
          return 'Camera permission was blocked. Allow it in your browser\'s site settings, then try again.';
        }
        if (/NotFound|Devices/i.test(name)) { return 'No camera was found on this device.'; }
        if (/NotReadable|TrackStart/i.test(name)) { return 'The camera is busy in another app or tab. Close it and try again.'; }
        if (/Overconstrained/i.test(name)) { return 'That camera could not be used. Tap Flip to try the other one.'; }
        return 'The camera could not be started. You can still type a stock number below.';
      },

      /** The exact origin to whitelist in the browser flag. */
      get origin() { return window.location.origin; },

      get httpsUrl() { return window.location.href.replace(/^http:/, 'https:'); },

      copyFlag() {
        var text = 'chrome://flags/#unsafely-treat-insecure-origin-as-secure';
        var el = document.createElement('textarea');
        el.value = text; document.body.appendChild(el); el.select();
        try { document.execCommand('copy'); CPSU.toast('Flag address copied — paste it in a new tab.', 'success'); }
        catch (e) { CPSU.toast('Copy failed — type it manually.', 'error'); }
        document.body.removeChild(el);
      },

      async stop() {
        if (!this.reader || !this.running) return;
        try { await this.reader.stop(); } catch (e) { /* already stopped */ }
        this.running = false;
      },

      async flip() {
        this.facing = this.facing === 'environment' ? 'user' : 'environment';
        await this.stop();
        this.start();
      },

      /* ---------------- scanning ---------------- */
      onDecode(text) {
        if (this.busy || this.saving) return;
        if (text === this._last && Date.now() - (this._lastAt || 0) < 2500) return;
        this._last = text; this._lastAt = Date.now();
        this.beep();
        this.lookup(text);
      },

      async lookup(code) {
        if (!code) return;
        this.busy = true;
        try {
          var res = await fetch(cfg.lookupUrl + '?code=' + encodeURIComponent(code), { headers: { 'Accept': 'application/json' } });
          var j = await res.json();
          if (!res.ok) {
            this.active = j.active !== false;
            CPSU.toast(j.message || 'Could not read that code.', 'error');
            this.busy = false;
            return;
          }
          this.item = j.item;
          this.qty = j.item.counted_qty !== null ? j.item.counted_qty : j.item.system_qty;
          this.unitId = j.item.unit_id;
          this.progress = j.progress;
          this.manual = '';
          this.$nextTick(function () { if (window.lucide) window.lucide.createIcons(); });
        } catch (e) {
          CPSU.toast('Network error while reading that code.', 'error');
        }
        this.busy = false;
      },

      /* ---------------- saving ---------------- */
      async save() {
        if (!this.item || this.saving) return;
        if (this.qty === '' || this.qty === null || isNaN(this.qty) || this.qty < 0) {
          CPSU.toast('Enter the counted quantity.', 'error');
          return;
        }
        this.saving = true;
        try {
          var res = await fetch(cfg.countUrl, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ item_id: this.item.id, unit_id: this.unitId, counted_qty: this.qty }),
          });
          var j = await res.json();
          if (res.status === 409) { this.active = false; this.stop(); CPSU.toast(j.message, 'error'); this.saving = false; return; }
          if (!res.ok) { CPSU.toast('Could not save that count.', 'error'); this.saving = false; return; }

          this.progress = j.progress;
          this.recent.unshift({ name: this.item.name, qty: j.count.counted_qty, unit: j.count.unit, variance: j.count.variance });
          CPSU.toast(this.item.name + ' counted.', 'success');
          this.clearItem();
        } catch (e) { CPSU.toast('Network error.', 'error'); }
        this.saving = false;
      },

      clearItem() { this.item = null; this.qty = 0; this._last = null; },

      /* ---------------- live status ---------------- */
      async poll() {
        try {
          var res = await fetch(cfg.statusUrl, { headers: { 'Accept': 'application/json' } });
          var j = await res.json();
          var was = this.active;
          this.active = j.active;
          this.session = j.session;
          if (j.progress) { this.progress = j.progress; }

          if (was && !j.active) {
            this.stop(); this.clearItem();
            CPSU.toast('The inventory was closed. Scanning is locked.', 'warning');
          }
          if (!was && j.active) {
            CPSU.toast('An inventory is now active — scanning unlocked.', 'success');
            this.start();
          }
        } catch (e) { /* keep polling */ }
      },

      /* ---------------- helpers ---------------- */
      variance() {
        if (!this.item || this.qty === '' || this.qty === null || isNaN(this.qty)) return null;
        return Math.round((Number(this.qty) - Number(this.item.system_qty)) * 100) / 100;
      },
      step(d) { this.qty = Math.max(0, Math.round(((Number(this.qty) || 0) + d) * 100) / 100); },
      fmt(n) { return (Number(n) || 0).toLocaleString(undefined, { maximumFractionDigits: 2 }); },
      unitLabel(id) {
        var opt = document.querySelector('option[value="' + id + '"]');
        return opt ? opt.textContent.trim() : '';
      },
      beep() {
        try {
          var ctx = new (window.AudioContext || window.webkitAudioContext)();
          var osc = ctx.createOscillator(), gain = ctx.createGain();
          osc.connect(gain); gain.connect(ctx.destination);
          osc.frequency.value = 880; gain.gain.value = 0.05;
          osc.start(); osc.stop(ctx.currentTime + 0.08);
        } catch (e) { /* silent is fine */ }
      },
    };
  }
</script>
@endpush
@endsection
