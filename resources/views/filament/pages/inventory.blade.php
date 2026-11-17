<x-filament-panels::page>
    <div class="scm-workspace">
        <nav class="scm-actions" aria-label="Data inventori">
            @foreach(['catalogue'=>'Master Barang', 'warehouses'=>'Gudang', 'balances'=>'Stok per gudang', 'receipts'=>'Penerimaan barang', 'requests'=>'Register PR/SR', 'assets'=>'Aset/perangkat', 'movements'=>'Riwayat mutasi'] as $key=>$label)
                @if(($key !== 'assets' || $canViewAssets) && ($key !== 'receipts' || $canAdminister))<button type="button" wire:click="switchDataset('{{ $key }}')" @if($dataset === $key) aria-current="page" @endif>{{ $label }}</button>@endif
            @endforeach
            @if($canViewStock)<button type="button" wire:click="switchDataset('stock')" @if($dataset === 'stock') aria-current="page" @endif>Ringkasan ATK</button>@endif
        </nav>
        <p role="status" wire:loading.delay>Memuat atau menyimpan data inventori...</p>
        @if($errors->any())<div class="scm-error" role="alert"><h2>Periksa data berikut</h2><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @if($formMode !== '') @include('filament.pages.inventory.forms') @endif
        <section class="scm-panel" aria-label="Data inventori sesuai akses">
            <header class="scm-heading">
                <h2>{{ ['catalogue'=>'Master Barang', 'warehouses'=>'Gudang HO dan Project', 'balances'=>'Stok per gudang', 'receipts'=>'Penerimaan barang berdasarkan PO', 'requests'=>'PR/SR HO dan Project', 'assets'=>'Unit aset dan perangkat', 'movements'=>'Riwayat mutasi barang', 'stock'=>'Ringkasan stok ATK'][$dataset] }}</h2>
                <div class="scm-actions">
                    @if($dataset === 'catalogue' && $editableKinds)<button type="button" wire:click="newItem">Tambah barang</button>@endif
                    @if($dataset === 'balances' && $canAdminister)<button type="button" wire:click="newStock">Catat stok awal/masuk</button>@endif
                    @if($dataset === 'warehouses' && $canAdminister)<button type="button" wire:click="syncWarehouses">Sinkronkan gudang Project</button>@endif
                    @if($dataset === 'assets' && $canCreateAsset)<a href="{{ \App\Filament\Resources\ItDevices\ItDeviceResource::getUrl('create') }}">Daftarkan unit perangkat</a>@endif
                    @if($dataset === 'stock' && $stockUrl)<a href="{{ $stockUrl }}">Pengaturan dan stok ATK</a>@endif
                </div>
            </header>
            <p>{{ match($dataset) {
                'catalogue'=>'Satu master digunakan untuk item PR, ATK, dan jenis perangkat. Nomor aset serta serial number dicatat per unit.',
                'warehouses'=>'Gudang HO dan setiap Project mencatat stok terpisah untuk KPMOG dan APCA.',
                'balances'=>'Saldo mengikuti akses entitas dan departemen, dipisahkan per barang dan gudang. Saldo ATK lama perlu dipetakan sebelum dipakai per entitas. Stok perangkat berisi unit yang belum didaftarkan; unit terdaftar tersedia di Aset/perangkat.',
                'requests'=>'Petugas inventori mencatat barang berdasarkan PO ke gudang utama HO atau Project. SR mencatat kebutuhan jasa.',
                'receipts'=>'Pilih PR dengan PO untuk mencatat barang yang diterima di gudang utama HO atau Project.',
                'assets'=>'Setiap baris mewakili satu unit. Pendaftaran dari stok mengurangi jumlah unit yang belum didaftarkan di gudang.',
                'movements'=>'Riwayat mencatat saldo sebelum dan sesudah, pelaksana, serta referensi transaksi. Data mengikuti akses Anda.',
                default=>'Quantity adalah total stok ATK yang masih berada di gudang. Actual tetap hasil pemeriksaan fisik. Konfirmasi penerimaan peminta tetap melalui Permintaan ATK.'
            } }}</p>
            @if($viewAll && in_array($dataset, ['balances','requests','receipts','assets','movements']))<p class="scm-help">Tampilan seluruh entitas sesuai izin inventori. Gunakan filter untuk melihat KPMOG atau APCA; saldonya tetap terpisah.</p>@endif
            <div class="scm-grid">
                <x-scm.field model="search" label="Cari kode, nomor, atau nama" :live="true" />
                @if(in_array($dataset, ['warehouses','balances','requests','receipts','assets','movements']))
                    <x-scm.select model="companyFilter" label="Entitas" :options="['KPMOG'=>'KPMOG','APCA'=>'APCA']" :live="true" />
                    @if($dataset !== 'assets')<x-scm.select model="needTypeFilter" label="Jenis kebutuhan" :options="['HO'=>'HO','PROJECT'=>'Project']" :live="true" />@endif
                    @if(!in_array($dataset, ['requests','receipts']))<x-scm.select model="warehouseFilter" label="Gudang" :options="$warehouseOptions" :live="true" />@endif
                @endif
                @if($dataset === 'catalogue')<x-scm.select model="kindFilter" label="Jenis barang" :options="['atk'=>'ATK','material'=>'Material/barang','device'=>'Perangkat/aset']" :live="true" />@endif
                @if($dataset === 'requests')<x-scm.select model="kindFilter" label="Dokumen" :options="['PR'=>'PR','SR'=>'SR']" :live="true" />@endif
            </div>
            <div class="scm-actions"><button type="button" wire:click="clearFilters" wire:loading.attr="disabled">Hapus filter</button></div>
            @if($loadError)
                <div class="scm-error" role="alert"><p>Inventori belum dapat dimuat. Coba muat ulang data.</p><button type="button" wire:click="$refresh" wire:loading.attr="disabled">Muat ulang data</button></div>
            @else
                <div class="scm-scroll" role="region" aria-label="Tabel inventori" tabindex="0">
                    <table>
                        @if($dataset === 'catalogue')
                            <thead><tr><th scope="col">Kode dan barang</th><th scope="col">Jenis</th><th scope="col">Merek/model</th><th scope="col">Satuan</th><th scope="col">Tindakan</th></tr></thead>
                            <tbody>@forelse($rows as $item)<tr wire:key="catalogue-{{ $item->id }}">
                                <td>{{ $item->code }}<br>{{ $item->name }}{{ $item->is_active ? '' : ' (nonaktif)' }}<br>{{ $item->size }}</td>
                                <td>{{ ['atk'=>'ATK','material'=>'Material/barang','device'=>'Perangkat/aset'][$item->kind] }}<br>{{ $item->category }}</td>
                                <td>{{ $item->brand }}<br>{{ $item->model }}</td><td>{{ $item->unit }}</td>
                                <td>@if(\App\Services\Inventory\InventoryAccess::editItem(auth()->user(), $item->kind))<button type="button" wire:click="editItem({{ $item->id }})">Edit barang</button>@endif
                                    @if($item->atkItem && \App\Filament\Resources\AtkItems\AtkItemResource::canEdit($item->atkItem))<a href="{{ \App\Filament\Resources\AtkItems\AtkItemResource::getUrl('edit',['record'=>$item->atkItem]) }}">Pengaturan ATK</a>@endif</td>
                            </tr>@empty<tr><td colspan="5">Belum ada barang sesuai filter. Tambahkan Master Barang sesuai kewenangan Anda.</td></tr>@endforelse</tbody>
                        @elseif($dataset === 'warehouses')
                            <thead><tr><th scope="col">Gudang</th><th scope="col">Entitas</th><th scope="col">HO/Project</th><th scope="col">Status</th><th scope="col">Tindakan</th></tr></thead>
                            <tbody>@forelse($rows as $warehouse)<tr wire:key="warehouse-{{ $warehouse->id }}"><td>{{ $warehouse->label() }}<br>{{ $warehouse->code }}</td><td>{{ $warehouse->company ?? 'Perlu pemetaan' }}</td><td>{{ $warehouse->kind === 'HO' ? 'HO' : 'Project' }}<br>{{ $warehouse->project?->code }}</td><td>{{ $warehouse->is_active && ($warehouse->kind === 'HO' || $warehouse->project?->is_active) ? 'Aktif' : 'Nonaktif' }}</td><td>@if($canAdminister && !$warehouse->legacy_atk)<button type="button" wire:click="toggleWarehouse({{ $warehouse->id }})" wire:confirm="Ubah status aktif gudang ini?">{{ $warehouse->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>@endif</td></tr>@empty<tr><td colspan="5">Belum ada gudang sesuai filter.</td></tr>@endforelse</tbody>
                        @elseif($dataset === 'balances')
                            <thead><tr><th scope="col">Barang</th><th scope="col">Gudang/entitas</th><th scope="col">Departemen asal PR</th><th scope="col">Tersedia</th><th scope="col">Tindakan</th></tr></thead>
                            <tbody>@forelse($rows as $balance)<tr wire:key="balance-{{ $balance->id }}"><td>{{ $balance->item->code }}<br>{{ $balance->item->name }}</td><td>{{ $balance->warehouse->label() }}</td><td>{{ $balance->department?->name ?? ($balance->warehouse->legacy_atk ? 'Belum dipetakan' : 'Stok gudang') }}</td><td>{{ $balance->quantity_milli / 1000 }} {{ $balance->item->unit }}</td><td>
                                @if($balance->warehouse->legacy_atk && $balance->quantity_milli > 0 && ($canAdminister || auth()->user()->hasPermission('atk.manage')) && $balance->item->atkItem)
                                    <button type="button" wire:click="openAllocation({{ $balance->item->atkItem->id }})">Petakan saldo ATK lama</button>
                                @elseif(!$balance->warehouse->legacy_atk && ($canAdminister || \App\Services\Inventory\InventoryAccess::issueDepartment(auth()->user(), $balance)))<button type="button" wire:click="openStock({{ $balance->id }})">{{ $canAdminister ? 'Catat mutasi' : 'Catat distribusi' }}</button>@endif
                            </td></tr>@empty<tr><td colspan="5">Belum ada saldo sesuai filter dan akses Anda. Saldo terbentuk dari penerimaan atau pencatatan stok awal.</td></tr>@endforelse</tbody>
                        @elseif($dataset === 'movements')
                            <thead><tr><th scope="col">Waktu/pelaksana</th><th scope="col">Barang/gudang</th><th scope="col">Transaksi</th><th scope="col">Jumlah</th><th scope="col">Sebelum/sesudah</th><th scope="col">Referensi</th></tr></thead>
                            <tbody>@forelse($rows as $movement)<tr wire:key="movement-{{ $movement->id }}"><td>{{ $movement->created_at->format('d/m/Y H:i') }}<br>{{ $movement->actor?->name ?? 'Saldo migrasi' }}</td><td>{{ $movement->balance->item->name }}<br>{{ $movement->balance->warehouse->label() }}</td><td>{{ ['opening'=>'Saldo awal','atk_sync'=>'Mutasi ATK lama','atk_issue'=>'Penyerahan ATK','pr_receipt'=>'Penerimaan PR','incoming'=>'Stok masuk','outgoing'=>'Stok keluar','adjustment'=>'Penyesuaian','transfer_out'=>'Transfer keluar','transfer_in'=>'Transfer masuk','allocation_out'=>'Pemetaan saldo keluar','allocation_in'=>'Pemetaan saldo masuk','asset_registration'=>'Pendaftaran unit aset'][$movement->type] ?? $movement->type }}</td><td>{{ $movement->quantity_milli / 1000 }} {{ $movement->balance->item->unit }}</td><td>{{ $movement->balance_before_milli / 1000 }} / {{ $movement->balance_after_milli / 1000 }}</td><td>{{ $movement->notes }}@if($movement->receipt_id)<br>Penerimaan #{{ $movement->receipt_id }}<br>PR: {{ $movement->receipt?->request?->number }}@if($movement->receipt?->purchaseOrder)<br>PO: {{ $movement->receipt->purchaseOrder->number }}@endif @endif</td></tr>@empty<tr><td colspan="6">Belum ada mutasi sesuai filter dan akses Anda.</td></tr>@endforelse</tbody>
                        @elseif($dataset === 'assets')
                            <thead><tr><th scope="col">Nomor aset</th><th scope="col">Perangkat/master</th><th scope="col">Serial number</th><th scope="col">Pemakai/status</th><th scope="col">Gudang asal</th><th scope="col">Tindakan</th></tr></thead>
                            <tbody>@forelse($rows as $device)<tr wire:key="asset-{{ $device->id }}"><td>{{ $device->asset_number }}</td><td>{{ $device->name }}<br>{{ $device->inventoryItem?->code }} / {{ $device->inventoryItem?->name }}</td><td>{{ $device->serial_number }}</td><td>{{ $device->assigned_user_name }}<br>{{ $device->lifecycle_status?->label() }}</td><td>{{ $device->inventoryWarehouse?->label() ?? 'Unit lama / belum tercatat dari stok' }}</td><td>@can('view',$device)<a href="{{ \App\Filament\Resources\ItDevices\ItDeviceResource::getUrl('view',['record'=>$device]) }}">Buka perangkat</a>@endcan</td></tr>@empty<tr><td colspan="6">Belum ada unit aset sesuai filter dan akses Anda.</td></tr>@endforelse</tbody>
                        @elseif($dataset === 'stock')
                            <thead><tr><th scope="col">Kode/barang</th><th scope="col">Quantity total gudang</th><th scope="col">Actual</th><th scope="col">Satuan</th><th scope="col">Pemetaan</th></tr></thead>
                            <tbody>@forelse($rows as $item)<tr wire:key="atk-stock-{{ $item->id }}"><td>{{ $item->code }}<br>{{ $item->name }}<br>{{ $item->size }}</td><td>{{ (float)$item->current_stock }}</td><td>{{ $item->actual_stock }}</td><td>{{ $item->unit }}</td><td>@if($canAdminister || auth()->user()->hasPermission('atk.manage'))<button type="button" wire:click="openAllocation({{ $item->id }})">Periksa pemetaan entitas</button>@endif</td></tr>@empty<tr><td colspan="5">Tidak ada ATK yang cocok. Ubah pencarian atau hapus filter.</td></tr>@endforelse</tbody>
                        @else
                            <thead><tr><th scope="col">PR/SR</th><th scope="col">Entitas/kebutuhan</th><th scope="col">Pemohon</th><th scope="col">Barang/jasa</th><th scope="col">Tahap</th><th scope="col">Tindakan</th></tr></thead>
                            <tbody>@forelse($rows as $request)<tr wire:key="inventory-request-{{ $request->id }}"><td>{{ $request->number }}<br>@if($request->purchaseOrder)PO: {{ $request->purchaseOrder->number }}<br>@endif{{ $request->date?->format('d/m/Y') }}<br>{{ $request->sourceAtkRequest?->request_number }}</td><td>{{ $request->company }} / {{ $request->need_type === 'PROJECT' ? 'Project' : 'HO' }}<br>{{ $request->projectNumbering?->name }}</td><td>{{ $request->requester?->name }}<br>{{ $request->department?->name }}</td><td>@foreach($request->items as $item)<div>{{ $item->name }}: {{ $item->quantity_milli / 1000 }} {{ $item->unit }}</div>@endforeach</td><td>{{ match($request->status) {'submitted'=>'Menunggu penerimaan '.($request->need_type === 'HO'?'Purchasing Officer':'SCM'),'sourcing'=>'Diterima '.($request->need_type === 'HO'?'Purchasing Officer':'SCM'), default=>$statuses[$request->status]??$request->status} }}</td><td>
                                @if($url=\App\Filament\Pages\Inventory::procurementUrl($request))<a href="{{ $url }}">Buka {{ $request->kind }}</a>@endif
                                @if($canAdminister && $request->kind === 'PR' && in_array($request->status,['selected','legal','completed']) && $request->cbe?->status === 'final' && $request->purchaseOrder)<button type="button" wire:click="openReceipt({{ $request->id }})">Catat barang diterima di gudang</button>@endif
                            </td></tr>@empty<tr><td colspan="6">Belum ada PR/SR sesuai filter dan akses Anda. Ubah filter atau buat permintaan melalui menu PR/SR.</td></tr>@endforelse</tbody>
                        @endif
                    </table>
                </div>
                <p class="scm-help">Geser tabel ke samping untuk melihat kolom lainnya.</p>
                {{ $rows->links() }}
            @endif
        </section>
    </div>
</x-filament-panels::page>
