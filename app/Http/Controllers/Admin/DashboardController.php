<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Reminder;
use App\Models\Ticket;
use App\Models\WorkTask;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $today = Carbon::today();
        $now = Carbon::now();

        $serviceRequestStats = [
            'total' => Ticket::count(),
            'open' => Ticket::where('status', 'open')->count(),
            'in_progress' => Ticket::where('status', 'in_progress')->count(),
            'waiting_user' => Ticket::where('status', 'waiting_user')->count(),
            'resolved' => Ticket::where('status', 'resolved')->count(),
            'overdue' => Ticket::whereNotNull('due_at')
                ->whereDate('due_at', '<', $today)
                ->whereNotIn('status', ['resolved', 'closed', 'cancel'])
                ->count(),
        ];

        $workLogStats = [
            'total' => WorkTask::count(),
            'planned' => WorkTask::where('status', 'planned')->count(),
            'in_progress' => WorkTask::where('status', 'in_progress')->count(),
            'done' => WorkTask::where('status', 'done')->count(),
            'overdue' => WorkTask::whereNotNull('due_at')
                ->whereDate('due_at', '<', $today)
                ->whereNotIn('status', ['done', 'cancel'])
                ->count(),
        ];

        $latestRequests = Ticket::with([
                'employee',
                'requesterDepartment',
                'handlerDepartment',
                'category',
            ])
            ->latest()
            ->limit(5)
            ->get();

        $latestWorkLogs = WorkTask::with([
                'ticket',
                'employee',
                'department',
            ])
            ->latest()
            ->limit(5)
            ->get();

        $todayReminders = Reminder::with(['employee', 'department'])
            ->whereDate('reminder_at', $today)
            ->where('status', 'pending')
            ->orderBy('reminder_at')
            ->limit(10)
            ->get();

        $upcomingReminders = Reminder::with(['employee', 'department'])
            ->where('reminder_at', '>', $now)
            ->where('status', 'pending')
            ->orderBy('reminder_at')
            ->limit(5)
            ->get();

        $overdueReminders = Reminder::with(['employee', 'department'])
            ->where('reminder_at', '<', $now)
            ->where('status', 'pending')
            ->orderBy('reminder_at')
            ->limit(10)
            ->get();

        $reminderStats = [
            'pending' => Reminder::where('status', 'pending')->count(),
            'today' => Reminder::whereDate('reminder_at', $today)
                ->where('status', 'pending')
                ->count(),
            'overdue' => Reminder::where('reminder_at', '<', $now)
                ->where('status', 'pending')
                ->count(),
        ];

        return view('admin.dashboard', compact(
            'serviceRequestStats',
            'workLogStats',
            'latestRequests',
            'latestWorkLogs',
            'todayReminders',
            'upcomingReminders',
            'overdueReminders',
            'reminderStats'
        ));
    }
}