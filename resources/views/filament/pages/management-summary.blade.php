<x-filament-panels::page>
    <form wire:submit="$refresh" class="grid gap-4 rounded-xl border border-gray-200 bg-white p-4 md:grid-cols-3 xl:grid-cols-6">
        <label class="text-sm font-medium text-gray-700">Start date<input type="date" wire:model="startDate" class="mt-1 w-full rounded-lg border-gray-300"></label>
        <label class="text-sm font-medium text-gray-700">End date<input type="date" wire:model="endDate" class="mt-1 w-full rounded-lg border-gray-300"></label>
        <label class="text-sm font-medium text-gray-700">Department<select wire:model="departmentId" class="mt-1 w-full rounded-lg border-gray-300"><option value="">All departments</option>@foreach($departments as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select></label>
        <label class="text-sm font-medium text-gray-700">Category<select wire:model="categoryId" class="mt-1 w-full rounded-lg border-gray-300"><option value="">All categories</option>@foreach($categories as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select></label>
        <label class="text-sm font-medium text-gray-700">Priority<select wire:model="priority" class="mt-1 w-full rounded-lg border-gray-300"><option value="">All priorities</option><option value="low">Low</option><option value="medium">Medium</option><option value="high">High</option><option value="urgent">Urgent</option></select></label>
        <label class="text-sm font-medium text-gray-700">Status<select wire:model="status" class="mt-1 w-full rounded-lg border-gray-300"><option value="">All statuses</option><option value="open">Open</option><option value="in_progress">In Progress</option><option value="waiting_user">Pending</option><option value="resolved">Resolved</option><option value="closed">Closed</option></select></label>
        <label class="text-sm font-medium text-gray-700">PIC / Assignee<select wire:model="assigneeId" class="mt-1 w-full rounded-lg border-gray-300"><option value="">All PIC</option>@foreach($employees as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select></label>
        <div class="flex items-end gap-2"><x-filament::button type="submit">Apply filter</x-filament::button><x-filament::button color="gray" wire:click="resetFilters">Reset</x-filament::button></div>
    </form>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach($kpis as $label => $value)
            <section class="rounded-xl border border-gray-200 bg-white p-5">
                <p class="text-sm text-gray-500">{{ $label }}</p><p class="mt-2 text-2xl font-semibold text-gray-900">{{ $value }}</p>
            </section>
        @endforeach
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <section class="rounded-xl border border-gray-200 bg-white p-5"><h2 class="font-semibold text-gray-900">Ticket by Status</h2><dl class="mt-4 space-y-3">@forelse($statusCounts as $label => $total)<div class="flex justify-between gap-4"><dt class="text-sm text-gray-600">{{ str($label)->replace('_', ' ')->title() }}</dt><dd class="font-medium">{{ $total }}</dd></div>@empty<p class="text-sm text-gray-500">No tickets in this period.</p>@endforelse</dl></section>
        <section class="rounded-xl border border-gray-200 bg-white p-5"><h2 class="font-semibold text-gray-900">Ticket by Department</h2><dl class="mt-4 space-y-3">@forelse($departmentCounts as $item)<div class="flex justify-between gap-4"><dt class="text-sm text-gray-600">{{ $item->name }}</dt><dd class="font-medium">{{ $item->total }}</dd></div>@empty<p class="text-sm text-gray-500">No department data in this period.</p>@endforelse</dl></section>
        <section class="rounded-xl border border-gray-200 bg-white p-5"><h2 class="font-semibold text-gray-900">Top Request Categories</h2><dl class="mt-4 space-y-3">@forelse($categoryCounts as $item)<div class="flex justify-between gap-4"><dt class="text-sm text-gray-600">{{ $item->name }}</dt><dd class="font-medium">{{ $item->total }}</dd></div>@empty<p class="text-sm text-gray-500">No category data in this period.</p>@endforelse</dl></section>
    </div>
</x-filament-panels::page>
