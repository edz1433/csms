@extends('layouts.app')

@section('title', 'System Settings')
@section('header', 'System Settings')
@section('subheader', 'Declare maintenance and control system-wide behaviour')

@section('content')
<div x-data="systemSettings({
        url: @js(route('settings.update')),
        enabled: {{ $settings['maintenance_enabled'] ? 'true' : 'false' }},
        message: @js($settings['maintenance_message']),
        until: @js($settings['maintenance_until'] ? \Illuminate\Support\Carbon::parse($settings['maintenance_until'])->format('Y-m-d H:i') : ''),
     })"
     class="max-w-4xl space-y-4">

    {{-- Status hero --}}
    <div class="rounded-xl border shadow-sm overflow-hidden transition-colors duration-300"
         :class="enabled ? 'bg-amber-50 border-amber-300' : 'bg-white border-cpsu-border'"
         data-aos="fade-up">
        <div class="p-5 sm:p-6 flex flex-wrap items-center gap-5">
            <div class="h-14 w-14 rounded-2xl flex items-center justify-center shrink-0 transition-colors duration-300"
                 :class="enabled ? 'bg-amber-400/20 text-amber-600' : 'bg-cpsu-green/10 text-cpsu-green'">
                <i data-lucide="wrench" class="w-7 h-7" x-show="enabled" x-cloak></i>
                <i data-lucide="check-circle-2" class="w-7 h-7" x-show="!enabled"></i>
            </div>

            <div class="flex-1 min-w-[14rem]">
                <div class="flex items-center gap-2">
                    <h3 class="font-bold">Maintenance Mode</h3>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold uppercase tracking-wide"
                          :class="enabled ? 'bg-amber-400/20 text-amber-700' : 'bg-cpsu-green/10 text-cpsu-green'">
                        <span class="h-1.5 w-1.5 rounded-full" :class="enabled ? 'bg-amber-500 animate-pulse' : 'bg-cpsu-green'"></span>
                        <span x-text="enabled ? 'Declared' : 'System live'"></span>
                    </span>
                </div>
                <p class="text-sm text-gray-500 mt-1" x-show="!enabled">
                    Everyone can sign in and use the system normally.
                </p>
                <p class="text-sm text-amber-800 mt-1" x-show="enabled" x-cloak>
                    Supply and accounting staff see the maintenance page. Administrators keep full access.
                </p>
                @if ($settings['maintenance_enabled'] && $settings['maintenance_declared_by'])
                    <p class="text-xs text-amber-700/80 mt-1.5">
                        Declared by {{ $settings['maintenance_declared_by'] }}
                        @if ($settings['maintenance_declared_at'])
                            · {{ \Illuminate\Support\Carbon::parse($settings['maintenance_declared_at'])->format('M d, Y · g:i A') }}
                        @endif
                    </p>
                @endif
            </div>

            {{-- Toggle --}}
            <button type="button" role="switch" :aria-checked="enabled ? 'true' : 'false'"
                    @click="enabled = !enabled"
                    class="relative inline-flex h-8 w-14 shrink-0 items-center rounded-full transition-colors duration-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-cpsu-green/40"
                    :class="enabled ? 'bg-amber-500' : 'bg-gray-300'">
                <span class="inline-block h-6 w-6 transform rounded-full bg-white shadow transition-transform duration-300"
                      :class="enabled ? 'translate-x-7' : 'translate-x-1'"></span>
            </button>
        </div>
    </div>

    {{-- Details --}}
    <div class="bg-white rounded-xl border border-cpsu-border shadow-sm p-5 sm:p-6 space-y-4" data-aos="fade-up">
        <h3 class="font-bold text-sm flex items-center gap-2">
            <i data-lucide="megaphone" class="w-4 h-4 text-cpsu-green"></i> What people will see
        </h3>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            <div class="space-y-4">
                <div class="space-y-1">
                    <label class="block text-sm font-medium">Notice</label>
                    <textarea x-model="message" rows="4" maxlength="500"
                              placeholder="e.g. The system is closed for the year-end physical inventory. We will be back shortly."
                              class="w-full rounded-lg border border-cpsu-border px-3 py-2 text-sm outline-none focus:border-cpsu-green focus:ring-2 focus:ring-cpsu-green/20"></textarea>
                    <p class="text-xs text-gray-400"><span x-text="(message || '').length"></span>/500 · leave blank to use the default notice.</p>
                </div>

                <div class="space-y-1">
                    <label class="block text-sm font-medium">Expected back (optional)</label>
                    <div class="relative">
                        <i data-lucide="calendar-clock" class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                        <input x-ref="until" type="text" readonly placeholder="Pick a date and time"
                               class="w-full rounded-lg border border-cpsu-border pl-9 pr-3 py-2 text-sm bg-white outline-none focus:border-cpsu-green cursor-pointer">
                    </div>
                    <button type="button" x-show="until" x-cloak @click="clearUntil()"
                            class="text-xs text-cpsu-danger hover:underline">Clear</button>
                </div>
            </div>

            {{-- Live preview of the maintenance page --}}
            <div class="rounded-xl border border-cpsu-border bg-cpsu-bg/60 p-5 text-center flex flex-col items-center justify-center">
                <div class="h-12 w-12 rounded-2xl bg-white shadow ring-1 ring-cpsu-border flex items-center justify-center mb-3">
                    <i data-lucide="wrench" class="w-6 h-6 text-cpsu-green"></i>
                </div>
                <p class="inline-flex rounded-full bg-cpsu-green/10 text-cpsu-green px-2.5 py-0.5 text-[10px] font-bold tracking-widest uppercase">Error 503</p>
                <p class="font-extrabold mt-2">Under maintenance</p>
                <p class="text-xs text-gray-500 mt-1.5 leading-relaxed"
                   x-text="message || 'The system is temporarily unavailable while we perform scheduled maintenance.'"></p>
                <p class="text-[11px] text-gray-400 mt-3" x-show="until" x-cloak>
                    Expected back <span x-text="until"></span>
                </p>
            </div>
        </div>

        <div class="flex items-center justify-end gap-2 pt-2 border-t border-cpsu-border">
            <x-ui.button variant="primary" icon="save" x-on:click="save()" x-bind:disabled="saving">
                <span x-show="!saving">Save settings</span>
                <span x-show="saving" x-cloak>Saving…</span>
            </x-ui.button>
        </div>
    </div>
</div>

@push('scripts')
<script>
  function systemSettings(cfg) {
    return {
      enabled: cfg.enabled, message: cfg.message || '', until: cfg.until || '', saving: false,
      init() {
        var self = this;
        this.picker = flatpickr(this.$refs.until, {
          enableTime: true, dateFormat: 'Y-m-d H:i', defaultDate: cfg.until || null, minDate: 'today',
          onChange: function (d, str) { self.until = str; },
        });
      },
      clearUntil() { this.until = ''; this.picker.clear(); },
      async save() {
        this.saving = true;
        try {
          var res = await fetch(cfg.url, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({
              maintenance_enabled: this.enabled ? 1 : 0,
              maintenance_message: this.message || null,
              maintenance_until: this.until || null,
            }),
          });
          if (!res.ok) { CPSU.toast('Could not save settings.', 'error'); this.saving = false; return; }
          var j = await res.json();
          CPSU.toast(j.message, j.enabled ? 'warning' : 'success');
          setTimeout(function () { window.location.reload(); }, 900);
        } catch (e) { CPSU.toast('Network error.', 'error'); this.saving = false; }
      },
    };
  }
</script>
@endpush
@endsection
