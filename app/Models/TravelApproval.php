<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TravelApproval extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['signature' => 'array', 'decided_at' => 'datetime', 'step' => 'integer', 'round' => 'integer'];
    }

    public function document()
    {
        return $this->belongsTo(TravelDocument::class, 'travel_document_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
