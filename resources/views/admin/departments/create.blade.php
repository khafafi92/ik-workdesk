@extends('layouts.admin')

@section('title', 'Create Department')
@section('page-title', 'Create Department')
@section('page-description', 'Tambah data department baru.')

@section('content')
<x-ui.card class="max-w-2xl">
    @include('admin.departments._form', [
    'department' => null,
    'action' => route('admin.departments.store'),
    'method' => 'POST',
    ])
</x-ui.card>
@endsection