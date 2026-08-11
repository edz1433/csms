@props(['user'])

<div class="flex items-center justify-end gap-1">
    {{-- Js::from, not @js: Blade does not compile directives inside a component
         tag's attribute value, so @js would reach the browser verbatim and break
         the handler. --}}
    <x-ui.icon-btn icon="key-round" variant="default" title="Reset password"
        onclick="resetPassword('{{ route('users.reset-password', $user) }}', {{ Illuminate\Support\Js::from($user->name) }})" />
    <x-ui.icon-btn icon="pencil" variant="edit" title="Edit"
        onclick="window.openEdit('users', {{ Illuminate\Support\Js::from($user->only(['id','name','email','role','access','is_active'])) }})" />
    @if ($user->id !== auth()->id())
        <x-ui.icon-btn icon="trash-2" variant="danger" title="Delete"
            onclick="CPSU.deleteResource('{{ route('users.destroy', $user) }}', 'user', 'users')" />
    @endif
</div>
