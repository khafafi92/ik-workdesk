@extends('layouts.admin')

@section('title', 'Dashboard')
@section('page-title')
    @php
        $hour = now()->format('H');
        $greeting = match(true) {
            $hour < 11 => 'Selamat Pagi',
            $hour < 15 => 'Selamat Siang',
            $hour < 18 => 'Selamat Sore',
            default => 'Selamat Malam',
        };
        $firstName = explode(' ', auth()->user()->name ?? 'User')[0];
    @endphp
    <div class="ik-hero-greeting">
        <span>{{ $greeting }}</span>
        <span class="ik-hero-wave">ðŸ‘‹</span>
    </div>
    {{ $firstName }}, ini ringkasan hari ini
    <div class="ik-hero-date">{{ now()->translatedFormat('l, d F Y') }}</div>
@endsection
@section('page-description', 'Monitor service desk, work logs, dan reminders.')

@section('content')
<div class="ik-dashboard-content space-y-5">

    @include('admin._compact-overview')

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
        <div class="bg-white border border-[#e6e9ef] rounded-lg shadow-[0_1px_3px_rgba(0,0,0,0.04)] overflow-hidden">
            <div class="px-4 py-3 border-b border-[#e6e9ef] flex items-center justify-between">
                <div class="flex items-center">
                    <div class="ik-panel-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-5.25h5.25M7.5 15h3M3.375 5.25c-.621 0-1.125.504-1.125 1.125v3.026a2.999 2.999 0 0 1 0 5.198v3.026c0 .621.504 1.125 1.125 1.125h17.25c.621 0 1.125-.504 1.125-1.125v-3.026a2.999 2.999 0 0 1 0-5.198V6.375c0-.621-.504-1.125-1.125-1.125H3.375Z" /></svg></div>
                    <h2 class="text-sm font-semibold text-[#323338]">Latest Service Desk</h2>
                </div>
                <a href="{{ url('/panel/service-desk') }}" class="text-xs font-semibold text-[#0073ea] hover:underline">View all</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr><th class="text-left px-4 py-2 text-[10px] font-semibold text-[#676879] uppercase tracking-wider bg-[#f5f6f8]">Request No</th><th class="text-left px-4 py-2 text-[10px] font-semibold text-[#676879] uppercase tracking-wider bg-[#f5f6f8]">Subject</th><th class="text-left px-4 py-2 text-[10px] font-semibold text-[#676879] uppercase tracking-wider bg-[#f5f6f8]">Dept</th><th class="text-left px-4 py-2 text-[10px] font-semibold text-[#676879] uppercase tracking-wider bg-[#f5f6f8]">Status</th></tr></thead>
                    <tbody class="divide-y divide-[#edeef2]">
                        @forelse ($latestRequests as $request)
                        <tr class="hover:bg-[#f5f6f8] transition-colors"><td class="px-4 py-2.5 text-xs font-semibold text-[#0073ea]">{{ $request->ticket_no }}</td><td class="px-4 py-2.5 text-xs text-[#323338]">{{ $request->subject }}</td><td class="px-4 py-2.5 text-xs text-[#676879]">{{ $request->handlerDepartment?->name ?? '-' }}</td><td class="px-4 py-2.5"><span class="rounded bg-[#f0f1f3] px-1.5 py-0.5 text-[10px] font-semibold text-[#676879]">{{ str_replace('_', ' ', $request->status) }}</span></td></tr>
                        @empty
                        <tr><td colspan="4" class="px-4 py-5 text-center text-xs text-[#9699a6]">Belum ada service request.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-white border border-[#e6e9ef] rounded-lg shadow-[0_1px_3px_rgba(0,0,0,0.04)] overflow-hidden">
            <div class="px-4 py-3 border-b border-[#e6e9ef] flex items-center justify-between">
                <div class="flex items-center">
                    <div class="ik-panel-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25Z" /></svg></div>
                    <h2 class="text-sm font-semibold text-[#323338]">Latest Work Logs</h2>
                </div>
                <a href="{{ url('/panel/work-tasks') }}" class="text-xs font-semibold text-[#0073ea] hover:underline">View all</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr><th class="text-left px-4 py-2 text-[10px] font-semibold text-[#676879] uppercase tracking-wider bg-[#f5f6f8]">Task No</th><th class="text-left px-4 py-2 text-[10px] font-semibold text-[#676879] uppercase tracking-wider bg-[#f5f6f8]">Task</th><th class="text-left px-4 py-2 text-[10px] font-semibold text-[#676879] uppercase tracking-wider bg-[#f5f6f8]">PIC</th><th class="text-left px-4 py-2 text-[10px] font-semibold text-[#676879] uppercase tracking-wider bg-[#f5f6f8]">Progress</th></tr></thead>
                    <tbody class="divide-y divide-[#edeef2]">
                        @forelse ($latestWorkLogs as $task)
                        <tr class="hover:bg-[#f5f6f8] transition-colors"><td class="px-4 py-2.5 text-xs font-semibold text-[#0073ea]">{{ $task->task_no }}</td><td class="px-4 py-2.5 text-xs text-[#323338]">{{ $task->title }}</td><td class="px-4 py-2.5 text-xs text-[#676879]">{{ $task->employee?->name ?? '-' }}</td><td class="px-4 py-2.5"><span class="rounded bg-[#f0f1f3] px-1.5 py-0.5 text-[10px] font-semibold text-[#676879]">{{ $task->progress_percent }}%</span></td></tr>
                        @empty
                        <tr><td colspan="4" class="px-4 py-5 text-center text-xs text-[#9699a6]">Belum ada work log.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
