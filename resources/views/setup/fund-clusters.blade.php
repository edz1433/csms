@extends('layouts.app')

@section('title', 'Fund Clusters')
@section('header', 'Fund Clusters')

@section('content')
<x-setup.scaffold
    resource="fund_clusters"
    singular="Fund Cluster"
    :store-url="route('fund-clusters.store')"
    :update-url="route('fund-clusters.update', '__ID__')"
    :ajax-url="route('fund-clusters.index')"
    :blank="['id' => null, 'code' => '', 'name' => '']"
    :columns="[
        ['data' => 'code', 'title' => 'Code'],
        ['data' => 'name', 'title' => 'Name'],
        ['data' => 'status', 'title' => 'Status', 'orderable' => false, 'searchable' => false, 'className' => 'text-center'],
        ['data' => 'action', 'title' => '', 'orderable' => false, 'searchable' => false, 'className' => 'text-right'],
    ]"
>
    <x-setup.field label="Code" name="code" required placeholder="e.g. 101, MOOE-2026" />
    <x-setup.field label="Name" name="name" required placeholder="e.g. Maintenance & Other Operating Expenses" />
</x-setup.scaffold>
@endsection
