@props(['name', 'title' => '', 'maxWidth' => 'max-w-lg'])

{{-- Alpine modal. Open via: $dispatch('open-modal', 'name') ; close via 'close-modal'. --}}
<div
    x-data="{ show: false }"
    x-show="show"
    x-cloak
    @open-modal.window="if ($event.detail === '{{ $name }}') show = true"
    @close-modal.window="if ($event.detail === '{{ $name }}' || $event.detail === undefined) show = false"
    @keydown.escape.window="show = false"
    class="fixed inset-0 z-[60] overflow-y-auto"
    aria-modal="true" role="dialog"
>
    {{-- backdrop --}}
    <div x-show="show" x-transition:enter="transition-opacity ease-out duration-200"
         x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-in duration-150"
         x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         @click="show = false" class="fixed inset-0 bg-black/40"></div>

    {{-- panel --}}
    <div class="flex min-h-full items-center justify-center p-4">
        <div x-show="show"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-2 scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 scale-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 translate-y-0 scale-100"
             x-transition:leave-end="opacity-0 translate-y-2 scale-95"
             class="relative w-full {{ $maxWidth }} bg-white rounded-2xl shadow-xl border border-cpsu-border">
            <div class="flex items-center justify-between px-5 py-4 border-b border-cpsu-border">
                <h3 class="font-bold text-cpsu-black"
                    x-text="(typeof modalTitle !== 'undefined' && modalTitle) ? modalTitle : @js($title)">{{ $title }}</h3>
                <button type="button" @click="show = false" class="p-1.5 rounded-lg hover:bg-cpsu-bg text-gray-400 hover:text-cpsu-black transition">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>
            <div class="p-5">
                {{ $slot }}
            </div>
        </div>
    </div>
</div>
