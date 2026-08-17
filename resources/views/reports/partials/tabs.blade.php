{{-- Reports hub tab bar. Pass $active as one of the tab keys below. --}}
@php
    $tabs = [
        ['key' => 'ris',            'route' => 'reports.index',          'label' => 'Requisition & Issue Slip', 'icon' => 'file-text'],
        ['key' => 'stock-card',     'route' => 'reports.stock-card',     'label' => 'Stock Card',       'icon' => 'scroll-text'],
        ['key' => 'stock-status',   'route' => 'reports.stock-status',   'label' => 'Stock Status',     'icon' => 'boxes'],
        ['key' => 'account-summary', 'route' => 'reports.account-summary', 'label' => 'Inventory Summary', 'icon' => 'calendar-range'],
        ['key' => 'payment-status', 'route' => 'reports.payment-status', 'label' => 'Payment Status',   'icon' => 'wallet'],
        ['key' => 'iar',            'route' => 'reports.iar',            'label' => 'IAR',              'icon' => 'clipboard-check'],
        ['key' => 'rsmi',           'route' => 'reports.rsmi',           'label' => 'RSMI (Issued)',    'icon' => 'clipboard-list'],
        ['key' => 'ledger',         'route' => 'reports.ledger',         'label' => 'Supply Ledger',    'icon' => 'notebook-text'],
    ];
    $active = $active ?? 'summary';
@endphp
<div class="mb-5 border-b border-cpsu-border">
    <nav class="flex flex-wrap gap-x-1 -mb-px overflow-x-auto">
        @foreach ($tabs as $t)
            @php $isActive = $active === $t['key']; @endphp
            <a href="{{ route($t['route']) }}"
               class="inline-flex items-center gap-2 whitespace-nowrap px-4 py-2.5 text-sm font-medium border-b-2 transition
                      {{ $isActive
                          ? 'border-cpsu-green text-cpsu-green'
                          : 'border-transparent text-gray-500 hover:text-cpsu-green hover:border-cpsu-green/40' }}">
                <i data-lucide="{{ $t['icon'] }}" class="w-4 h-4"></i>
                {{ $t['label'] }}
            </a>
        @endforeach
    </nav>
</div>
