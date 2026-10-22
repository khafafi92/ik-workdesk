<?php

namespace App\Models\Scm;

use Illuminate\Database\Eloquent\Model;

abstract class Record extends Model
{
    protected $guarded = ['id'];
}
