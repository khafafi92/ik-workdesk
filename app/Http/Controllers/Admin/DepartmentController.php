<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    public function index()
    {
        $departments = Department::query()
            ->latest()
            ->paginate(10);

        return view('admin.departments.index', compact('departments'));
    }

    public function create()
    {
        return view('admin.departments.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:departments,code'],
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        Department::create($validated);

        return redirect()
            ->route('admin.departments.index')
            ->with('success', 'Department berhasil dibuat.');
    }

    public function edit(Department $department)
    {
        return view('admin.departments.edit', compact('department'));
    }

    public function update(Request $request, Department $department)
    {
        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('departments', 'code')->ignore($department->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        $department->update($validated);

        return redirect()
            ->route('admin.departments.index')
            ->with('success', 'Department berhasil diupdate.');
    }

    public function destroy(Department $department)
    {
        $department->delete();

        return redirect()
            ->route('admin.departments.index')
            ->with('success', 'Department berhasil dihapus.');
    }
}