@extends('layouts.app')

@section('title', 'Suppliers')
@section('header', 'Suppliers')

@section('content')
<x-setup.scaffold
    resource="suppliers"
    singular="Supplier"
    :store-url="route('suppliers.store')"
    :update-url="route('suppliers.update', '__ID__')"
    :ajax-url="route('suppliers.index')"
    :blank="['id' => null, 'name' => '', 'contact_person' => '', 'contact_number' => '', 'email' => '', 'address' => '']"
    :columns="[
        ['data' => 'name', 'title' => 'Supplier'],
        ['data' => 'contact_person', 'title' => 'Contact Person'],
        ['data' => 'contact_number', 'title' => 'Contact No.'],
        ['data' => 'status', 'title' => 'Status', 'orderable' => false, 'searchable' => false, 'className' => 'text-center'],
        ['data' => 'action', 'title' => '', 'orderable' => false, 'searchable' => false, 'className' => 'text-right'],
    ]"
>
    <x-setup.field label="Supplier Name" name="name" required placeholder="Company / supplier name" />
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <x-setup.field label="Contact Person" name="contact_person" placeholder="Full name" />
        <x-setup.field label="Contact Number" name="contact_number" placeholder="09xx-xxx-xxxx" />
    </div>
    <x-setup.field label="Email" name="email" type="email" placeholder="sales@example.ph" />
    <x-setup.field label="Address" name="address" placeholder="City / full address" />
</x-setup.scaffold>
@endsection
