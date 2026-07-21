@extends('layouts.app')

@section('title', 'User Management')
@section('header', 'User Management')
@section('subheader', 'Accounts, roles and page-level access')

@section('content')
@php $labels = config('access.labels'); @endphp

<div x-data="userForm({
        storeUrl: @js(route('users.store')),
        updateUrl: @js(route('users.update', '__ID__')),
        labels: {{ Illuminate\Support\Js::from($labels) }}
     })">

    <div class="flex items-center justify-between gap-3 mb-4">
        <p class="text-sm text-gray-500 hidden sm:block">Administrators have full access; staff access is controlled per page.</p>
        <x-ui.button variant="primary" icon="user-plus" onclick="window.openCreate('users')">New User</x-ui.button>
    </div>

    <div class="bg-white rounded-xl border border-cpsu-border shadow-sm p-4 sm:p-5" data-aos="fade-up">
        <table id="users-table" class="w-full text-sm"></table>
    </div>

    {{-- Create / Edit modal --}}
    <x-ui.modal name="users-form" title="New User" maxWidth="max-w-2xl">
        <form @submit.prevent="submit()" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="space-y-1">
                    <label class="block text-sm font-medium">Full Name <span class="text-cpsu-danger">*</span></label>
                    <input x-model="form.name" type="text" class="w-full rounded-lg border px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-cpsu-green/20"
                           :class="err('name') ? 'border-cpsu-danger' : 'border-cpsu-border focus:border-cpsu-green'">
                    <p x-show="err('name')" x-cloak x-text="err('name')" class="text-xs text-cpsu-danger"></p>
                </div>
                <div class="space-y-1">
                    <label class="block text-sm font-medium">Email <span class="text-cpsu-danger">*</span></label>
                    <input x-model="form.email" type="email" class="w-full rounded-lg border px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-cpsu-green/20"
                           :class="err('email') ? 'border-cpsu-danger' : 'border-cpsu-border focus:border-cpsu-green'">
                    <p x-show="err('email')" x-cloak x-text="err('email')" class="text-xs text-cpsu-danger"></p>
                </div>
                <div class="space-y-1">
                    <label class="block text-sm font-medium">
                        Password <span x-show="mode==='create'" class="text-cpsu-danger">*</span>
                        <span x-show="mode==='edit'" class="text-xs text-gray-400 font-normal">(leave blank to keep)</span>
                    </label>
                    <input x-model="form.password" type="password" autocomplete="new-password"
                           class="w-full rounded-lg border px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-cpsu-green/20"
                           :class="err('password') ? 'border-cpsu-danger' : 'border-cpsu-border focus:border-cpsu-green'">
                    <p x-show="err('password')" x-cloak x-text="err('password')" class="text-xs text-cpsu-danger"></p>
                </div>
                <div class="space-y-1">
                    <label class="block text-sm font-medium">Role <span class="text-cpsu-danger">*</span></label>
                    <select x-model="form.role" class="w-full rounded-lg border border-cpsu-border px-3 py-2 text-sm bg-white focus:border-cpsu-green outline-none focus:ring-2 focus:ring-cpsu-green/20">
                        <option value="administrator">Administrator</option>
                        <option value="supply_staff">Supply Staff</option>
                        <option value="accounting_staff">Accounting Staff</option>
                    </select>
                </div>
            </div>

            {{-- Access note per role --}}
            <p x-show="form.role === 'accounting_staff'" x-cloak class="text-xs rounded-lg bg-cpsu-gold/10 text-cpsu-gold-dark px-3 py-2 flex items-start gap-1.5">
                <i data-lucide="info" class="w-3.5 h-3.5 mt-0.5 shrink-0"></i>
                Accounting Staff always has view-only access, plus the ability to mark released items as Paid/Unpaid — regardless of which pages are checked.
            </p>

            {{-- Access checkboxes (hidden for administrators) --}}
            <div x-show="form.role !== 'administrator'" x-cloak class="space-y-2">
                <label class="block text-sm font-medium">Page Access</label>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 rounded-lg border border-cpsu-border p-3 bg-cpsu-bg/40">
                    @foreach ($labels as $key => $label)
                        <label class="flex items-center gap-2 text-sm select-none">
                            <input type="checkbox" value="{{ $key }}" x-model="form.access"
                                   class="rounded border-cpsu-border text-cpsu-green focus:ring-cpsu-green/30">
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>
            <p x-show="form.role === 'administrator'" x-cloak class="text-xs text-gray-400 italic">
                Administrators automatically have full access to every page.
            </p>

            <label class="flex items-center gap-2 text-sm select-none">
                <input type="checkbox" x-model="form.is_active" class="rounded border-cpsu-border text-cpsu-green focus:ring-cpsu-green/30">
                Active account (can sign in)
            </label>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-cpsu-border">
                <x-ui.button variant="ghost" x-on:click="$dispatch('close-modal', 'users-form')">Cancel</x-ui.button>
                <x-ui.button variant="primary" type="submit" x-bind:disabled="submitting">
                    <span x-show="!submitting" x-text="mode === 'create' ? 'Create User' : 'Save changes'"></span>
                    <span x-show="submitting" x-cloak>Saving…</span>
                </x-ui.button>
            </div>
        </form>
    </x-ui.modal>
