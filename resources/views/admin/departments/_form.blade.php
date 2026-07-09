@props([
'department' => null,
'action',
'method' => 'POST',
])

<form method="POST" action="{{ $action }}" class="space-y-5">
    @csrf

    @if ($method !== 'POST')
    @method($method)
    @endif

    <x-ui.input label="Code" name="code" :value="$department?->code" placeholder="ICT" required />

    <x-ui.input label="Name" name="name" :value="$department?->name" placeholder="Information Communication Technology"
        required />

    <label class="flex items-center gap-3">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $department?->is_active ?? true))
        class="rounded border-slate-300 text-slate-900 focus:ring-slate-900"
        >

        <span class="text-sm font-semibold text-slate-700">
            Active
        </span>
    </label>

    <div class="flex items-center gap-3 pt-4">
        <x-ui.button type="submit">
            {{ $method === 'POST' ? 'Save' : 'Update' }}
        </x-ui.button>

        <x-ui.button variant="secondary" :href="route('admin.departments.index')">
            Cancel
        </x-ui.button>
    </div>
</form>