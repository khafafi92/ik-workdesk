<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TravelFile extends Model
{
    protected $guarded = ['id'];

    public function document()
    {
        return $this->belongsTo(TravelDocument::class, 'travel_document_id');
    }
}
