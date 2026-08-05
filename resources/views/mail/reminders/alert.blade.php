<x-mail::message>
# Pengingat H-{{ $daysBefore }}

Halo {{ $recipientName }},

Reminder berikut masih berstatus **Pending** dan akan mencapai deadline dalam {{ $daysBefore }} hari.

- **Judul:** {{ $reminder->title }}
- **Tipe:** {{ str($reminder->reminder_type)->replace('_', ' ')->title() }}
- **Deadline:** {{ $reminder->reminder_at->format('d M Y H:i') }} WIB

@if ($reminder->workTask)
**Source Task:** {{ $reminder->workTask->task_no }} · {{ $reminder->workTask->title }}
@endif

@if ($reminder->description)
**Catatan:**

{{ $reminder->description }}
@endif

<x-mail::button :url="$viewUrl">
Lihat Reminder
</x-mail::button>

Email berikutnya hanya akan dikirim sesuai alarm yang dipilih dan akan berhenti jika status diubah menjadi Done atau Cancel.

Salam,

IK WorkDesk System
</x-mail::message>
