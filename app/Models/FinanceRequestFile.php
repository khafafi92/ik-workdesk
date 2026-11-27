<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinanceRequestFile extends Model
{
    public const KINDS = ['support' => 'Bukti pendukung', 'invoice' => 'Invoice'];

    protected $guarded = ['id'];
}
