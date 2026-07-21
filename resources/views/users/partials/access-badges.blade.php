@props(['user'])

@if ($user->isAdministrator())
    <span class="text-xs text-gray-400 italic">Full access</span>
@elseif (empty($user->access))
    <span class="text-xs text-gray-300">—</span>
@else
    @php $labels = config('access.labels'); $keys = $user->access ?? []; @endphp
    <div class="flex flex-wrap gap-1">
        @foreach (array_slice($keys, 0, 4) as $key)
            <span class="inline-flex px-2 py-0.5 rounded text-[10px] font-medium bg-cpsu-bg text-gray-600 border border-cpsu-border">{{ $labels[$key] ?? $key }}</span>
        @endforeach
        @if (count($keys) > 4)
            <span class="inline-flex px-2 py-0.5 rounded text-[10px] font-medium bg-cpsu-green/10 text-cpsu-green">+{{ count($keys) - 4 }}</span>
        @endif
    </div>
@endif