</div>

@push('scripts')
<script>
  var USERS_BLANK = { id: null, name: '', email: '', password: '', role: 'supply_staff', access: [], is_active: true };

  function userForm(cfg) {
    return {
      mode: 'create', modalTitle: '', submitting: false, errors: {},
      form: JSON.parse(JSON.stringify(USERS_BLANK)),
      init() {
        var self = this;
        window.addEventListener('resource-create', function (e) { if (e.detail.resource === 'users') self.startCreate(); });
        window.addEventListener('resource-edit', function (e) { if (e.detail.resource === 'users') self.startEdit(e.detail.data); });
      },
      startCreate() { this.mode = 'create'; this.form = JSON.parse(JSON.stringify(USERS_BLANK)); this.errors = {}; this.modalTitle = 'New User'; },
      startEdit(data) {
        this.mode = 'edit'; this.errors = {}; this.modalTitle = 'Edit User';
        this.form = Object.assign(JSON.parse(JSON.stringify(USERS_BLANK)), {
          id: data.id, name: data.name, email: data.email, password: '',
          role: data.role, access: Array.isArray(data.access) ? data.access : [],
          is_active: !!data.is_active,
        });
      },
      err(field) { return this.errors[field] ? this.errors[field][0] : ''; },
      async submit() {
        this.submitting = true; this.errors = {};
        var url = this.mode === 'create' ? cfg.storeUrl : cfg.updateUrl.replace('__ID__', this.form.id);
        var payload = Object.assign({}, this.form);
        if (this.mode === 'edit') payload._method = 'PUT';
        try {
          var res = await fetch(url, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(payload),
          });
          if (res.status === 422) { var j = await res.json(); this.errors = j.errors || {}; this.submitting = false; return; }
          if (!res.ok) { CPSU.toast('Something went wrong', 'error'); this.submitting = false; return; }
          window.dispatchEvent(new CustomEvent('close-modal', { detail: 'users-form' }));
          window.reloadTable['users'] && window.reloadTable['users']();
          CPSU.toast('User ' + (this.mode === 'create' ? 'created' : 'updated'), 'success');
        } catch (e) { CPSU.toast('Network error', 'error'); }
        this.submitting = false;
      },
    };
  }

  window.resetPassword = function (url, name) {
    CPSU.confirm({
      icon: 'question', title: 'Reset password?',
      text: 'A new temporary password will be generated for ' + name + '.',
      confirmText: 'Yes, reset',
    }).then(function (r) {
      if (!r.isConfirmed) return;
      $.ajax({ url: url, method: 'PATCH' })
        .done(function (d) {
          Swal.fire({
            icon: 'success', title: 'Password reset',
            html: 'Temporary password for <b>' + d.name + '</b>:<br><code style="font-size:1.1rem;background:#F7F8F5;padding:4px 10px;border-radius:6px;display:inline-block;margin-top:8px;">' + d.temp_password + '</code><br><span style="font-size:.8rem;color:#888">Share it securely; ask them to change it after login.</span>',
            confirmButtonColor: '#0B6E2E',
          });
        })
        .fail(function () { CPSU.toast('Could not reset password', 'error'); });
    });
  };

  document.addEventListener('DOMContentLoaded', function () {
    var dt = CPSU.dataTable('#users-table', @js(route('users.index')), [
      { data: 'name', title: 'Name' },
      { data: 'email', title: 'Email' },
      { data: 'role', title: 'Role' },
      { data: 'access', title: 'Access', orderable: false, searchable: false },
      { data: 'status', title: 'Status', orderable: false, searchable: false, className: 'text-center' },
      { data: 'action', title: '', orderable: false, searchable: false, className: 'text-right' },
    ], { order: [[0, 'asc']] });
    window.reloadTable['users'] = function () { dt.ajax.reload(null, false); };
  });
</script>
@endpush
@endsection
