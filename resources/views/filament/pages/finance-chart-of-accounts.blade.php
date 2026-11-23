<x-filament-panels::page>
    @vite('resources/css/scm.css')
    <div class="scm-workspace">
        @if($errors->any())<div class="scm-error" role="alert"><h2>Check the chart of accounts</h2><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <p role="status" wire:loading.delay>Loading or storing COA data...</p>
        @if($missing)
            <section class="scm-panel"><p role="alert">The Administrator needs to run migration.</p></section>
        @elseif($loadError)
            <section class="scm-panel"><p role="alert">COA data has not been loaded. Retry or contact administrator.</p><button type="button" wire:click="$refresh">Try again</button></section>
        @else
            <section class="scm-panel"><header class="scm-heading"><div><h2>Master COA</h2><p>Manage account codes for each company.</p></div><button type="button" wire:click="newAccount">Add COA</button></header>
                <x-scm.field model="search" label="Find an account code or name" :live="true" />
                <div class="scm-scroll" role="region" aria-label="COA List" tabindex="0"><table><thead><tr><th scope="col">Company</th><th scope="col">Kode COA</th><th scope="col">Account name</th><th scope="col">Parent</th><th scope="col">Level</th><th scope="col">Status</th><th scope="col">Action</th></tr></thead><tbody>
                    @forelse($accounts as $row)<tr wire:key="coa-{{ $row->id }}"><td>{{ $row->company }}</td><td>{{ $row->code }}</td><td>{{ $row->name }}</td><td>{{ $row->parent ? $row->parent->code.' · '.$row->parent->name : 'Parent account' }}</td><td>{{ $row->parent ? ($row->parent->parent ? 3 : 2) : 1 }}</td><td>{{ $row->is_active ? 'Active' : 'Inactive' }}</td><td><button type="button" wire:click="editAccount({{ $row->id }})">Edit {{ $row->code }}</button></td></tr>
                    @empty<tr><td colspan="7">No suitable COAs yet, add accounts to the Finance list.</td></tr>@endforelse
                </tbody></table></div>{{ $accounts->links() }}
            </section>
            <section class="scm-panel"><h2>{{ $accountId ? 'Edit COA' : 'Add COA' }}</h2>
                <form wire:submit="saveAccount"><fieldset wire:loading.attr="disabled" wire:target="saveAccount"><legend>Account data</legend><div class="scm-grid">
                    <x-scm.select model="account.company" label="Company" :options="collect(config('scm.companies'))->mapWithKeys(fn ($item, $code) => [$code => $code])->all()" :live="true" required />
                    <x-scm.field model="account.code" label="Kode COA" required />
                    <x-scm.field model="account.name" label="Account name" required />
                    <x-scm.select model="account.parent_id" label="Parent account" placeholder="No parent (level 1)" :options="$parents->mapWithKeys(fn ($a) => [$a->id => $a->code.' · '.$a->name])->all()" help="Leave blank for a top-level account. You can use accounts at any of the three levels." />
                    <x-scm.check model="account.is_active" label="Active account" />
                </div><div class="scm-actions"><x-filament::button type="submit" wire:loading.attr="disabled" wire:target="saveAccount">Save COA</x-filament::button>@if($accountId)<button type="button" wire:click="newAccount">Cancel edit</button>@endif</div></fieldset></form>
            </section>
        @endif
    </div>
</x-filament-panels::page>
