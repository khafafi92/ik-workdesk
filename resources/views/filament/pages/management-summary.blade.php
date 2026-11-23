<x-filament-panels::page>
    <div class="ik-report-overview">
        <section class="ik-report-overview-filter" aria-labelledby="report-filter-heading">
            <div class="ik-report-overview-section-heading">
                <div>
                    <h2 id="report-filter-heading">Filter laporan</h2>
                    <p>Adjust the period and coverage of the data you want to review.</p>
                </div>
            </div>

            <form wire:submit="$refresh" class="ik-report-overview-filter-form">
                <label><span>Start date</span><input type="date" wire:model="startDate"></label>
                <label><span>Date complete</span><input type="date" wire:model="endDate"></label>
                <label><span>Department</span><select wire:model="departmentId"><option value="">All departments</option>@foreach ($departments as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select></label>
                <label><span>Category</span><select wire:model="categoryId"><option value="">All categories</option>@foreach ($categories as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select></label>
                <label><span>Priority</span><select wire:model="priority"><option value="">All priorities</option><option value="low">Low</option><option value="medium">Medium</option><option value="high">High</option><option value="urgent">Urgent</option></select></label>
                <label><span>Status</span><select wire:model="status"><option value="">All statuses</option><option value="open">Open</option><option value="in_progress">In Progress</option><option value="waiting_user">Pending</option><option value="resolved">Resolved</option><option value="closed">Closed</option></select></label>
                <label><span>PIC / Assignee</span><select wire:model="assigneeId"><option value="">All assignees</option>@foreach ($employees as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select></label>
                <div class="ik-report-overview-filter-actions">
                    <x-filament::button type="submit">Apply filters</x-filament::button>
                    <x-filament::button color="gray" type="button" wire:click="resetFilters">Reset</x-filament::button>
                </div>
            </form>
        </section>

        <section aria-labelledby="report-summary-heading">
            <div class="ik-report-overview-section-heading">
                <div>
                    <h2 id="report-summary-heading">Period summary</h2>
                    <p>The main indicator of the data that fills the filter above.</p>
                </div>
            </div>
            <dl class="ik-report-overview-metrics">
                @foreach ($kpis as $label => $value)
                    @php($tone = match ($label) { 'Overdue Tickets' => 'danger', 'Resolved Tickets', 'Closed Tickets' => 'success', 'Pending Tickets' => 'warning', default => 'default' })
                    <div class="ik-report-overview-metric ik-report-overview-metric--{{ $tone }}">
                        <dt>{{ $label }}</dt><dd @class(['ik-report-overview-metric-text' => ! is_numeric($value)])>{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>

        <section class="ik-report-overview-breakdowns" aria-label="Report details">
            <article>
                <header><h2>Ticket by status</h2></header>
                <dl>@forelse ($statusCounts as $label => $total)<div><dt>{{ str($label)->replace('_', ' ')->title() }}</dt><dd>{{ $total }}</dd></div>@empty<p>There's no ticket in this period.</p>@endforelse</dl>
            </article>
            <article>
                <header><h2>Ticket by department</h2></header>
                <dl>@forelse ($departmentCounts as $item)<div><dt>{{ $item->name }}</dt><dd>{{ $item->total }}</dd></div>@empty<p>No department data at this period.</p>@endforelse</dl>
            </article>
            <article>
                <header><h2>Top request category</h2></header>
                <dl>@forelse ($categoryCounts as $item)<div><dt>{{ $item->name }}</dt><dd>{{ $item->total }}</dd></div>@empty<p>No category data at this period.</p>@endforelse</dl>
            </article>
        </section>
    </div>
</x-filament-panels::page>
