<?php

namespace App\Models;

use App\Models\Scm\ProjectNumbering;
use Illuminate\Database\Eloquent\Model;

class TravelDocument extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['data' => 'array', 'employee_snapshot' => 'array', 'prepared_signature' => 'array',
            'issued_on' => 'date', 'applied_on' => 'date', 'submitted_at' => 'datetime', 'approved_at' => 'datetime',
            'total_minor' => 'integer', 'advance_one_minor' => 'integer', 'advance_two_minor' => 'integer',
            'balance_minor' => 'integer', 'version' => 'integer', 'approval_round' => 'integer'];
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function spd()
    {
        return $this->belongsTo(self::class, 'spd_id');
    }

    public function lpj()
    {
        return $this->hasOne(self::class, 'spd_id');
    }

    public function projectNumbering()
    {
        return $this->belongsTo(ProjectNumbering::class, 'project_numbering_id');
    }

    public function approvals()
    {
        return $this->hasMany(TravelApproval::class)->orderBy('round')->orderBy('step');
    }

    public function files()
    {
        return $this->hasMany(TravelFile::class);
    }

    public function events()
    {
        return $this->hasMany(TravelEvent::class)->orderByDesc('id');
    }

    public function currentApprovals()
    {
        return $this->approvals()->where('round', $this->approval_round);
    }

    public function statusLabel(): string
    {
        return ['draft' => 'Draft', 'submitted' => 'Menunggu persetujuan', 'returned' => 'Dikembalikan', 'approved' => 'Disetujui'][$this->status] ?? $this->status;
    }
}
