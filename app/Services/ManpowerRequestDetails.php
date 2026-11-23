<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketCategory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ManpowerRequestDetails
{
    public const REASONS = ['additional' => 'Workforce addition', 'replacement' => 'Workforce replacement', 'temporary' => 'Temporal needs'];

    public const EMPLOYMENT_TYPES = ['permanent' => 'Fixed', 'contract' => 'Contract', 'project' => 'Project', 'daily' => 'Daily', 'other' => 'Other'];

    public static function categoryRequiresManpower(mixed $departmentId, mixed $categoryId): bool
    {
        return filled($departmentId) && filled($categoryId)
            && Department::query()->find($departmentId)?->isHumanResources()
            && TicketCategory::query()->whereKey($categoryId)->where('handler_department_id', $departmentId)->where('requires_manpower', true)->exists();
    }

    public static function usesForm(mixed $departmentId, mixed $categoryId, ?Ticket $record = null): bool
    {
        $unchangedCategory = $record && (int) $departmentId === (int) $record->handler_department_id
            && (int) $categoryId === (int) $record->ticket_category_id;
        if ($unchangedCategory) {
            return filled($record->manpower_details);
        }

        return self::categoryRequiresManpower($departmentId, $categoryId);
    }

    public static function prepare(Ticket $ticket): void
    {
        $category = $ticket->ticket_category_id ? TicketCategory::query()->find($ticket->ticket_category_id) : null;
        $hasSavedDetails = $ticket->exists && filled($ticket->getRawOriginal('manpower_details'))
            && ! $ticket->isDirty(['handler_department_id', 'ticket_category_id']);

        if ($category?->requires_manpower && ! self::categoryRequiresManpower($ticket->handler_department_id, $category->id)) {
            throw ValidationException::withMessages(['ticket_category_id' => 'The Manpower needs category should fit the HR department\'s destination.']);
        }
        if (! $category?->requires_manpower && ! $hasSavedDetails) {
            if (array_key_exists('manpower_details', $ticket->getAttributes())) {
                $ticket->manpower_details = null;
            }

            return;
        }
        if ($ticket->exists && ! $hasSavedDetails && blank($ticket->manpower_details)
            && ! $ticket->isDirty(['handler_department_id', 'ticket_category_id', 'manpower_details'])) {
            return;
        }
        if ((! $ticket->exists || $ticket->isDirty('ticket_category_id')) && ! $category?->is_active) {
            throw ValidationException::withMessages(['ticket_category_id' => 'Manpower needs category is disabled.']);
        }

        $validated = Validator::make(['manpower_details' => $ticket->manpower_details], [
            'manpower_details' => ['required', 'array'],
            'manpower_details.purpose' => ['required', 'string', 'max:3000'],
            'manpower_details.work_location' => ['required', 'string', 'max:255'],
            'manpower_details.needed_at' => ['required', 'date_format:Y-m-d'],
            'manpower_details.reason' => ['nullable', Rule::in(array_keys(self::REASONS))],
            'manpower_details.notes' => ['nullable', 'string', 'max:3000'],
            'manpower_details.positions' => ['required', 'array', 'min:1', 'max:50'],
            'manpower_details.positions.*' => ['required', 'array'],
            'manpower_details.positions.*.title' => ['required', 'string', 'max:150'],
            'manpower_details.positions.*.quantity' => ['required', 'integer', 'min:1', 'max:10000'],
            'manpower_details.positions.*.specifications' => ['required', 'string', 'max:3000'],
            'manpower_details.positions.*.requirements' => ['required', 'string', 'max:3000'],
            'manpower_details.positions.*.employment_type' => ['nullable', Rule::in(array_keys(self::EMPLOYMENT_TYPES))],
        ], [
            'manpower_details.positions.required' => 'Add at least one required position.',
            'manpower_details.positions.*.quantity.min' => 'The number of needs is at least 1 person.',
            'manpower_details.positions.*.quantity.integer' => 'The number of needs must be an integer.',
        ])->validate()['manpower_details'];

        $details = collect($validated)->only(['purpose', 'work_location', 'needed_at', 'reason', 'notes'])->all();
        $details['positions'] = collect($validated['positions'])->map(fn (array $position): array => [
            ...collect($position)->only(['title', 'specifications', 'requirements', 'employment_type'])->all(),
            'quantity' => (int) $position['quantity'],
        ])->values()->all();
        $ticket->manpower_details = $details;
        $total = array_sum(array_column($details['positions'], 'quantity'));
        $ticket->subject = Str::limit('Needs manpower: '.implode(', ', array_column($details['positions'], 'title')).' ('.$total.' people)', 255, '');
        $ticket->description = self::description($details, $total);
    }

    private static function description(array $details, int $total): string
    {
        $lines = ['Needs: '.$details['purpose'], 'Placement location: '.$details['work_location'],
            'Date required: '.Carbon::parse($details['needed_at'])->format('d/m/Y'), 'Total needs: '.$total.' orang'];
        if (filled($details['reason'] ?? null)) {
            $lines[] = 'Reason for filing: '.self::REASONS[$details['reason']];
        }
        foreach ($details['positions'] as $index => $position) {
            $lines[] = '';
            $lines[] = ($index + 1).'. '.$position['title'].': '.$position['quantity'].' orang';
            $lines[] = 'Job specification: '.$position['specifications'];
            $lines[] = 'Candidate requirements: '.$position['requirements'];
            if (filled($position['employment_type'] ?? null)) {
                $lines[] = 'Workforce type: '.self::EMPLOYMENT_TYPES[$position['employment_type']];
            }
        }
        if (filled($details['notes'] ?? null)) {
            $lines[] = '';
            $lines[] = 'Note: '.$details['notes'];
        }

        return implode("\n", $lines);
    }
}
