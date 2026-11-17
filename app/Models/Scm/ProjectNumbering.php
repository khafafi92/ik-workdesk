<?php

namespace App\Models\Scm;

use App\Services\Inventory\InventoryCatalogue;
use App\Services\Inventory\Warehouses;

class ProjectNumbering extends Record
{
    protected $table = 'scm_project_numbering';

    protected static function booted(): void
    {
        static::saved(function (self $project): void {
            if ($project->is_active && InventoryCatalogue::installed()) {
                foreach (['KPMOG', 'APCA'] as $company) {
                    app(Warehouses::class)->forContext($company, 'PROJECT', $project->id);
                }
            }
        });
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
