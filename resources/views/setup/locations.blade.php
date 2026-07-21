@extends('layouts.app')

@section('title', 'Campuses / Offices')
@section('header', 'Campuses / Offices')
@section('subheader', 'Receiving campuses and offices')

@section('content')
<x-setup.scaffold
    resource="locations"
    singular="Location"
    :store-url="route('locations.store')"
    :update-url="route('locations.update', '__ID__')"
    :ajax-url="route('locations.index')"
    :blank="['id' => null, 'type' => '', 'code' => '', 'name' => '']"
    :order="[[0, 'asc']]"
    :columns="[
        ['data' => 'code', 'title' => 'Code'],
        ['data' => 'name', 'title' => 'Name'],
        ['data' => 'type', 'title' => 'Type'],
        ['data' => 'status', 'title' => 'Status', 'orderable' => false, 'searchable' => false, 'className' => 'text-center'],
        ['data' => 'action', 'title' => '', 'orderable' => false, 'searchable' => false, 'className' => 'text-right'],
    ]"
>
    <x-setup.select label="Type" name="type" required
        :options="['campus' => 'Campus', 'office' => 'Office']" placeholder="Select type" />
    <x-setup.field label="Code" name="code" required placeholder="e.g. CPSU-MAIN, SUPPLY-OFC"
        hint="Unique short code; uppercase, hyphens allowed." />
    <x-setup.field label="Name" name="name" required placeholder="e.g. Main Campus, Registrar's Office" />
</x-setup.scaffold>
@endsection
