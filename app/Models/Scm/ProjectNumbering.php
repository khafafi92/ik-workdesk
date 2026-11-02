<?php

namespace App\Models\Scm;

class ProjectNumbering extends Record
{
    protected $table = 'scm_project_numbering';

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
