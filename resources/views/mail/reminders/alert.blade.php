<x-mail::message>
# Reminder H-{{ $daysBefore }}

Halo {{ $recipientName }},

The following reminder is still in **Pending* status and will reach the deadline in {{ $daysBefore }} days.

- **Title:* {{ $reminder->title }}
- **Type:* {{ str($reminder->reminder_type)->replace('_', ' ')->title() }}
- **Deadline:** {{ $reminder->reminder_at->format('d M Y H:i') }} WIB

@if ($reminder->workTask)
**Source Task:** {{ $reminder->workTask->task_no }} · {{ $reminder->workTask->title }}
@endif

@if ($reminder->description)
**Note:*

{{ $reminder->description }}
@endif

<x-mail::button :url="$viewUrl">
View reminder
</x-mail::button>

The next email will only be sent according to the selected alarm and will stop if the status is changed to Done or Cancel.

Greetings,

IK WorkDesk System
</x-mail::message>
