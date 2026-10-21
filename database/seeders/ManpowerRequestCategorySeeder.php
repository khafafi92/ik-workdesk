<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\TicketCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ManpowerRequestCategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach (Department::query()->get()->filter(fn (Department $department) => $department->isHumanResources()) as $department) {
            DB::transaction(function () use ($department): void {
                $categories = TicketCategory::query()->where('handler_department_id', $department->id)
                    ->whereIn(DB::raw('lower(trim(name))'), ['kebutuhan manpower', 'kebutuhan man power', 'manpower plant', 'man power plant'])
                    ->lockForUpdate()->get();
                $category = $categories->first(fn (TicketCategory $category): bool => strtolower(trim($category->name)) === 'kebutuhan manpower')
                    ?? $categories->firstWhere('code', 'HR-MP') ?? $categories->first();
                $category ??= new TicketCategory(['handler_department_id' => $department->id,
                    'code' => 'HR-MP', 'workflow_type' => 'single', 'is_active' => true]);
                $category->name = 'Kebutuhan Manpower';
                $category->requires_manpower = true;
                $category->save();

                // Keep old category IDs so existing requests retain their history.
                foreach ($categories->except($category->id) as $duplicate) {
                    $duplicate->update(['is_active' => false]);
                }
            });
        }
    }
}
