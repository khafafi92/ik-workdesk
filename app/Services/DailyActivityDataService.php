<?php

namespace App\Services;

use App\Models\DailyActivity;
use App\Models\WorkTask;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class DailyActivityDataService
{
    public function normalize(array $data, ?DailyActivity $record = null): array
    {
        if (($data['source_type'] ?? null) === 'task' && filled($data['work_task_id'] ?? null)) {
            $task = WorkTask::query()->with('ticket')->find($data['work_task_id']);

            if ($task?->work_project_id) {
                $data['work_context'] = 'project';
                $data['work_project_id'] = $task->work_project_id;
                $data['activity_category_id'] = null;
            }

            if ($task?->ticket?->requester_department_id) {
                $data['requester_type'] = 'division';
                $data['requester_department_id'] = $task->ticket->requester_department_id;
            }
        }

        $this->validateClassification($data);

        $start = Carbon::parse($data['work_date'].' '.$data['start_time']);
        $end = Carbon::parse($data['work_date'].' '.$data['end_time']);

        if (! $end->greaterThan($start)) {
            throw ValidationException::withMessages([
                'end_time' => 'Jam selesai harus setelah jam mulai pada hari yang sama.',
            ]);
        }

        $overlaps = DailyActivity::query()
            ->where('user_id', $data['user_id'])
            ->whereDate('work_date', $data['work_date'])
            ->when($record, fn ($query) => $query->whereKeyNot($record->id))
            ->where('start_time', '<', $data['end_time'])
            ->where('end_time', '>', $data['start_time'])
            ->exists();

        if ($overlaps) {
            throw ValidationException::withMessages([
                'start_time' => 'Waktu pekerjaan bertabrakan dengan aktivitas lain pada tanggal yang sama.',
            ]);
        }

        $data['duration_minutes'] = (int) $start->diffInMinutes($end);

        if (($data['source_type'] ?? 'manual') !== 'task') {
            $data['work_task_id'] = null;
        }

        if (($data['work_context'] ?? null) === 'project') {
            $data['activity_category_id'] = null;
        } else {
            $data['work_project_id'] = null;
        }

        $data['requester_company_name'] = ($data['requester_type'] ?? null) === 'company'
            ? ($data['requester_company_name'] ?? null) : null;
        $data['requester_department_id'] = ($data['requester_type'] ?? null) === 'division'
            ? ($data['requester_department_id'] ?? null) : null;
        $data['requester_employee_id'] = ($data['requester_type'] ?? null) === 'individual'
            ? ($data['requester_employee_id'] ?? null) : null;

        return $data;
    }

    private function validateClassification(array $data): void
    {
        $errors = [];

        if (($data['source_type'] ?? null) === 'task' && blank($data['work_task_id'] ?? null)) {
            $errors['work_task_id'] = 'Source Task wajib dipilih.';
        }

        if (($data['work_context'] ?? null) === 'project' && blank($data['work_project_id'] ?? null)) {
            $errors['work_project_id'] = 'Project wajib dipilih untuk pekerjaan project.';
        }

        if (($data['work_context'] ?? null) === 'operational' && blank($data['activity_category_id'] ?? null)) {
            $errors['activity_category_id'] = 'Kategori wajib dipilih untuk pekerjaan operasional.';
        }

        $requesterField = match ($data['requester_type'] ?? null) {
            'company' => 'requester_company_name',
            'division' => 'requester_department_id',
            'individual' => 'requester_employee_id',
            default => null,
        };

        if ($requesterField === null) {
            $errors['requester_type'] = 'Jenis pemberi pekerjaan wajib dipilih.';
        } elseif (blank($data[$requesterField] ?? null)) {
            $errors[$requesterField] = 'Detail pemberi pekerjaan wajib diisi.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
