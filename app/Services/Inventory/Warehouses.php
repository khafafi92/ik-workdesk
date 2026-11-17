<?php

namespace App\Services\Inventory;

use App\Models\Department;
use App\Models\InventoryWarehouse;
use App\Models\Scm\ProjectNumbering;
use Illuminate\Validation\ValidationException;

class Warehouses
{
    public function legacyAtk(): InventoryWarehouse
    {
        return InventoryWarehouse::where('legacy_atk', true)->firstOrFail();
    }

    public function forContext(string $company, string $kind, ?int $projectId): InventoryWarehouse
    {
        if (! in_array($company, ['KPMOG', 'APCA'], true) || ! in_array($kind, ['HO', 'PROJECT'], true)) {
            throw ValidationException::withMessages(['warehouse' => 'Pilih entitas dan jenis gudang yang sesuai.']);
        }
        $project = $kind === 'PROJECT' ? ProjectNumbering::findOrFail($projectId) : null;
        if ($project && ! $project->is_active) {
            throw ValidationException::withMessages(['warehouse' => 'Project sudah nonaktif.']);
        }

        return InventoryWarehouse::firstOrCreate(['code' => $kind.'-'.$company.($project ? '-'.$project->id : '')],
            ['kind' => $kind, 'company' => $company, 'name' => $project?->name ?? 'Gudang HO', 'project_numbering_id' => $project?->id]);
    }

    public function syncProjects(): void
    {
        foreach (ProjectNumbering::where('is_active', true)->get() as $project) {
            foreach (['KPMOG', 'APCA'] as $company) {
                $this->forContext($company, 'PROJECT', $project->id);
            }
        }
    }

    public function forDepartment(InventoryWarehouse $source, Department $department): InventoryWarehouse
    {
        if ($source->legacy_atk || $source->department_id || ! $source->is_active || ! $department->is_active) {
            throw ValidationException::withMessages(['destination_id' => 'Pilih gudang utama dan departemen yang aktif.']);
        }

        $warehouse = InventoryWarehouse::firstOrCreate(['code' => $source->code.'-DEPT-'.$department->id], [
            'kind' => $source->kind, 'company' => $source->company, 'name' => $department->name,
            'project_numbering_id' => $source->project_numbering_id, 'department_id' => $department->id,
        ]);
        if (! $warehouse->is_active) {
            throw ValidationException::withMessages(['destination_id' => 'Gudang departemen tidak aktif.']);
        }

        return $warehouse;
    }
}
