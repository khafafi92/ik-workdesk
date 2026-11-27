<?php

namespace App\Filament\Resources\Tickets\Pages;

use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Employee;
use App\Models\Ticket;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

class ListTickets extends ListRecords
{
    protected static string $resource = TicketResource::class;

    protected string $view = 'filament.resources.tickets.pages.list-tickets';

    #[Url(as: 'view')]
    public string $viewMode = 'board';

    public string $boardSearch = '';

    public string $boardRequester = '';

    public string $boardPriority = '';

    public string $boardGroupBy = 'status';

    public string $boardSort = 'newest';

    /** @var array<int, string> */
    public array $collapsedGroups = [];

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('New request'))
                ->visible(fn (): bool => TicketResource::canCreate()),
        ];
    }

    public function setViewMode(string $mode): void
    {
        if (in_array($mode, ['board', 'table', 'timeline'], true)) {
            $this->viewMode = $mode;
        }
    }

    public function toggleGroup(string $groupKey): void
    {
        if (in_array($groupKey, $this->collapsedGroups, true)) {
            $this->collapsedGroups = array_values(array_diff($this->collapsedGroups, [$groupKey]));

            return;
        }

        $this->collapsedGroups[] = $groupKey;
    }

    /**
     * @return Collection<int, Ticket>
     */
    public function getBoardTickets(): Collection
    {
        $query = TicketResource::getEloquentQuery()
            ->with(['category', 'requesterDepartment', 'handlerDepartment']);

        if (trim($this->boardSearch) !== '') {
            $term = '%'.trim($this->boardSearch).'%';
            $query->where(function (Builder $q) use ($term): void {
                $q->where('ticket_no', 'like', $term)
                    ->orWhere('subject', 'like', $term)
                    ->orWhereHas('employee', fn (Builder $eq) => $eq->where('name', 'like', $term))
                    ->orWhereHas('handlerDepartment', fn (Builder $dq) => $dq->where('name', 'like', $term));
            });
        }

        if ($this->boardRequester !== '') {
            $query->where('employee_id', (int) $this->boardRequester);
        }

        if ($this->boardPriority !== '') {
            $query->where('priority', $this->boardPriority);
        }

        match ($this->boardSort) {
            'reported_asc' => $query->orderBy('reported_at'),
            'priority_desc' => $query->orderByRaw("CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 WHEN 'low' THEN 4 ELSE 5 END"),
            default => $query->orderByDesc('created_at'),
        };

        return $query->limit(150)->get();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getBoardGroups(): array
    {
        $tickets = $this->getBoardTickets();

        if ($this->boardGroupBy === 'priority') {
            $definitions = [
                'urgent' => ['label' => __('Urgent'), 'color' => '#c93246'],
                'high' => ['label' => __('High'), 'color' => '#401694'],
                'medium' => ['label' => __('Medium'), 'color' => '#5b3cc4'],
                'low' => ['label' => __('Low'), 'color' => '#0073ea'],
            ];

            return collect($definitions)->map(fn (array $meta, string $key): array => [
                'key' => $key,
                'label' => $meta['label'],
                'color' => $meta['color'],
                'tickets' => $tickets->where('priority', $key)->values(),
            ])->values()->all();
        }

        if ($this->boardGroupBy === 'department') {
            return $tickets
                ->groupBy(fn (Ticket $ticket): string => (string) ($ticket->handler_department_id ?? 'none'))
                ->map(function (Collection $groupTickets, string $key): array {
                    $first = $groupTickets->first();

                    return [
                        'key' => 'dept-'.$key,
                        'label' => $first?->handlerDepartment?->name ?? __('Unassigned Dept'),
                        'color' => $key === 'none' ? '#579bfc' : '#0073ea',
                        'tickets' => $groupTickets->values(),
                    ];
                })
                ->values()
                ->all();
        }

        $definitions = [
            'open' => ['label' => __('Open / New'), 'color' => '#c93246'],
            'in_progress' => ['label' => __('In Progress'), 'color' => '#e08b12'],
            'waiting_user' => ['label' => __('Waiting User'), 'color' => '#0073ea'],
            'discussion' => ['label' => __('Discussion'), 'color' => '#5b3cc4'],
            'resolved' => ['label' => __('Resolved'), 'color' => '#00854d'],
            'closed' => ['label' => __('Closed'), 'color' => '#475569'],
        ];

        return collect($definitions)->map(fn (array $meta, string $key): array => [
            'key' => $key,
            'label' => $meta['label'],
            'color' => $meta['color'],
            'tickets' => $tickets->where('status', $key)->values(),
        ])->values()->all();
    }

    /**
     * @return Collection<int, Employee>
     */
    public function getRequesterOptions(): Collection
    {
        return Employee::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->limit(80)
            ->get(['id', 'name']);
    }
}
