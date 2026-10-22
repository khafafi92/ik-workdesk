<?php

namespace App\Models\Scm;

class Category extends Record
{
    protected $table = 'scm_categories';

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
