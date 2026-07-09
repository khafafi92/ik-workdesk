@extends('layouts.admin')

@section('title', 'Departments')
@section('page-title', 'Departments')
@section('page-description', 'Master data department untuk contoh CRUD boilerplate.')

@section('content')
@if (session('success'))
{{-- <div class="mb-5 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
    {{ session('success') }}
</div> --}}
<x-ui.alert type="success" class="mb-5">
    {{ session('success') }}
</x-ui.alert>

@endif

<div class="bg-white rounded-2xl border border-slate-200 shadow-sm">
    <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
        <div>
            <h2 class="text-lg font-bold text-slate-900">Department List</h2>
            <p class="text-sm text-slate-500">Data department aktif dan tidak aktif.</p>
        </div>


        <x-ui.button :href="route('admin.departments.create')">
            + Add Department
        </x-ui.button>

        {{-- <a href="{{ route('admin.departments.create') }}"
            class="px-4 py-2 rounded-xl bg-slate-950 text-white text-sm font-semibold hover:bg-slate-800">
            + Add Department
        </a> --}}
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">Code</th>
                    <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500">Status
                    </th>
                    <th class="px-6 py-3 text-right text-xs font-bold uppercase tracking-wider text-slate-500">Action
                    </th>
                </tr>
            </thead>

            <tbody class="divide-y divide-slate-200 bg-white">
                @forelse ($departments as $department)
                <tr>
                    <td class="px-6 py-4 text-sm font-semibold text-slate-900">
                        {{ $department->code }}
                    </td>

                    <td class="px-6 py-4 text-sm text-slate-600">
                        {{ $department->name }}
                    </td>

                    <td class="px-6 py-4 text-sm">
                        {{-- @if ($department->is_active)
                        <span
                            class="inline-flex rounded-full bg-green-50 px-3 py-1 text-xs font-semibold text-green-700">
                            Active
                        </span>
                        @else
                        <span
                            class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                            Inactive
                        </span>
                        @endif --}}

                        @if ($department->is_active)
                        <x-ui.badge type="success">Active</x-ui.badge>
                        @else
                        <x-ui.badge type="danger">Inactive</x-ui.badge>
                        @endif

                    </td>

                    <td class="px-6 py-4 text-right text-sm">
                        <div class="flex justify-end gap-2">
                            <a href="{{ route('admin.departments.edit', $department) }}"
                                class="px-3 py-2 rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-50">
                                Edit
                            </a>

                            <form method="POST" action="{{ route('admin.departments.destroy', $department) }}"
                                onsubmit="return confirm('Hapus department ini?')">
                                @csrf
                                @method('DELETE')

                                <button type="submit"
                                    class="px-3 py-2 rounded-lg bg-red-600 text-white hover:bg-red-700">
                                    Delete
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="px-6 py-10 text-center text-sm text-slate-500">
                        Belum ada data department.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="px-6 py-4 border-t border-slate-200">
        {{ $departments->links() }}
    </div>
</div>
@endsection