@extends('layouts.admin')

@section('title', 'Edit Department')
@section('page-title', 'Edit Department')
@section('page-description', 'Update data department.')

@section('content')
<x-ui.card class="max-w-2xl">
    <x-admin.departments._form :department="$department" :action="route('admin.departments.update', $department)"
        method="PUT" />
</x-ui.card>
@endsection