@props(['user'])

<div class="flex items-center justify-end gap-1">
    <x-ui.icon-btn icon="key-round" variant="default" title="Reset password"
        onclick="resetPassword('{{ route('users.reset-password', $user) }}', @js($user->name))" />
    <x-ui.icon-btn icon="pencil" variant="edit" title="Edit"
        onclick="window.openEdit('users', {{ Illuminate\Support\Js::from($user->only(['id','name','email','role','access','is_active'])) }})" />
    @if ($user->id !== auth()->id())
        <x-ui.icon-btn icon="trash-2" variant="danger" title="Delete"
            onclick="CPSU.deleteResource('{{ route('users.destroy', $user) }}', 'user', 'users')" />
    @endif
</div>
