<section class="it-review-section" aria-labelledby="device-transfer-heading">
    <h2 id="device-transfer-heading">{{ __('Asset displacement history') }}</h2>
    <p class="it-review-help">{{ __('Current user:') }} {{ $this->getRecord()->assigneeLabel() }}{{ __('Name on history follows data while displacement is recorded.') }}</p>
    @php($transfers = $this->transferHistory())
    <div class="it-review-table-wrap" role="region" aria-label="{{ __('Asset displacement history') }}" tabindex="0">
        <table class="it-infrastructure-table">
            <thead><tr><th scope="col">{{ __('Transfer date') }}</th><th scope="col">{{ __('From user') }}</th><th scope="col">{{ __('To the user') }}</th><th scope="col">{{ __('Noted by') }}</th><th scope="col">{{ __('Handover notes') }}</th></tr></thead>
            <tbody>
                @forelse ($transfers as $transfer)
                    <tr><td>{{ $transfer->transferred_on->format('d/m/Y') }}</td><td>{{ $transfer->from_snapshot['name'] }}</td><td>{{ $transfer->to_snapshot['name'] }}</td><td>{{ $transfer->actor_snapshot['name'] }}<br>{{ $transfer->created_at->format('d/m/Y H:i') }}</td><td>{{ $transfer->notes }}</td></tr>
                @empty
                    <tr><td colspan="5">{{ __('No asset transfer has been recorded. The use before this feature is active is not created new history.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <x-filament::pagination :paginator="$transfers" />
</section>
<h2>{{ __('Check history') }}</h2>
<div class="it-review-table-wrap" role="region" aria-label="{{ __('Device history') }}" tabindex="0">
    <table class="it-infrastructure-table">
        <thead><tr><th scope="col">{{ __('Inspection') }}</th><th scope="col">{{ __('Time') }}</th><th scope="col">{{ __('Inspector') }}</th><th scope="col">{{ __('Documents') }}</th><th scope="col">{{ __('Condition') }}</th></tr></thead>
        <tbody>
            @forelse ($this->inspectionHistory() as $inspection)
                <tr>
                    <td><a class="it-review-link" href="{{ \App\Filament\Pages\ItInspectionPage::getUrl(['inspection' => $inspection->id]) }}">{{ $inspection->referenceNumber() }}</a></td>
                    <td>{{ $inspection->checked_at->format('d/m/Y H:i') }}</td><td>{{ $inspection->inspector_snapshot['name'] }}</td><td>{{ $inspection->status->label() }}</td>
                    <td><span class="it-review-status" data-condition="{{ $inspection->overall_condition->value }}">{{ $inspection->overall_condition->label() }}</span></td>
                </tr>
            @empty
                <tr><td colspan="5">{{ __('No check history yet for this device.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@if (\App\Filament\Pages\ItInfrastructureReportPage::canAccess())
    <div class="it-review-actions"><x-filament::button tag="a" color="gray" :href="\App\Filament\Pages\ItInfrastructureReportPage::getUrl(['deviceFilter' => $this->getRecord()->id, 'reportTab' => 'history'])">{{ __('View entire history') }}</x-filament::button></div>
@endif
