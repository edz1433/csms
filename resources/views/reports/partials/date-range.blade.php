{{-- Flatpickr date-range control, styled like the Supply Ledger picker.
     Renders a single readonly field bound to hidden `from`/`to` inputs so a
     plain GET form submits the selected range. Include inside a <form>.
     Params: $from, $to (Y-m-d strings). --}}
<div x-data="reportRange({ from: @js($from), to: @js($to) })" class="min-w-[240px]">
    <label class="block text-xs font-medium text-gray-500 mb-1">Date Range</label>
    <div class="relative">
        <i data-lucide="calendar" class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"></i>
        <input x-ref="display" type="text" readonly placeholder="Select date range"
               class="w-full rounded-lg border border-cpsu-border pl-9 pr-3 py-2 text-sm bg-white focus:border-cpsu-green outline-none cursor-pointer">
    </div>
    <input type="hidden" name="from" :value="from">
    <input type="hidden" name="to" :value="to">
</div>

@once
    @push('scripts')
    <script>
      function reportRange(cfg) {
        return {
          from: cfg.from, to: cfg.to,
          init() {
            var self = this;
            flatpickr(this.$refs.display, {
              mode: 'range', dateFormat: 'Y-m-d',
              defaultDate: [cfg.from, cfg.to],
              onChange: function (dates) {
                if (dates.length === 2) {
                  self.from = self.fmt(dates[0]);
                  self.to = self.fmt(dates[1]);
                }
              },
            });
          },
          fmt(d) {
            return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
          },
        };
      }
    </script>
    @endpush
@endonce
