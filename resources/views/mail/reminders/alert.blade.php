<x-mail::message>
# {{ __('Reminder H-:days', ['days' => $daysBefore]) }}

{{ __('Hello :name,', ['name' => $recipientName]) }}

{{ __('The following reminder is pending.') }}

- **{{ __('Title') }}:** {{ $reminder->title }}
- **{{ __('Type') }}:** {{ str($reminder->reminder_type)->replace('_', ' ')->title() }}
- **{{ __('Deadline') }}:** {{ $reminder->reminder_at->format('d M Y H:i') }} WIB

@if ($reminder->workTask)
**{{ __('Source task') }}:** {{ $reminder->workTask->task_no }} ? {{ $reminder->workTask->title }}
@endif

@if ($reminder->description)
**{{ __('Note') }}:**

{{ $reminder->description }}
@endif

<x-mail::button :url="$viewUrl">
{{ __('View reminder') }}
</x-mail::button>

{{ __('Further emails follow your selected alarms and stop when the reminder is completed or cancelled.') }}
</x-mail::message>
