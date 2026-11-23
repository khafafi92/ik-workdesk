<x-filament-panels::page>
@vite('resources/css/scm.css')
<div class="scm-workspace">
    @if($errors->any())<div class="scm-error" role="alert"><h2>Check the budget</h2><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <p role="status" wire:loading.delay>Loading or saving budget...</p>
    @if($missing)<section class="scm-panel"><p role="alert">Administrator needs to run migrations.</p></section>
    @else
    <section class="scm-panel"><h2>The annual primary budget</h2><p>Allocate the company's budget to the HO or project, then to the department and the COA.</p>
        <p role="status">PR/SR menjadi komitmen saat PO dibuat. Pembayaran memindahkan nilai komitmen menjadi realisasi. SPD menjadi komitmen saat diajukan, lalu memakai nilai LPJ setelah LPJ disetujui. Pembatalan PO melepas komitmennya.</p>
        <form wire:submit="createBudget"><fieldset wire:loading.attr="disabled"><legend>Add primary budget</legend><div class="scm-grid">
            <x-scm.select model="budget.company" label="Company" :options="collect(config('scm.companies'))->mapWithKeys(fn ($item, $code) => [$code => $code])->all()" required />
            <x-scm.field model="budget.year" label="Year" type="number" min="2000" max="2100" required />
            <x-scm.select model="budget.currency" label="Currency" :options="collect(config('scm.currencies'))->mapWithKeys(fn ($currency) => [$currency => $currency])->all()" required />
            <x-scm.field model="budget.amount" label="Main budget" inputmode="decimal" required help="Use a comma for decimals and omit thousands separators." />
        </div><div class="scm-actions"><x-filament::button type="submit">Save budget</x-filament::button></div></fieldset></form>
        <div class="scm-scroll" role="region" aria-label="Main budget list" tabindex="0"><table><thead><tr><th>Company</th><th>Year</th><th>Main budget</th><th>Action</th></tr></thead><tbody>
        @forelse($budgets as $row)<tr><td>{{ $row->company }}</td><td>{{ $row->year }}</td><td>{{ \App\Services\Scm\Decimal::money($row->amount_minor, $row->currency) }}</td><td><button type="button" wire:click="openBudget({{ $row->id }})">Open {{ $row->company }} {{ $row->year }} {{ $row->currency }}</button></td></tr>@empty<tr><td colspan="4">No major budget yet.</td></tr>@endforelse
        </tbody></table></div>
    </section>
    @if($current)
    @php($budgetReportByLine = $budgetReport->keyBy(fn ($row) => $row['line']->id))
    <section class="scm-panel"><h2>Allocation {{ $current->company }} {{ $current->year }} · {{ $current->currency }}</h2>
        <p>Main budget: {{ \App\Services\Scm\Decimal::money($current->amount_minor, $current->currency) }} · Unallocated to HO/project: {{ \App\Services\Scm\Decimal::money($current->amount_minor - $current->scopes->sum('amount_minor'), $current->currency) }}</p>
        <div class="scm-actions"><button type="button" wire:click="editAmount('budget', {{ $current->id }})">Change primary budget</button></div>
        @if($editLevel)<form wire:submit="saveAmount"><fieldset wire:loading.attr="disabled"><legend>Edit amount {{ ['budget' => 'primary budget', 'scope' => 'HO / project', 'line' => 'departemen / COA'][$editLevel] }}</legend><div class="scm-grid"><x-scm.field model="editAmount" label="Nominal baru ({{ $current->currency }})" inputmode="decimal" required /></div><div class="scm-actions"><x-filament::button type="submit">Save changes</x-filament::button><button type="button" wire:click="cancelEdit">Cancel</button></div></fieldset></form>@endif
        <form wire:submit="createScope"><fieldset wire:loading.attr="disabled"><legend>Add HO / project allocation</legend><div class="scm-grid">
            <x-scm.select model="scope.need_type" label="The kind of needs" :options="['HO' => 'HO', 'PROJECT' => 'Project']" :live="true" required />
            @if(($scope['need_type'] ?? '') === 'PROJECT')<x-scm.select model="scope.project_numbering_id" label="Project" :options="$projects->mapWithKeys(fn ($p) => [$p->id => $p->code.' · '.$p->name])->all()" required />@endif
            <x-scm.field model="scope.amount" label="Anggaran HO / project ({{ $current->currency }})" inputmode="decimal" required />
        </div><div class="scm-actions"><x-filament::button type="submit">Save allocation</x-filament::button></div></fieldset></form>
        @forelse($current->scopes as $item)<div class="scm-item"><h3>{{ $item->need_type === 'HO' ? 'HO' : ($item->project?->code.' · '.$item->project?->name) }}</h3>
            <p>Budgets: {{ \App\Services\Scm\Decimal::money($item->amount_minor, $current->currency) }} · Unallocated to department: {{ \App\Services\Scm\Decimal::money($item->amount_minor - $item->lines->sum('amount_minor'), $current->currency) }}</p>
            <div class="scm-actions"><button type="button" wire:click="editAmount('scope', {{ $item->id }})">Change budget {{ $item->need_type === 'HO' ? 'HO' : $item->project?->code }}</button></div>
            <div class="scm-scroll" role="region" aria-label="Department and COA allocation" tabindex="0"><table><thead><tr><th>Department</th><th>COA</th><th>Allocation</th><th>Actual spending</th><th>Commitment</th><th>Remaining</th><th>Action</th></tr></thead><tbody>
            @forelse($item->lines as $allocation)@php($usage = $budgetReportByLine->get($allocation->id))<tr><td>{{ $allocation->department->name }}</td><td>{{ $allocation->account->code }} · {{ $allocation->account->name }}</td><td>{{ \App\Services\Scm\Decimal::money($allocation->amount_minor, $current->currency) }}</td><td>{{ \App\Services\Scm\Decimal::money($usage['realized_minor'] ?? 0, $current->currency) }}</td><td>{{ \App\Services\Scm\Decimal::money($usage['committed_minor'] ?? 0, $current->currency) }}</td><td>{{ \App\Services\Scm\Decimal::money($usage['available_minor'] ?? $allocation->amount_minor, $current->currency) }}</td><td><button type="button" wire:click="editAmount('line', {{ $allocation->id }})">Ubah {{ $allocation->department->name }} · {{ $allocation->account->code }}</button></td></tr>@empty<tr><td colspan="7">Belum ada alokasi departemen.</td></tr>@endforelse
            </tbody></table></div></div>@empty<p>No HO or project allocation yet.</p>@endforelse
        <form wire:submit="createLine"><fieldset wire:loading.attr="disabled"><legend>Add department and COA allocation</legend><div class="scm-grid">
            <x-scm.select model="line.scope_id" label="HO / project" :options="$current->scopes->mapWithKeys(fn ($s) => [$s->id => $s->need_type === 'HO' ? 'HO' : ($s->project?->code.' · '.$s->project?->name)])->all()" required />
            <x-scm.select model="line.department_id" label="Department" :options="$departments->pluck('name', 'id')" required />
            <x-scm.select model="line.account_id" label="COA" :options="$accounts->mapWithKeys(fn ($a) => [$a->id => $a->code.' · '.$a->name])->all()" required />
            <x-scm.field model="line.amount" label="Anggaran departemen / COA ({{ $current->currency }})" inputmode="decimal" required />
        </div><div class="scm-actions"><x-filament::button type="submit">Save department allocation</x-filament::button></div></fieldset></form>
    </section>
    <section class="scm-panel"><h2>Budget usage details</h2>
        <p>The active pos are listed as commitment until paid. and switch to actual value after LPJ is approved.</p>
        <div class="scm-actions"><button type="button" wire:click="exportBudget" wire:loading.attr="disabled" wire:target="exportBudget">Export to Excel</button></div>
        <div class="scm-scroll" role="region" aria-label="Details of PR, SR, and SPD usage" tabindex="0"><table><thead><tr><th>Date</th><th>Division</th><th>Formation</th><th>Type / document number</th><th>Project</th><th>Status</th><th>Document value</th><th>Actual spending</th><th>Commitment</th></tr></thead><tbody>
            @php($entries = $budgetReport->flatMap(fn ($row) => collect($row['entries'])->map(fn ($entry) => [...$entry, 'account' => $row['line']->account->code.' · '.$row['line']->account->name]))->sortBy('date'))
            @forelse($entries as $entry)<tr><td>{{ $entry['date']->format('d/m/Y') }}<br>{{ $entry['date']->format('m/Y') }}</td><td>{{ $entry['department'] }}</td><td>{{ $entry['account'] }}</td><td>{{ $entry['kind'] }} · {{ $entry['number'] }}@if($entry['related_number'])<br>{{ $entry['kind'] === 'SPD' ? 'LPJ' : 'PO' }} · {{ $entry['related_number'] }}@endif</td><td>{{ $entry['project'] ?: 'HO' }}</td><td>{{ $entry['status'] }}</td><td>{{ \App\Services\Scm\Decimal::money($entry['amount_minor'], $current->currency) }}</td><td>{{ \App\Services\Scm\Decimal::money($entry['realized_minor'], $current->currency) }}</td><td>{{ \App\Services\Scm\Decimal::money($entry['committed_minor'], $current->currency) }}</td></tr>
            @empty<tr><td colspan="9">No PO or SPD has yet used this budget allocation.</td></tr>@endforelse
        </tbody></table></div>
    </section>
    @endif
    @endif
</div>
</x-filament-panels::page>
