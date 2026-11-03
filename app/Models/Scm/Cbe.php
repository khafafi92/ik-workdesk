<?php

namespace App\Models\Scm;

class Cbe extends Record
{
    protected $table = 'scm_cbes';

    public function approvals()
    {
        return $this->hasMany(CbeApproval::class)->orderBy('position');
    }

    public function request()
    {
        return $this->belongsTo(Procurement::class, 'request_id');
    }

    public function documentRequest(): array
    {
        $request = $this->snapshot['request'] ?? [];
        if (! array_key_exists('project_name', $request)) {
            $request['project_name'] = $this->request?->cbeProjectName() ?? $request['location'] ?? '';
        }

        return $request;
    }

    public function documentComment(): string
    {
        return $this->status === 'final'
            ? ($this->snapshot['comment'] ?? $this->snapshot['request']['notes'] ?? '')
            : ($this->comment ?? $this->request?->notes ?? '');
    }

    protected function casts(): array
    {
        return ['quotation_ids' => 'array', 'commercial_terms' => 'array', 'vat_percent' => 'integer', 'snapshot' => 'array', 'date' => 'date', 'finalized_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $record): void {
            if ($record->getRawOriginal('status') === 'final') {
                throw new \LogicException('CBE final terkunci.');
            }
        });
        static::deleting(fn () => throw new \LogicException('CBE historis tidak dapat dihapus.'));
    }
}
