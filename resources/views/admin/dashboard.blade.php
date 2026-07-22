@extends('layouts.admin')

@section('title', 'Dashboard')
@section('page-title', 'IK WorkDesk Dashboard')
@section('page-description', 'Ringkasan Service Desk dan Work Logs.')

@section('content')
<div class="ik-dashboard-content space-y-6">

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-6 gap-4">
        <div class="ik-stat-card ik-accent-neutral bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
            <p class="text-xs text-slate-500">Total Requests</p>
            <p class="mt-2 text-3xl font-bold text-slate-900">{{ $serviceRequestStats['total'] }}</p>
        </div>

        <div class="ik-stat-card ik-accent-danger bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
            <p class="text-xs text-slate-500">Open</p>
            <p class="mt-2 text-3xl font-bold text-red-600">{{ $serviceRequestStats['open'] }}</p>
        </div>

        <div class="ik-stat-card ik-accent-warning bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
            <p class="text-xs text-slate-500">In Progress</p>
            <p class="mt-2 text-3xl font-bold text-amber-600">{{ $serviceRequestStats['in_progress'] }}</p>
        </div>

        <div class="ik-stat-card ik-accent-info bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
            <p class="text-xs text-slate-500">Waiting User</p>
            <p class="mt-2 text-3xl font-bold text-blue-600">{{ $serviceRequestStats['waiting_user'] }}</p>
        </div>

        <div class="ik-stat-card ik-accent-success bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
            <p class="text-xs text-slate-500">Resolved</p>
            <p class="mt-2 text-3xl font-bold text-emerald-600">{{ $serviceRequestStats['resolved'] }}</p>
        </div>

        <div class="ik-stat-card ik-accent-danger bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
            <p class="text-xs text-slate-500">Overdue</p>
            <p class="mt-2 text-3xl font-bold text-rose-700">{{ $serviceRequestStats['overdue'] }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-4">
        <div class="ik-stat-card ik-accent-neutral bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
            <p class="text-xs text-slate-500">Total Work Logs</p>
            <p class="mt-2 text-3xl font-bold text-slate-900">{{ $workLogStats['total'] }}</p>
        </div>

        <div class="ik-stat-card ik-accent-neutral bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
            <p class="text-xs text-slate-500">Planned</p>
            <p class="mt-2 text-3xl font-bold text-slate-600">{{ $workLogStats['planned'] }}</p>
        </div>

        <div class="ik-stat-card ik-accent-warning bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
            <p class="text-xs text-slate-500">In Progress</p>
            <p class="mt-2 text-3xl font-bold text-amber-600">{{ $workLogStats['in_progress'] }}</p>
        </div>

        <div class="ik-stat-card ik-accent-success bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
            <p class="text-xs text-slate-500">Done</p>
            <p class="mt-2 text-3xl font-bold text-emerald-600">{{ $workLogStats['done'] }}</p>
        </div>

        <div class="ik-stat-card ik-accent-danger bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
            <p class="text-xs text-slate-500">Overdue Work</p>
            <p class="mt-2 text-3xl font-bold text-rose-700">{{ $workLogStats['overdue'] }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="ik-stat-card ik-accent-neutral bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
            <p class="text-xs text-slate-500">Pending Reminders</p>
            <p class="mt-2 text-3xl font-bold text-slate-900">{{ $reminderStats['pending'] }}</p>
        </div>

        <div class="ik-stat-card ik-accent-info bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
            <p class="text-xs text-slate-500">Today Reminders</p>
            <p class="mt-2 text-3xl font-bold text-blue-600">{{ $reminderStats['today'] }}</p>
        </div>

        <div class="ik-stat-card ik-accent-danger bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
            <p class="text-xs text-slate-500">Overdue Reminders</p>
            <p class="mt-2 text-3xl font-bold text-rose-700">{{ $reminderStats['overdue'] }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
            <div class="p-5 border-b border-slate-200 flex items-center justify-between">
                <div>
                    <h2 class="font-bold text-slate-900">Today's Reminders</h2>
                    <p class="text-sm text-slate-500">Reminder yang jatuh pada hari ini.</p>
                </div>

                <a href="{{ url('/panel/reminders') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-800">
                    Open Panel
                </a>
            </div>

            <div class="divide-y divide-slate-100">
                @forelse ($todayReminders as $reminder)
                <div class="p-5 flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="px-2 py-1 rounded-full text-xs bg-blue-50 text-blue-700">
                                {{ str_replace('_', ' ', $reminder->reminder_type) }}
                            </span>

                            <span class="text-xs text-slate-400">
                                {{ $reminder->reminder_at?->format('H:i') }}
                            </span>
                        </div>

                        <p class="mt-2 font-semibold text-slate-900">
                            {{ $reminder->title }}
                        </p>

                        <p class="mt-1 text-sm text-slate-500">
                            {{ $reminder->description ?? '-' }}
                        </p>

                        <p class="mt-2 text-xs text-slate-400">
                            For: {{ $reminder->employee?->name ?? 'All / Department' }}
                            @if ($reminder->department)
                                &bull; {{ $reminder->department->name }}
                            @endif
                        </p>
                    </div>

                    <span class="px-2 py-1 rounded-full text-xs bg-amber-50 text-amber-700">
                        {{ $reminder->status }}
                    </span>
                </div>
                @empty
                <div class="p-6 text-center text-sm text-slate-500">
                    Tidak ada reminder hari ini.
                </div>
                @endforelse
            </div>
        </div>

        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
            <div class="p-5 border-b border-slate-200">
                <h2 class="font-bold text-slate-900">Upcoming Reminders</h2>
                <p class="text-sm text-slate-500">Reminder mendatang.</p>
            </div>

            <div class="divide-y divide-slate-100">
                @forelse ($upcomingReminders as $reminder)
                <div class="p-5">
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-1 rounded-full text-xs bg-slate-100 text-slate-700">
                            {{ str_replace('_', ' ', $reminder->reminder_type) }}
                        </span>

                        <span class="text-xs text-slate-400">
                            {{ $reminder->reminder_at?->format('d M Y H:i') }}
                        </span>
                    </div>

                    <p class="mt-2 font-semibold text-slate-900">
                        {{ $reminder->title }}
                    </p>

                    <p class="mt-1 text-sm text-slate-500">
                        {{ $reminder->employee?->name ?? $reminder->department?->name ?? 'General' }}
                    </p>
                </div>
                @empty
                <div class="p-6 text-center text-sm text-slate-500">
                    Belum ada upcoming reminder.
                </div>
                @endforelse
            </div>
        </div>
    </div>

    @if ($overdueReminders->count() > 0)
    <div class="bg-white border border-rose-200 rounded-2xl shadow-sm overflow-hidden">
        <div class="p-5 border-b border-rose-100 bg-rose-50">
            <h2 class="font-bold text-rose-800">Overdue Reminders</h2>
            <p class="text-sm text-rose-600">Reminder yang sudah lewat waktunya dan masih pending.</p>
        </div>

        <div class="divide-y divide-rose-100">
            @foreach ($overdueReminders as $reminder)
            <div class="p-5 flex items-start justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-1 rounded-full text-xs bg-rose-100 text-rose-700">
                            {{ str_replace('_', ' ', $reminder->reminder_type) }}
                        </span>

                        <span class="text-xs text-rose-500">
                            {{ $reminder->reminder_at?->format('d M Y H:i') }}
                        </span>
                    </div>

                    <p class="mt-2 font-semibold text-slate-900">
                        {{ $reminder->title }}
                    </p>

                    <p class="mt-1 text-sm text-slate-500">
                        {{ $reminder->description ?? '-' }}
                    </p>

                    <p class="mt-2 text-xs text-slate-400">
                        For: {{ $reminder->employee?->name ?? 'All / Department' }}
                        @if ($reminder->department)
                            &bull; {{ $reminder->department->name }}
                        @endif
                    </p>
                </div>

                <span class="px-2 py-1 rounded-full text-xs bg-rose-100 text-rose-700">
                    Overdue
                </span>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
            <div class="p-5 border-b border-slate-200 flex items-center justify-between">
                <div>
                    <h2 class="font-bold text-slate-900">Latest Service Desk</h2>
                    <p class="text-sm text-slate-500">Request terbaru dari user/divisi.</p>
                </div>

                <a href="{{ url('/panel/service-desk') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-800">
                    Open Panel
                </a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-slate-500">
                        <tr>
                            <th class="text-left px-5 py-3">Request No</th>
                            <th class="text-left px-5 py-3">Subject</th>
                            <th class="text-left px-5 py-3">To Dept</th>
                            <th class="text-left px-5 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($latestRequests as $request)
                        <tr>
                            <td class="px-5 py-3 font-medium text-slate-900">
                                {{ $request->ticket_no }}
                            </td>
                            <td class="px-5 py-3 text-slate-600">
                                {{ $request->subject }}
                            </td>
                            <td class="px-5 py-3 text-slate-600">
                                {{ $request->handlerDepartment?->name ?? '-' }}
                            </td>
                            <td class="px-5 py-3">
                                <span class="px-2 py-1 rounded-full text-xs bg-slate-100 text-slate-700">
                                    {{ str_replace('_', ' ', $request->status) }}
                                </span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="px-5 py-6 text-center text-slate-500">
                                Belum ada service request.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
            <div class="p-5 border-b border-slate-200 flex items-center justify-between">
                <div>
                    <h2 class="font-bold text-slate-900">Latest Work Logs</h2>
                    <p class="text-sm text-slate-500">Pekerjaan terbaru dari request/task.</p>
                </div>

                <a href="{{ url('/panel/work-tasks') }}"
                    class="text-sm font-semibold text-blue-600 hover:text-blue-800">
                    Open Panel
                </a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-slate-500">
                        <tr>
                            <th class="text-left px-5 py-3">Task No</th>
                            <th class="text-left px-5 py-3">Task</th>
                            <th class="text-left px-5 py-3">PIC</th>
                            <th class="text-left px-5 py-3">Progress</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($latestWorkLogs as $task)
                        <tr>
                            <td class="px-5 py-3 font-medium text-slate-900">
                                {{ $task->task_no }}
                            </td>
                            <td class="px-5 py-3 text-slate-600">
                                {{ $task->title }}
                            </td>
                            <td class="px-5 py-3 text-slate-600">
                                {{ $task->employee?->name ?? '-' }}
                            </td>
                            <td class="px-5 py-3">
                                <span class="px-2 py-1 rounded-full text-xs bg-slate-100 text-slate-700">
                                    {{ $task->progress_percent }}%
                                </span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="px-5 py-6 text-center text-slate-500">
                                Belum ada work log.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
@endsection
