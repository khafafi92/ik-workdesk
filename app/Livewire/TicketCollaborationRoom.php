<?php

namespace App\Livewire;

use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Ticket;
use App\Models\TicketComment;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class TicketCollaborationRoom extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $ticketId;

    public string $message = '';

    /** @var array<int, TemporaryUploadedFile> */
    public array $messageFiles = [];

    public function mount(Ticket $record): void
    {
        $this->ticketId = (int) $record->getKey();
        $this->authorizeTicket($record);
    }

    public function addMessage(): void
    {
        $ticket = $this->ticket();
        $this->authorizeTicket($ticket);

        $this->validate([
            'message' => ['required', 'string', 'max:5000'],
            'messageFiles' => ['array', 'max:10'],
            'messageFiles.*' => ['file', 'max:102400'],
        ]);

        $context = $this->activityContext($ticket);

        $ticket->comments()->create([
            'work_task_id' => $context['work_task_id'],
            'department_id' => $context['department_id'],
            'user_id' => auth()->id(),
            'activity_type' => 'message',
            'message' => trim($this->message),
            'attachments' => $this->storeFiles(
                $this->messageFiles,
                'ticket-comments'
            ),
            'metadata' => [
                'source' => 'collaboration_room',
                'parent_ticket_no' => $ticket->ticket_no,
            ],
        ]);

        $this->reset('message', 'messageFiles');

        Notification::make()
            ->title('Message posted to collaboration room')
            ->success()
            ->send();
    }

    public function activityLabel(string $type): string
    {
        return match ($type) {
            'message' => 'Message',
            'work_log_created' => 'Work Log Created',
            'pic_change' => 'PIC Changed',
            'status_change' => 'Status Changed',
            'progress_change' => 'Progress Updated',
            'due_date_change' => 'Due Date Updated',
            'notes_change' => 'Notes Updated',
            default => Str::of($type)->replace('_', ' ')->title()->toString(),
        };
    }

    public function render(): View
    {
        $ticket = $this->ticket();
        $this->authorizeTicket($ticket);

        $ticket->load([
            'employee.department',
            'assignments.department',
            'workTasks.department',
            'comments.user.employee.department',
            'comments.department',
            'comments.workTask.department',
        ]);

        return view('livewire.ticket-collaboration-room', [
            'ticket' => $ticket,
            'participants' => $this->participants($ticket),
            'timeline' => $ticket->comments
                ->sortByDesc('created_at')
                ->values(),
            'summary' => $this->workLogSummary($ticket),
        ]);
    }

    private function ticket(): Ticket
    {
        return Ticket::query()->findOrFail($this->ticketId);
    }

    private function authorizeTicket(Ticket $ticket): void
    {
        abort_unless(TicketResource::canView($ticket), 403);
    }

    /**
     * @return array{department_id: int|null, work_task_id: int|null}
     */
    private function activityContext(Ticket $ticket): array
    {
        $departmentId = auth()->user()?->employee?->department_id;
        $workTaskId = $departmentId
            ? $ticket->workTasks()
                ->where('department_id', $departmentId)
                ->value('id')
            : null;

        return [
            'department_id' => $departmentId
                ? (int) $departmentId
                : null,
            'work_task_id' => $workTaskId
                ? (int) $workTaskId
                : null,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function storeFiles(array $files, string $directory): array
    {
        return collect($files)
            ->filter(fn ($file): bool => $file instanceof TemporaryUploadedFile)
            ->map(function (TemporaryUploadedFile $file) use ($directory): string {
                $name = now()->format('YmdHis')
                    . '-'
                    . Str::random(6)
                    . '-'
                    . Str::slug(pathinfo(
                        $file->getClientOriginalName(),
                        PATHINFO_FILENAME
                    ))
                    . '.'
                    . strtolower($file->getClientOriginalExtension());

                return $file->storeAs($directory, $name, 'public');
            })
            ->values()
            ->all();
    }

    private function participants(Ticket $ticket): Collection
    {
        return collect([
            $ticket->employee?->department?->name,
            ...$ticket->assignments->pluck('department.name')->all(),
        ])->filter()->unique()->values();
    }

    private function workLogSummary(Ticket $ticket): array
    {
        return [
            'total' => $ticket->workTasks->count(),
            'planned' => $ticket->workTasks->where('status', 'planned')->count(),
            'in_progress' => $ticket->workTasks->where('status', 'in_progress')->count(),
            'hold' => $ticket->workTasks->where('status', 'hold')->count(),
            'done' => $ticket->workTasks->where('status', 'done')->count(),
        ];
    }
}
