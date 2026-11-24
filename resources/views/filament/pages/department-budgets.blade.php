<x-filament-panels::page>
@vite('resources/css/scm.css')
<div class="scm-workspace">
    @if($missing)
        <section class="scm-panel"><p role="alert">{{ __('Budget data is not set up. Contact administrator.') }}</p></section>
    @else
        <section class="scm-panel"><h2>{{ __('Department budget') }}</h2>
            <p>{{ __('Budget allocation for departments you can access, separated by company, year, HO/project, COA and currency.') }}</p>
            <p role="status">{{ __('The balance takes into account the PO created and filing of the SPD. PO Payment converts commitment to realization. The approved LPJ replaces SPD commitment at actual cost.') }}</p>
            <div class="scm-scroll" role="region" aria-label="{{ __('Department budget list') }}" tabindex="0"><table><thead><tr><th scope="col">{{ __('Company / year') }}</th><th scope="col">HO / project</th><th scope="col">{{ __('Department') }}</th><th scope="col">COA</th><th scope="col">{{ __('Allocation') }}</th><th scope="col">{{ __('Actual spending') }}</th><th scope="col">{{ __('Commitment') }}</th><th scope="col">{{ __('Remaining') }}</th></tr></thead><tbody>
                @forelse($lines as $line)@php($usage = $line->budget_usage)<tr wire:key="department-budget-{{ $line->id }}"><td>{{ $line->scope->budget->company }} / {{ $line->scope->budget->year }}</td><td>{{ $line->scope->need_type === 'HO' ? 'HO' : ($line->scope->project?->code.' · '.$line->scope->project?->name) }}</td><td>{{ $line->department->name }}</td><td>{{ $line->account->code }} · {{ $line->account->name }}</td><td>{{ \App\Services\Scm\Decimal::money($line->amount_minor, $line->scope->budget->currency) }}</td><td>{{ \App\Services\Scm\Decimal::money($usage['realized_minor'], $line->scope->budget->currency) }}</td><td>{{ \App\Services\Scm\Decimal::money($usage['committed_minor'], $line->scope->budget->currency) }}</td><td>{{ \App\Services\Scm\Decimal::money($usage['available_minor'], $line->scope->budget->currency) }}</td></tr>
                @empty<tr><td colspan="8">{{ __('There\'s no budget allocation for departments that you can access.') }}</td></tr>@endforelse
            </tbody></table></div>{{ $lines->links() }}
        </section>
    @endif
</div>
</x-filament-panels::page>
