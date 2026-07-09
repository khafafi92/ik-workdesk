<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskCategory extends Model
{
    protected $fillable = [
        'department_id',
        'name',
        'code',
        'is_active',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }
}
