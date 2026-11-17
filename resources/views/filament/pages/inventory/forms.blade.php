<section class="scm-panel" aria-label="Pencatatan inventori">
    @if($formMode === 'catalogue')
        <form wire:submit="saveItem">
            <h2>{{ $catalogueId ? 'Edit Master Barang' : 'Tambah Master Barang' }}</h2>
            <div class="scm-grid">
                <x-scm.field model="catalogueForm.code" label="Kode barang" required />
                <x-scm.field model="catalogueForm.name" label="Nama barang" required />
                <x-scm.select model="catalogueForm.kind" label="Jenis barang" :options="$editableKinds" :disabled="$catalogueId > 0" required />
                <x-scm.field model="catalogueForm.category" label="Kategori" />
                <x-scm.field model="catalogueForm.unit" label="Satuan" required />
                <x-scm.field model="catalogueForm.brand" label="Merek" />
                <x-scm.field model="catalogueForm.model" label="Model/part number" />
                <x-scm.field model="catalogueForm.size" label="Ukuran" />
                <x-scm.select model="catalogueForm.is_active" label="Status" :options="[1=>'Aktif',0=>'Nonaktif']" required />
            </div>
            <x-scm.field model="catalogueForm.specification" label="Spesifikasi barang" multiline />
            <div class="scm-actions"><button type="submit" wire:loading.attr="disabled">Simpan Master Barang</button><button type="button" wire:click="closeForm">Batal</button></div>
            <p class="scm-help">Pengubahan master tidak mengubah isi PR yang sudah tersimpan. Satuan yang sudah dipakai transaksi tetap dipertahankan.</p>
        </form>
    @elseif($formMode === 'allocation' && $allocationItem)
        <form wire:submit="allocate">
            <h2>Pemetaan saldo ATK: {{ $allocationItem->name }}</h2>
            <p>Saldo lama yang belum dipetakan: {{ $allocationAvailable }} {{ $allocationItem->unit }}. Tentukan jumlah milik masing-masing entitas berdasarkan data stok. Jumlah total tetap sama.</p>
            <div class="scm-grid"><x-scm.field model="allocation.KPMOG" label="Jumlah ke Gudang HO KPMOG" inputmode="decimal" required /><x-scm.field model="allocation.APCA" label="Jumlah ke Gudang HO APCA" inputmode="decimal" required /></div>
            <div class="scm-actions"><button type="submit" wire:loading.attr="disabled">Simpan pemetaan entitas</button><button type="button" wire:click="closeForm">Batal</button></div>
        </form>
    @elseif($formMode === 'stock')
        <form wire:submit="saveStock">
            <h2>Catat mutasi gudang</h2>
            <div class="scm-grid">
                <x-scm.select model="stockForm.operation" label="Transaksi" :options="!$canAdminister ? ['outgoing'=>'Distribusi / stok keluar'] : ($stockForm['balance_id'] ? ['incoming'=>'Stok masuk','outgoing'=>'Distribusi / stok keluar','transfer'=>'Transfer gudang','department_transfer'=>'Pindah ke gudang departemen asal'] : ['incoming'=>'Stok awal/masuk'])" :live="true" required />
                <x-scm.select model="stockForm.warehouse_id" label="Gudang sumber" :options="$warehouseOptions" :disabled="filled($stockForm['balance_id'])" required />
                <x-scm.select model="stockForm.inventory_item_id" label="Barang" :options="$itemOptions" :disabled="filled($stockForm['balance_id'])" required />
                <x-scm.select model="stockForm.department_id" label="Departemen asal pencatatan" :options="$departmentOptions" :disabled="filled($stockForm['balance_id'])" />
                @if($stockForm['operation'] === 'transfer')<x-scm.select model="stockForm.destination_id" label="Gudang tujuan" :options="$warehouseOptions" required />@endif
                <x-scm.field model="stockForm.quantity" label="Jumlah" inputmode="decimal" required />
            </div>
            <x-scm.field model="stockForm.notes" label="Referensi dan alasan mutasi" multiline required />
            <p class="scm-help">Pindahkan barang dari gudang utama ke gudang departemen asal PR. Untuk distribusi, pilih stok keluar dari gudang departemen dan tulis penerima atau departemen tujuan pada referensi.</p>
            <div class="scm-actions"><button type="submit" wire:loading.attr="disabled">Simpan mutasi gudang</button><button type="button" wire:click="closeForm">Batal</button></div>
        </form>
    @elseif($formMode === 'receipt' && $receiptRequest)
        <form wire:submit="receive">
            <h2>Penerimaan barang: {{ $receiptRequest->number }}</h2>
            <p>PO: {{ $receiptRequest->purchaseOrder?->number }}</p>
            <p>Isi jumlah fisik yang diterima pada pengiriman ini. Isi nol untuk baris yang belum diterima. Master dan satuan harus sesuai barang pada PR.</p>
            <div class="scm-grid">
                <x-scm.select model="receiptForm.warehouse_id" label="Gudang tujuan" :options="$warehouseOptions" required />
                <x-scm.field model="receiptForm.received_on" label="Tanggal diterima" type="date" required />
                <x-scm.field model="receiptForm.reference" label="Nomor surat jalan / bukti penerimaan" required />
            </div>
            @foreach($receiptLines as $index=>$line)
                @if($source=$receiptItems->get($line['request_item_id']))
                <fieldset class="scm-item" wire:key="receipt-line-{{ $source->id }}"><legend>{{ $source->name }}</legend>
                    <p>PR: {{ $source->quantity_milli/1000 }} {{ $source->unit }}. Sudah diterima: {{ ($receiptReceived[$source->id]??0)/1000 }}. Sisa: {{ ($source->quantity_milli-($receiptReceived[$source->id]??0))/1000 }}.</p>
                    <div class="scm-grid">
                        <x-scm.select model="receiptLines.{{ $index }}.inventory_item_id" label="Master Barang" :options="$itemOptions" :live="true" :disabled="filled($source->inventory_item_id)" />
                        <x-scm.field model="receiptLines.{{ $index }}.quantity" label="Jumlah diterima kali ini" inputmode="decimal" required />
                    </div>
                    @if(! $source->inventory_item_id)
                        @if($receiptNewItemSource === $source->id && ! filled($line['inventory_item_id'] ?? null))
                            <div class="scm-item"><h3>Tambah Master Barang: {{ $source->name }}</h3>
                                <p>Periksa apakah barang sudah ada sebelum membuat master baru. Satuan mengikuti PR: {{ $source->unit }}.</p>
                                <div class="scm-grid">
                                    <x-scm.field model="receiptNewItem.code" label="Kode barang" required />
                                    <x-scm.field model="receiptNewItem.name" label="Nama barang" required />
                                    <x-scm.select model="receiptNewItem.kind" label="Jenis barang" :options="$editableKinds" required />
                                    <x-scm.field model="receiptNewItem.brand" label="Merek" />
                                    <x-scm.field model="receiptNewItem.model" label="Model/part number" />
                                    <x-scm.field model="receiptNewItem.specification" label="Spesifikasi barang" multiline />
                                </div>
                                <p class="scm-help">Master disimpan saat tombol di bawah ditekan. Stok bertambah setelah penerimaan gudang disimpan.</p>
                                <div class="scm-actions"><button type="button" wire:click="saveReceiptItem" wire:loading.attr="disabled" wire:target="saveReceiptItem">Simpan dan pilih Master Barang</button><button type="button" wire:click="cancelReceiptItem">Batal tambah master</button></div>
                            </div>
                        @elseif(! filled($line['inventory_item_id'] ?? null))
                            <div class="scm-actions"><button type="button" wire:click="newReceiptItem({{ $source->id }})">Tambah Master Barang untuk {{ $source->name }}</button></div>
                        @endif
                    @endif
                </fieldset>
                @endif
            @endforeach
            <x-scm.field model="receiptForm.notes" label="Catatan penerimaan" multiline />
            <p class="scm-help">Penerimaan ini menambah stok gudang. Peminta ATK tetap mengonfirmasi penerimaan setelah petugas menyerahkan barang.</p>
            <div class="scm-actions"><button type="submit" wire:loading.attr="disabled">Simpan penerimaan gudang</button><button type="button" wire:click="closeForm">Batal</button></div>
        </form>
    @endif
</section>
