@extends('layouts.app')

@section('title', 'Units')
@section('header', 'Units of Measure')

@section('content')
<x-setup.scaffold
    resource="units"
    singular="Unit"
    :store-url="route('units.store')"
    :update-url="route('units.update', '__ID__')"
    :ajax-url="route('units.index')"
    :blank="['id' => null, 'name' => '', 'abbreviation' => '']"
    :columns="[
        ['data' => 'name', 'title' => 'Name'],
        ['data' => 'abbreviation', 'title' => 'Abbreviation'],
        ['data' => 'action', 'title' => '', 'orderable' => false, 'searchable' => false, 'className' => 'text-right'],
    ]"
>
    <x-setup.field label="Name" name="name" required placeholder="e.g. Pieces, Box, Ream" />
    <x-setup.field label="Abbreviation" name="abbreviation" required placeholder="e.g. pcs, box, ream" />
</x-setup.scaffold>
@endsection
