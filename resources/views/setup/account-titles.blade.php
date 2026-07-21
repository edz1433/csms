@extends('layouts.app')

@section('title', 'Account Titles')
@section('header', 'Account Titles')
@section('subheader', 'Revised Chart of Accounts codes used at releasing')

@section('content')
<x-setup.scaffold
    resource="account_titles"
    singular="Account Title"
    :store-url="route('account-titles.store')"
    :update-url="route('account-titles.update', '__ID__')"
    :ajax-url="route('account-titles.index')"
    :blank="['id' => null, 'rca_code' => '', 'name' => '']"
    :columns="[
        ['data' => 'rca_code', 'title' => 'RCA Code'],
        ['data' => 'name', 'title' => 'Account Title'],
        ['data' => 'status', 'title' => 'Status', 'orderable' => false, 'searchable' => false, 'className' => 'text-center'],
        ['data' => 'action', 'title' => '', 'orderable' => false, 'searchable' => false, 'className' => 'text-right'],
    ]"
>
    <x-setup.field label="RCA Code" name="rca_code" required placeholder="e.g. 5-02-03-010"
        hint="Revised Chart of Accounts code. Snapshotted onto releases at release time." />
    <x-setup.field label="Account Title" name="name" required placeholder="e.g. Office Supplies Expense" />
</x-setup.scaffold>
@endsection
