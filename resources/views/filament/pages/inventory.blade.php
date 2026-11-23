<x-filament-panels::page>
    <div class="scm-workspace">
        <nav class="scm-actions" aria-label="Inventory data">
            @foreach(['catalogue'=>'Master of Things', 'warehouses'=>'Warehouse', 'balances'=>'Stock per warehouse', 'receipts'=>'Reception', 'requests'=>'Register PR/SR', 'assets'=>'Aset/perangkat', 'movements'=>'Mutation history'] as $key=>$label)
                @if(($key !== 'assets' || $canViewAssets) && ($key !== 'receipts' || $canAdminister))<button type="button" wire:click="switchDataset('{{ $key }}')" @if($dataset === $key) aria-current="page" @endif>{{ $label }}</button>@endif
            @endforeach
            @if($canViewStock)<button type="button" wire:click="switchDataset('stock')" @if($dataset === 'stock') aria-current="page" @endif>Office Supply Summary</button>@endif
        </nav>
        <p role="status" wire:loading.delay>Loading or storing inventory data...</p>
        @if($errors->any())<div class="scm-error" role="alert"><h2>Check the details below</h2><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @if($formMode !== '') @include('filament.pages.inventory.forms') @endif
        <section class="scm-panel" aria-label="Inventory data according to access">
            <header class="scm-heading">
                <h2>{{ ['catalogue'=>'Master of Things', 'warehouses'=>'The Ho and Project warehouse', 'balances'=>'Stock per warehouse', 'receipts'=>'Reception by PO', 'requests'=>'PR/SR HO dan Project', 'assets'=>'Asset and device units', 'movements'=>'The history of mutation goods', 'stock'=>'ATHK stock summary'][$dataset] }}</h2>
                <div class="scm-actions">
                    @if($dataset === 'catalogue' && $editableKinds)<button type="button" wire:click="newItem">Add item</button>@endif
                    @if($dataset === 'balances' && $canAdminister)<button type="button" wire:click="newStock">Note initial/in stock</button>@endif
                    @if($dataset === 'warehouses' && $canAdminister)<button type="button" wire:click="syncWarehouses">Sync Project warehouse</button>@endif
                    @if($dataset === 'assets' && $canCreateAsset)<a href="{{ \App\Filament\Resources\ItDevices\ItDeviceResource::getUrl('create') }}">Register device</a>@endif
                    @if($dataset === 'stock' && $stockUrl)<a href="{{ $stockUrl }}">Office supplies Settings and stock</a>@endif
                </div>
            </header>
            <p>{{ match($dataset) {
                'catalogue'=>'One master is used for purchase requests, office supplies items and device types. The asset number and serial number are recorded per unit.',
                'warehouses'=>'The HO warehouse and every Project records separate stock for KPMOG and APCA.',
                'balances'=>'The balance follows entity and department access, separated per item and storage. The old office supplies balance needs to be mapped before per entity. The device\'s stock contains unregistered units; the unit is listed on the Asset/device.',
                'requests'=>'Inventory officials record items based on the PO to the main warehouse of the HO or Project.',
                'receipts'=>'Select purchase requests with PO to record items received in the main warehouse of the HO or Project.',
                'assets'=>'Every row represents a unit, registration from stock reduces the number of unlisted units in the warehouse.',
                'movements'=>'History records the balance before and after, the execution, as well as transaction references. Data follows your access.',
                default=>'Quantity is a total office supplies stock that\'s still in the warehouse.'
            } }}</p>
            @if($viewAll && in_array($dataset, ['balances','requests','receipts','assets','movements']))<p class="scm-help">Use the company filter to view KPMOG or APCA. Each company has its own stock balances.</p>@endif
            <div class="scm-grid">
                <x-scm.field model="search" label="Search code, number, or name" :live="true" />
                @if(in_array($dataset, ['warehouses','balances','requests','receipts','assets','movements']))
                    <x-scm.select model="companyFilter" label="Entity" :options="['KPMOG'=>'KPMOG','APCA'=>'APCA']" :live="true" />
                    @if($dataset !== 'assets')<x-scm.select model="needTypeFilter" label="The kind of needs" :options="['HO'=>'HO','PROJECT'=>'Project']" :live="true" />@endif
                    @if(!in_array($dataset, ['requests','receipts']))<x-scm.select model="warehouseFilter" label="Gudang" :options="$warehouseOptions" :live="true" />@endif
                @endif
                @if($dataset === 'catalogue')<x-scm.select model="kindFilter" label="Type of goods" :options="['atk'=>'ATK','material'=>'Material/barang','device'=>'Perangkat/aset']" :live="true" />@endif
                @if($dataset === 'requests')<x-scm.select model="kindFilter" label="Documents" :options="['PR'=>'PR','SR'=>'SR']" :live="true" />@endif
            </div>
            <div class="scm-actions"><button type="button" wire:click="clearFilters" wire:loading.attr="disabled">Delete filter</button></div>
            @if($loadError)
                <div class="scm-error" role="alert"><p>Inventory has not been loaded. Try reloading data.</p><button type="button" wire:click="$refresh" wire:loading.attr="disabled">Reload data</button></div>
            @else
                <div class="scm-scroll" role="region" aria-label="Inventory table" tabindex="0">
                    <table>
                        @if($dataset === 'catalogue')
                            <thead><tr><th scope="col">Codes and stuff</th><th scope="col">Type</th><th scope="col">Merek/model</th><th scope="col">Unit</th><th scope="col">Action</th></tr></thead>
                            <tbody>@forelse($rows as $item)<tr wire:key="catalogue-{{ $item->id }}">
                                <td>{{ $item->code }}<br>{{ $item->name }}{{ $item->is_active ? '' : ' (disabled)' }}<br>{{ $item->size }}</td>
                                <td>{{ ['atk'=>'ATK','material'=>'Material/barang','device'=>'Perangkat/aset'][$item->kind] }}<br>{{ $item->category }}</td>
                                <td>{{ $item->brand }}<br>{{ $item->model }}</td><td>{{ $item->unit }}</td>
                                <td>@if(\App\Services\Inventory\InventoryAccess::editItem(auth()->user(), $item->kind))<button type="button" wire:click="editItem({{ $item->id }})">Edit item</button>@endif
                                    @if($item->atkItem && \App\Filament\Resources\AtkItems\AtkItemResource::canEdit($item->atkItem))<a href="{{ \App\Filament\Resources\AtkItems\AtkItemResource::getUrl('edit',['record'=>$item->atkItem]) }}">Office Supply Settings</a>@endif</td>
                            </tr>@empty<tr><td colspan="5">Add the master of goods to your authority.</td></tr>@endforelse</tbody>
                        @elseif($dataset === 'warehouses')
                            <thead><tr><th scope="col">Warehouse</th><th scope="col">Entity</th><th scope="col">HO/Project</th><th scope="col">Status</th><th scope="col">Action</th></tr></thead>
                            <tbody>@forelse($rows as $warehouse)<tr wire:key="warehouse-{{ $warehouse->id }}"><td>{{ $warehouse->label() }}<br>{{ $warehouse->code }}</td><td>{{ $warehouse->company ?? 'Require mapping' }}</td><td>{{ $warehouse->kind === 'HO' ? 'HO' : 'Project' }}<br>{{ $warehouse->project?->code }}</td><td>{{ $warehouse->is_active && ($warehouse->kind === 'HO' || $warehouse->project?->is_active) ? 'Active' : 'Inactive' }}</td><td>@if($canAdminister && !$warehouse->legacy_atk)<button type="button" wire:click="toggleWarehouse({{ $warehouse->id }})" wire:confirm="Ubah status aktif gudang ini?">{{ $warehouse->is_active ? 'Deactivate' : 'Activate' }}</button>@endif</td></tr>@empty<tr><td colspan="5">There's no warehouse on the filter.</td></tr>@endforelse</tbody>
                        @elseif($dataset === 'balances')
                            <thead><tr><th scope="col">Item</th><th scope="col">Gudang/entitas</th><th scope="col">PR Department of Origin</th><th scope="col">Available</th><th scope="col">Action</th></tr></thead>
                            <tbody>@forelse($rows as $balance)<tr wire:key="balance-{{ $balance->id }}"><td>{{ $balance->item->code }}<br>{{ $balance->item->name }}</td><td>{{ $balance->warehouse->label() }}</td><td>{{ $balance->department?->name ?? ($balance->warehouse->legacy_atk ? 'Unmapped' : 'Stock warehouse') }}</td><td>{{ $balance->quantity_milli / 1000 }} {{ $balance->item->unit }}</td><td>
                                @if($balance->warehouse->legacy_atk && $balance->quantity_milli > 0 && ($canAdminister || auth()->user()->hasPermission('atk.manage')) && $balance->item->atkItem)
                                    <button type="button" wire:click="openAllocation({{ $balance->item->atkItem->id }})">Map old office supplies balance</button>
                                @elseif(!$balance->warehouse->legacy_atk && ($canAdminister || \App\Services\Inventory\InventoryAccess::issueDepartment(auth()->user(), $balance)))<button type="button" wire:click="openStock({{ $balance->id }})">{{ $canAdminister ? 'Note mutation' : 'Record distribution' }}</button>@endif
                            </td></tr>@empty<tr><td colspan="5">No balance on your filter or access yet.</td></tr>@endforelse</tbody>
                        @elseif($dataset === 'movements')
                            <thead><tr><th scope="col">Waktu/pelaksana</th><th scope="col">Barang/gudang</th><th scope="col">Transaction</th><th scope="col">Quantity</th><th scope="col">Sebelum/sesudah</th><th scope="col">Reference</th></tr></thead>
                            <tbody>@forelse($rows as $movement)<tr wire:key="movement-{{ $movement->id }}"><td>{{ $movement->created_at->format('d/m/Y H:i') }}<br>{{ $movement->actor?->name ?? 'Migration balance' }}</td><td>{{ $movement->balance->item->name }}<br>{{ $movement->balance->warehouse->label() }}</td><td>{{ ['opening'=>'Start balance','atk_sync'=>'Old office supplies mutation','atk_issue'=>'Office Supply Issue','pr_receipt'=>'PR admissions','incoming'=>'Stock in','outgoing'=>'Stock out','adjustment'=>'Adjustment','transfer_out'=>'Transfer out','transfer_in'=>'Transfer in','allocation_out'=>'Mapping balances out','allocation_in'=>'Mapping in balance','asset_registration'=>'Asset registration'][$movement->type] ?? $movement->type }}</td><td>{{ $movement->quantity_milli / 1000 }} {{ $movement->balance->item->unit }}</td><td>{{ $movement->balance_before_milli / 1000 }} / {{ $movement->balance_after_milli / 1000 }}</td><td>{{ $movement->notes }}@if($movement->receipt_id)<br>Penerimaan #{{ $movement->receipt_id }}<br>PR: {{ $movement->receipt?->request?->number }}@if($movement->receipt?->purchaseOrder)<br>PO: {{ $movement->receipt->purchaseOrder->number }}@endif @endif</td></tr>@empty<tr><td colspan="6">There has been no mutation according to your filter and access.</td></tr>@endforelse</tbody>
                        @elseif($dataset === 'assets')
                            <thead><tr><th scope="col">Asset number</th><th scope="col">Perangkat/master</th><th scope="col">Serial number</th><th scope="col">Pemakai/status</th><th scope="col">Original warehouse</th><th scope="col">Action</th></tr></thead>
                            <tbody>@forelse($rows as $device)<tr wire:key="asset-{{ $device->id }}"><td>{{ $device->asset_number }}</td><td>{{ $device->name }}<br>{{ $device->inventoryItem?->code }} / {{ $device->inventoryItem?->name }}</td><td>{{ $device->serial_number }}</td><td>{{ $device->assigned_user_name }}<br>{{ $device->lifecycle_status?->label() }}</td><td>{{ $device->inventoryWarehouse?->label() ?? 'Old / unrecorded unit from stock' }}</td><td>@can('view',$device)<a href="{{ \App\Filament\Resources\ItDevices\ItDeviceResource::getUrl('view',['record'=>$device]) }}">Buka perangkat</a>@endcan</td></tr>@empty<tr><td colspan="6">There is no unit of assets matching your filter and access.</td></tr>@endforelse</tbody>
                        @elseif($dataset === 'stock')
                            <thead><tr><th scope="col">Kode/barang</th><th scope="col">Quantity total warehouse</th><th scope="col">Actual</th><th scope="col">Unit</th><th scope="col">Mapping</th></tr></thead>
                            <tbody>@forelse($rows as $item)<tr wire:key="atk-stock-{{ $item->id }}"><td>{{ $item->code }}<br>{{ $item->name }}<br>{{ $item->size }}</td><td>{{ (float)$item->current_stock }}</td><td>{{ $item->actual_stock }}</td><td>{{ $item->unit }}</td><td>@if($canAdminister || auth()->user()->hasPermission('atk.manage'))<button type="button" wire:click="openAllocation({{ $item->id }})">Check entity mapping</button>@endif</td></tr>@empty<tr><td colspan="5">No office supplies matches. Change search or delete filters.</td></tr>@endforelse</tbody>
                        @else
                            <thead><tr><th scope="col">PR/SR</th><th scope="col">Entitas/kebutuhan</th><th scope="col">Requester</th><th scope="col">Barang/jasa</th><th scope="col">Stage</th><th scope="col">Action</th></tr></thead>
                            <tbody>@forelse($rows as $request)<tr wire:key="inventory-request-{{ $request->id }}"><td>{{ $request->number }}<br>@if($request->purchaseOrder)PO: {{ $request->purchaseOrder->number }}<br>@endif{{ $request->date?->format('d/m/Y') }}<br>{{ $request->sourceAtkRequest?->request_number }}</td><td>{{ $request->company }} / {{ $request->need_type === 'PROJECT' ? 'Project' : 'HO' }}<br>{{ $request->projectNumbering?->name }}</td><td>{{ $request->requester?->name }}<br>{{ $request->department?->name }}</td><td>@foreach($request->items as $item)<div>{{ $item->name }}: {{ $item->quantity_milli / 1000 }} {{ $item->unit }}</div>@endforeach</td><td>{{ match($request->status) {'submitted'=>'Waiting for reception '.($request->need_type === 'HO'?'Purchasing Officer':'SCM'),'sourcing'=>'Received '.($request->need_type === 'HO'?'Purchasing Officer':'SCM'), default=>$statuses[$request->status]??$request->status} }}</td><td>
                                @if($url=\App\Filament\Pages\Inventory::procurementUrl($request))<a href="{{ $url }}">Open {{ $request->kind }}</a>@endif
                                @if($canAdminister && $request->kind === 'PR' && in_array($request->status,['selected','legal','completed']) && $request->cbe?->status === 'final' && $request->purchaseOrder)<button type="button" wire:click="openReceipt({{ $request->id }})">Catat barang diterima di gudang</button>@endif
                            </td></tr>@empty<tr><td colspan="6">No PR/SR according to your filter and access yet. Change filters or make requests via the PR/SR menu.</td></tr>@endforelse</tbody>
                        @endif
                    </table>
                </div>
                <p class="scm-help">Move the table sideways to see other columns.</p>
                {{ $rows->links() }}
            @endif
        </section>
    </div>
</x-filament-panels::page>
