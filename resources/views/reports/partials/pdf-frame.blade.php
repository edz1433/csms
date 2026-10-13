{{-- Embedded PDF preview, shared by every report tab. Requires an Alpine
     string `url` in the surrounding x-data scope (empty = nothing generated yet).
     Optional slots: $title (panel heading), $emptyText (empty-state message),
     $loading (true = overlay a spinner while Alpine boolean `loading` is set). --}}
<div x-show="url" x-cloak data-aos="fade-up"
     class="relative z-10 bg-white rounded-xl border border-cpsu-border shadow-sm overflow-hidden">
    <div class="px-4 py-3 border-b border-cpsu-border flex items-center gap-2">
        <i data-lucide="file-text" class="w-4 h-4 text-cpsu-green"></i>
        <h3 class="font-bold text-sm">{{ $title ?? 'Report Preview' }}</h3>
    </div>
    <div class="relative">
        <iframe x-ref="frame" :src="url" class="w-full block" style="height:80vh; border:0;"
                @if ($loading ?? false) x-on:load="loading = false" @endif></iframe>
        @if ($loading ?? false)
            <div x-show="loading" x-cloak class="absolute inset-0 bg-white/80 flex flex-col items-center justify-center gap-3 text-sm text-gray-500">
                <div class="w-8 h-8 rounded-full border-4 border-cpsu-border border-t-cpsu-green animate-spin"></div>
                <span>Generating PDF… this can take a while for many items.</span>
            </div>
        @endif
    </div>
</div>

<div x-show="!url" x-cloak class="bg-white rounded-xl border border-cpsu-border shadow-sm p-12 text-center text-gray-400">
    <i data-lucide="file-text" class="w-10 h-10 mx-auto mb-2 opacity-40"></i>
    <p>{{ $emptyText ?? 'Set the filters above, then click Generate PDF to preview the report.' }}</p>
</div>
