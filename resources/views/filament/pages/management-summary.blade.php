<x-filament-panels::page>
    <div class="ik-report-overview">
        <section class="ik-report-overview-filter" aria-labelledby="report-filter-heading">
            <div class="ik-report-overview-section-heading">
                <div>
                    <h2 id="report-filter-heading">Filter laporan</h2>
                    <p>{{ __('Adjust the period and coverage of the data you want to review.') }}</p>
                </div>
            </div>

            <form wire:submit="$refresh" class="ik-report-overview-filter-form">
                <label><span>{{ __('Start date') }}</span><input type="date" wire:model="startDate"></label>
                <label><span>{{ __('Date complete') }}</span><input type="date" wire:model="endDate"></label>
                <label><span>{{ __('Department') }}</span><select wire:model="departmentId"><option value="">{{ __('All departments') }}</option>@foreach ($departments as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select></label>
                <label><span>{{ __('Category') }}</span><select wire:model="categoryId"><option value="">{{ __('All categories') }}</option>@foreach ($categories as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select></label>
                <label><span>{{ __('Priority') }}</span><select wire:model="priority"><option value="">{{ __('All priorities') }}</option><option value="low">{{ __('Low') }}</option><option value="medium">{{ __('Medium') }}</option><option value="high">{{ __('High') }}</option><option value="urgent">{{ __('Urgent') }}</option></select></label>
                <label><span>{{ __('Status') }}</span><select wire:model="status"><option value="">{{ __('All statuses') }}</option><option value="open">{{ __('Open') }}</option><option value="in_progress">{{ __('In Progress') }}</option><option value="waiting_user">{{ __('Pending') }}</option><option value="resolved">{{ __('Resolved') }}</option><option value="closed">{{ __('Closed') }}</option></select></label>
                <label><span>PIC / Assignee</span><select wire:model="assigneeId"><option value="">{{ __('All assignees') }}</option>@foreach ($employees as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select></label>
                <div class="ik-report-overview-filter-actions">
                    <x-filament::button type="submit">{{ __('Apply filters') }}</x-filament::button>
                    <x-filament::button color="gray" type="button" wire:click="resetFilters">Reset</x-filament::button>
                </div>
            </form>
        </section>

        <section aria-labelledby="report-summary-heading">
            <div class="ik-report-overview-section-heading">
                <div>
                    <h2 id="report-summary-heading">{{ __('Period summary') }}</h2>
                    <p>{{ __('The main indicator of the data that fills the filter above.') }}</p>
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

        <section class="ik-report-overview-breakdowns" aria-label="{{ __('Report details') }}">
            <article>
                <header><h2>{{ __('Ticket by status') }}</h2></header>
                <dl>@forelse ($statusCounts as $label => $total)<div><dt>{{ str($label)->replace('_', ' ')->title() }}</dt><dd>{{ $total }}</dd></div>@empty<p>{{ __('There\'s no ticket in this period.') }}</p>@endforelse</dl>
            </article>
            <article>
                <header><h2>{{ __('Ticket by department') }}</h2></header>
                <dl>@forelse ($departmentCounts as $item)<div><dt>{{ $item->name }}</dt><dd>{{ $item->total }}</dd></div>@empty<p>{{ __('No department data at this period.') }}</p>@endforelse</dl>
            </article>
            <article>
                <header><h2>{{ __('Top request category') }}</h2></header>
                <dl>@forelse ($categoryCounts as $item)<div><dt>{{ $item->name }}</dt><dd>{{ $item->total }}</dd></div>@empty<p>{{ __('No category data at this period.') }}</p>@endforelse</dl>
            </article>
        </section>
    </div>
</x-filament-panels::page>
