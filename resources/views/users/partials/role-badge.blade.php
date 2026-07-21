@props(['role'])
@php
    $map = [
        'administrator' => ['Administrator', 'green'],
        'supply_staff' => ['Supply Staff', 'blue'],
        'accounting_staff' => ['Accounting Staff', 'gold'],
    ][$role] ?? [ucfirst($role), 'gray'];
@endphp
<x-ui.badge :color="$map[1]">{{ $map[0] }}</x-ui.badge>
