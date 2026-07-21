{{-- Wraps create/edit/delete UI. Renders its slot for everyone EXCEPT
     accounting_staff (who are view-only everywhere). Use once around action
     buttons/forms instead of scattering role checks per view. --}}
@unless (auth()->check() && auth()->user()->isAccountingStaff())
    {{ $slot }}
@endunless
