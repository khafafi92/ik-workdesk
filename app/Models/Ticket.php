<?php

namespace App\Models;

use App\Services\DocumentNumberGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Validation\ValidationException;

class Ticket extends Model
{
    protected $fillable = [
        'ticket_no',
        'employee_id',
        'requester_department_id',
        'handler_department_id',
        'ticket_category_id',
        'permit_company_id',
        'permit_kbli_id',
        'permit_kbli_unavailable',
        'subject',
        'description',
        'attachments',
        'priority',
        'status',
        'workflow_type',
        'assigned_to',
        'reported_at',
        'due_at',
        'resolved_at',
        'resolution_notes',
    ];

    protected $casts = [
        'reported_at' => 'datetime',
        'due_at' => 'datetime',
        'resolved_at' => 'datetime',
        'attachments' => 'array',
        'permit_kbli_unavailable' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (Ticket $ticket): void {
            $requiresPermit = $ticket->ticket_category_id
                && TicketCategory::query()
                    ->whereKey($ticket->ticket_category_id)
                    ->where('requires_permit', true)
                    ->exists();

            if (! $requiresPermit) {
                $ticket->permit_company_id = null;
                $ticket->permit_kbli_id = null;
                $ticket->permit_kbli_unavailable = false;

                if ($ticket->status === 'discussion') {
                    $ticket->status = 'open';
                }

                return;
            }

            if (! $ticket->permit_company_id) {
                throw ValidationException::withMessages([
                    'permit_company_id' => 'Permit Company wajib dipilih untuk kategori ini.',
                ]);
            }

            if ($ticket->permit_kbli_unavailable) {
                $ticket->permit_kbli_id = null;
                $ticket->status = 'discussion';

                return;
            }

            if (! $ticket->permit_kbli_id) {
                throw ValidationException::withMessages([
                    'permit_kbli_id' => 'Pilih KBLI atau tandai bahwa KBLI belum tersedia.',
                ]);
            }

            $validKbli = PermitKbli::query()
                ->whereKey($ticket->permit_kbli_id)
                ->where('permit_company_id', $ticket->permit_company_id)
                ->exists();

            if (! $validKbli) {
                throw ValidationException::withMessages([
                    'permit_kbli_id' => 'KBLI tidak sesuai dengan Permit Company yang dipilih.',
                ]);
            }

            if ($ticket->status === 'discussion') {
                $ticket->status = 'open';
            }
        });
    }

    public static function generateRequestNo(): string
    {
        $prefix = 'R-'.now()->format('d/m/y').'-';

        return app(DocumentNumberGenerator::class)->next($prefix);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function requesterDepartment()
    {
        return $this->belongsTo(Department::class, 'requester_department_id');
    }

    public function handlerDepartment()
    {
        return $this->belongsTo(Department::class, 'handler_department_id');
    }

    public function category()
    {
        return $this->belongsTo(TicketCategory::class, 'ticket_category_id');
    }

    public function permitCompany()
    {
        return $this->belongsTo(PermitCompany::class);
    }

    public function permitKbli()
    {
        return $this->belongsTo(PermitKbli::class);
    }

    public function requiresPermitDiscussion(): bool
    {
        return $this->permit_company_id !== null
            && $this->permit_kbli_unavailable === true;
    }

    public function workTasks()
    {
        return $this->hasMany(WorkTask::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TicketAssignment::class)
            ->orderBy('sort_order');
    }

    public function reviewerDepartments(): BelongsToMany
    {
        return $this->belongsToMany(
            Department::class,
            'ticket_assignments'
        )
            ->withPivot([
                'work_task_id',
                'is_required',
                'sort_order',
                'notes',
            ])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    public function findings(): HasManyThrough
    {
        return $this->hasManyThrough(
            WorkTaskFinding::class,
            WorkTask::class,
            'ticket_id',
            'work_task_id',
            'id',
            'id'
        );
    }

    public function syncCollaborativeStatus(): void
    {
        if ($this->workflow_type !== 'collaborative') {
            return;
        }

        $requiredTasks = $this->assignments()
            ->where('is_required', true)
            ->with('workTask')
            ->get()
            ->pluck('workTask')
            ->filter()
            ->values();

        if ($requiredTasks->isEmpty()) {
            return;
        }

        $allTasksDone = $requiredTasks->every(
            fn (WorkTask $task): bool => $task->status === 'done'
        );

        $allTasksCancelled = $requiredTasks->every(
            fn (WorkTask $task): bool => in_array(
                $task->status,
                ['cancel', 'cancelled'],
                true
            )
        );

        $hasHoldTask = $requiredTasks->contains(
            fn (WorkTask $task): bool => $task->status === 'hold'
        );

        $hasStartedTask = $requiredTasks->contains(
            fn (WorkTask $task): bool => in_array(
                $task->status,
                ['in_progress', 'done', 'cancel', 'cancelled'],
                true
            )
        );

        /*
        |--------------------------------------------------------------------------
        | FINAL RULE
        |--------------------------------------------------------------------------
        | Ticket resolved apabila seluruh required Work Log sudah Done.
        */
        if ($allTasksDone) {
            $this->update([
                'status' => 'resolved',
                'resolved_at' => now(),
                'resolution_notes' => 'All required work logs have been completed.',
            ]);

            return;
        }

        if ($allTasksCancelled) {
            $this->updateQuietly([
                'status' => 'cancel',
                'resolved_at' => null,
                'resolution_notes' => null,
            ]);

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Masih menunggu jawaban requester
        |--------------------------------------------------------------------------
        */
        if ($hasHoldTask) {
            $this->updateQuietly([
                'status' => 'waiting_user',
                'resolved_at' => null,
                'resolution_notes' => null,
            ]);

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Minimal satu department sudah memulai pekerjaan.
        |--------------------------------------------------------------------------
        */
        if ($hasStartedTask) {
            $this->updateQuietly([
                'status' => 'in_progress',
                'resolved_at' => null,
                'resolution_notes' => null,
            ]);

            return;
        }

        $this->updateQuietly([
            'status' => 'open',
            'resolved_at' => null,
            'resolution_notes' => null,
        ]);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class);
    }

    public function usesDueDiligenceFindings(): bool
    {
        if ($this->workflow_type !== 'collaborative') {
            return false;
        }

        $categoryCode = strtolower((string) $this->category?->code);
        $categoryName = strtolower((string) $this->category?->name);

        $isDueDiligenceCategory = $categoryCode === 'dd'
            || str_contains($categoryName, 'due diligence');

        if (! $isDueDiligenceCategory) {
            return false;
        }

        return $this->assignments()->exists();
    }
}
