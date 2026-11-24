<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">{{ __('Office supply operations report') }}</x-slot>
        <x-slot name="description">{{ __('Export Excel provides monthly requests and details of requests, as well as a welcome repertoire per department, the stock of the main warehouse, department balance, and use.') }}</x-slot>

        <p class="text-sm text-gray-600">
            {{ __('First sheet,') }} <strong>{{ __('Monthly Summary') }}</strong>{{ __(', sums up the number of requests and the number of items requested, handed over, and accepted according to the month of demand, department, entity, goods, sizes and units. Canceled requests do not count. Use the initial and final date to limit the report period') }} <strong>{{ __('Department Summary') }}</strong> {{ __('keep adding up the items you\'ve received.') }}
        </p>
    </x-filament::section>
</x-filament-panels::page>
