@php
    $poApproved = $record->cbe && app(\App\Services\Scm\PurchaseOrders::class)->approved($record->cbe);
    $poCanEdit = $poApproved && app(\App\Services\Scm\PurchaseOrders::class)->legalComplete($record, $record->cbe);
@endphp

<div class="scm-po-panel">
    <div>
        <h3>Purchase Order</h3>
        <p>PO menggunakan vendor, barang, dan nilai dari CBE yang disetujui. Nomor PO diisi manual oleh petugas.</p>
    </div>
    @if (!$record->cbe || ! $poApproved)
        <p role="status">PO dapat dibuat setelah seluruh persetujuan CBE, termasuk Approved By, selesai.</p>
    @elseif (! $poCanEdit && ! $record->purchaseOrder)
        <p role="status">Selesaikan legalitas vendor terpilih melalui tombol Selesaikan pengadaan pada tab Legalitas sebelum membuat PO.</p>
    @else
        @if ($poCanEdit)
            <p>Vendor terpilih: <strong>{{ $selectedQuote['vendor']['name'] ?? '-' }}</strong> · Quotation: {{ $selectedQuote['number'] ?? '-' }} · CBE: {{ $record->cbe->number }}</p>
            <form wire:submit="savePurchaseOrder" class="scm-po-form">
                <div class="scm-grid">
                    <x-scm.field model="purchaseOrderForm.number" label="Nomor Purchase Order" required help="Isi nomor PO secara manual sesuai penomoran perusahaan." />
                    <x-scm.field model="purchaseOrderForm.issued_at" label="Tanggal PO" type="date" required />
                    <x-scm.field model="purchaseOrderForm.supplier_address" label="Alamat pemasok" multiline />
                    <x-scm.field model="purchaseOrderForm.order_contact" label="Kontak pesanan" />
                    <x-scm.field model="purchaseOrderForm.delivery_terms" label="Tujuan / syarat pengiriman" multiline required />
                    <x-scm.field model="purchaseOrderForm.delivery_time" label="Waktu pengiriman" required />
                    <x-scm.field model="purchaseOrderForm.payment_terms" label="Syarat pembayaran" multiline required />
                    <x-scm.field model="purchaseOrderForm.notes" label="Catatan PO" multiline />
                </div>
                <div class="scm-actions"><x-filament::button type="submit" wire:loading.attr="disabled" wire:target="savePurchaseOrder">Simpan draft PO</x-filament::button></div>
                <p class="scm-help">Harga, diskon, biaya tambahan, dan PPN mengikuti snapshot CBE final. Draft belum memiliki tanda tangan PO.</p>
            </form>
        @endif

        @if ($record->purchaseOrder)
            <div class="scm-actions">
                <a href="{{ route('scm.purchase-order-print', $record->purchaseOrder) }}" target="_blank" rel="noopener">Buka draft PO untuk cetak / simpan PDF</a>
            </div>
            @include('scm.partials.purchase-order-sheet', ['order' => $record->purchaseOrder])
        @endif
    @endif
</div>
