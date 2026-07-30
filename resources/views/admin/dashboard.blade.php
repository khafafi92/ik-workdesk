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
        <span class="ik-hero-wave">👋</span>
    </div>
    {{ $firstName }}, ini ringkasan hari ini
    <div class="ik-hero-date">{{ now()->translatedFormat('l, d F Y') }}</div>
@endsection
@section('page-description', 'Monitor service desk, work logs, dan reminders.')

@section('content')
<div class="ik-dashboard-content space-y-5">

    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3">
        <div class="ik-stat-card ik-accent-neutral bg-white border rounded-lg p-3 mn-stagger-1">
            <div class="ik-stat-icon ik-stat-icon--neutral"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg></div>
            <p class="text-[11px] text-[#676879]">Total Requests</p>
            <p class="mt-0.5 text-lg font-bold text-[#323338]">{{ $serviceRequestStats['total'] }}</p>
        </div>
        <div class="ik-stat-card ik-accent-danger bg-white border rounded-lg p-3 mn-stagger-2">
            <div class="ik-stat-icon ik-stat-icon--danger"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" /></svg></div>
            <p class="text-[11px] text-[#676879]">Open</p>
            <p class="mt-0.5 text-lg font-bold text-[#e2445c]">{{ $serviceRequestStats['open'] }}</p>
        </div>
        <div class="ik-stat-card ik-accent-warning bg-white border rounded-lg p-3 mn-stagger-3">
            <div class="ik-stat-icon ik-stat-icon--warning"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" /></svg></div>
            <p class="text-[11px] text-[#676879]">In Progress</p>
            <p class="mt-0.5 text-lg font-bold text-[#fdab3d]">{{ $serviceRequestStats['in_progress'] }}</p>
        </div>
        <div class="ik-stat-card ik-accent-info bg-white border rounded-lg p-3 mn-stagger-4">
            <div class="ik-stat-icon ik-stat-icon--info"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg></div>
            <p class="text-[11px] text-[#676879]">Waiting User</p>
            <p class="mt-0.5 text-lg font-bold text-[#0073ea]">{{ $serviceRequestStats['waiting_user'] }}</p>
        </div>
        <div class="ik-stat-card ik-accent-success bg-white border rounded-lg p-3 mn-stagger-5">
            <div class="ik-stat-icon ik-stat-icon--success"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg></div>
            <p class="text-[11px] text-[#676879]">Resolved</p>
            <p class="mt-0.5 text-lg font-bold text-[#00c875]">{{ $serviceRequestStats['resolved'] }}</p>
        </div>
        <div class="ik-stat-card ik-accent-danger bg-white border rounded-lg p-3 mn-stagger-6">
            <div class="ik-stat-icon ik-stat-icon--danger"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" /></svg></div>
            <p class="text-[11px] text-[#676879]">Overdue</p>
            <p class="mt-0.5 text-lg font-bold text-[#e2445c]">{{ $serviceRequestStats['overdue'] }}</p>
        </div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-3">
        <div class="ik-stat-card ik-accent-neutral bg-white border rounded-lg p-3 mn-stagger-7">
            <div class="ik-stat-icon ik-stat-icon--neutral"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25Z" /></svg></div>
            <p class="text-[11px] text-[#676879]">Total Work Logs</p>
            <p class="mt-0.5 text-lg font-bold text-[#323338]">{{ $workLogStats['total'] }}</p>
        </div>
        <div class="ik-stat-card ik-accent-neutral bg-white border rounded-lg p-3 mn-stagger-8">
            <div class="ik-stat-icon ik-stat-icon--neutral"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" /></svg></div>
            <p class="text-[11px] text-[#676879]">Planned</p>
            <p class="mt-0.5 text-lg font-bold text-[#676879]">{{ $workLogStats['planned'] }}</p>
        </div>
        <div class="ik-stat-card ik-accent-warning bg-white border rounded-lg p-3 mn-stagger-9">
            <div class="ik-stat-icon ik-stat-icon--warning"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" /></svg></div>
            <p class="text-[11px] text-[#676879]">In Progress</p>
            <p class="mt-0.5 text-lg font-bold text-[#fdab3d]">{{ $workLogStats['in_progress'] }}</p>
        </div>
        <div class="ik-stat-card ik-accent-success bg-white border rounded-lg p-3 mn-stagger-10">
            <div class="ik-stat-icon ik-stat-icon--success"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg></div>
            <p class="text-[11px] text-[#676879]">Done</p>
            <p class="mt-0.5 text-lg font-bold text-[#00c875]">{{ $workLogStats['done'] }}</p>
        </div>
        <div class="ik-stat-card ik-accent-danger bg-white border rounded-lg p-3 mn-stagger-11">
            <div class="ik-stat-icon ik-stat-icon--danger"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" /></svg></div>
            <p class="text-[11px] text-[#676879]">Overdue Work</p>
            <p class="mt-0.5 text-lg font-bold text-[#e2445c]">{{ $workLogStats['overdue'] }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <div class="ik-stat-card ik-accent-neutral bg-white border rounded-lg p-3">
            <div class="ik-stat-icon ik-stat-icon--neutral"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" /></svg></div>
            <p class="text-[11px] text-[#676879]">Pending Reminders</p>
            <p class="mt-0.5 text-lg font-bold text-[#323338]">{{ $reminderStats['pending'] }}</p>
        </div>
        <div class="ik-stat-card ik-accent-info bg-white border rounded-lg p-3">
            <div class="ik-stat-icon ik-stat-icon--info"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg></div>
            <p class="text-[11px] text-[#676879]">Today Reminders</p>
            <p class="mt-0.5 text-lg font-bold text-[#0073ea]">{{ $reminderStats['today'] }}</p>
        </div>
        <div class="ik-stat-card ik-accent-danger bg-white border rounded-lg p-3">
            <div class="ik-stat-icon ik-stat-icon--danger"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" /></svg></div>
            <p class="text-[11px] text-[#676879]">Overdue Reminders</p>
            <p class="mt-0.5 text-lg font-bold text-[#e2445c]">{{ $reminderStats['overdue'] }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
        <div class="bg-white border border-[#e6e9ef] rounded-lg shadow-[0_1px_3px_rgba(0,0,0,0.04)] overflow-hidden">
            <div class="px-4 py-3 border-b border-[#e6e9ef] flex items-center justify-between">
                <div class="flex items-center">
                    <div class="ik-panel-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" /></svg></div>
                    <h2 class="text-sm font-semibold text-[#323338]">Today's Reminders</h2>
                </div>
                <a href="{{ url('/panel/reminders') }}" class="text-xs font-semibold text-[#0073ea] hover:underline">View all</a>
            </div>
            <div class="divide-y divide-[#edeef2]">
                @forelse ($todayReminders as $reminder)
                <div class="px-4 py-3 flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-1.5">
                            <span class="rounded bg-[#e5f1fd] px-1.5 py-0.5 text-[10px] font-semibold text-[#0073ea]">{{ str_replace('_', ' ', $reminder->reminder_type) }}</span>
                            <span class="text-[10px] text-[#9699a6]">{{ $reminder->reminder_at?->format('H:i') }}</span>
                        </div>
                        <p class="mt-1 text-sm font-medium text-[#323338] truncate">{{ $reminder->title }}</p>
                        <p class="mt-0.5 text-xs text-[#676879]">{{ $reminder->employee?->name ?? 'All' }}@if($reminder->department) · {{ $reminder->department->name }}@endif</p>
                    </div>
                    <span class="rounded bg-[#fef6e7] px-1.5 py-0.5 text-[10px] font-semibold text-[#fdab3d] shrink-0">{{ $reminder->status }}</span>
                </div>
                @empty
                <div class="ik-empty-state"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" /></svg><p>Tidak ada reminder hari ini</p></div>
                @endforelse
            </div>
        </div>

        <div class="bg-white border border-[#e6e9ef] rounded-lg shadow-[0_1px_3px_rgba(0,0,0,0.04)] overflow-hidden">
            <div class="px-4 py-3 border-b border-[#e6e9ef] flex items-center">
                <div class="ik-panel-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" /></svg></div>
                <h2 class="text-sm font-semibold text-[#323338]">Upcoming Reminders</h2>
            </div>
            <div class="divide-y divide-[#edeef2]">
                @forelse ($upcomingReminders as $reminder)
                <div class="px-4 py-3">
                    <div class="flex items-center gap-1.5">
                        <span class="rounded bg-[#f0f1f3] px-1.5 py-0.5 text-[10px] font-semibold text-[#676879]">{{ str_replace('_', ' ', $reminder->reminder_type) }}</span>
                        <span class="text-[10px] text-[#9699a6]">{{ $reminder->reminder_at?->format('d M Y H:i') }}</span>
                    </div>
                    <p class="mt-1 text-sm font-medium text-[#323338]">{{ $reminder->title }}</p>
                    <p class="mt-0.5 text-xs text-[#676879]">{{ $reminder->employee?->name ?? $reminder->department?->name ?? 'General' }}</p>
                </div>
                @empty
                <div class="ik-empty-state"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" /></svg><p>Belum ada upcoming reminder</p></div>
                @endforelse
            </div>
        </div>
    </div>

    @if ($overdueReminders->count() > 0)
    <div class="bg-white border border-[#e2445c]/30 rounded-lg shadow-[0_1px_3px_rgba(0,0,0,0.04)] overflow-hidden">
        <div class="px-4 py-3 border-b border-[#e2445c]/15 bg-[#fdebee] flex items-center">
            <div class="ik-panel-icon ik-panel-icon--danger"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" /></svg></div>
            <div>
                <h2 class="text-sm font-semibold text-[#e2445c]">Overdue Reminders</h2>
                <p class="text-xs text-[#e2445c]/70">Reminder yang sudah lewat waktunya.</p>
            </div>
        </div>
        <div class="divide-y divide-[#e2445c]/10">
            @foreach ($overdueReminders as $reminder)
            <div class="px-4 py-3 flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="flex items-center gap-1.5">
                        <span class="rounded bg-[#fdebee] px-1.5 py-0.5 text-[10px] font-semibold text-[#e2445c]">{{ str_replace('_', ' ', $reminder->reminder_type) }}</span>
                        <span class="text-[10px] text-[#e2445c]/60">{{ $reminder->reminder_at?->format('d M Y H:i') }}</span>
                    </div>
                    <p class="mt-1 text-sm font-medium text-[#323338]">{{ $reminder->title }}</p>
                    <p class="mt-0.5 text-xs text-[#676879]">{{ $reminder->employee?->name ?? 'All' }}@if($reminder->department) · {{ $reminder->department->name }}@endif</p>
                </div>
                <span class="rounded bg-[#e2445c] px-1.5 py-0.5 text-[10px] font-semibold text-white shrink-0">Overdue</span>
            </div>
            @endforeach
        </div>
    </div>
    @endif

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
