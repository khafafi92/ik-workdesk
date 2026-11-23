<x-filament-panels::page>
@vite('resources/css/scm.css')
<style>
    .finance-sheet{background:#fff;color:#111;border:1px solid #9ca3af;padding:1.5rem;font:400 .875rem/1.5 Arial,Helvetica,sans-serif;min-width:0}
    .finance-sheet h2{font-family:Arial,Helvetica,sans-serif;text-align:center;text-transform:uppercase;letter-spacing:.02em}
    .finance-sheet-intro{margin:.5rem 0 1rem}
    .finance-table-wrap{overflow-x:auto;max-width:100%}
    .finance-table{width:100%;min-width:56rem;border-collapse:collapse;table-layout:fixed}
    .finance-table :is(th,td){border:1px solid #374151;padding:.5rem;vertical-align:top;overflow-wrap:anywhere}
    .finance-table th{text-align:center;vertical-align:middle;font-weight:700}
    .finance-table th:nth-child(1){width:15%}.finance-table th:nth-child(2){width:15%}.finance-table th:nth-child(3){width:12%}.finance-table th:nth-child(4){width:13%}.finance-table th:nth-child(5){width:26%}.finance-table th:nth-child(6){width:19%}
    .finance-table td:nth-child(3){font-variant-numeric:tabular-nums;text-align:right}
    .finance-sheet .finance-table a{justify-content:flex-start;padding:.25rem 0;text-align:left;color:#7c2d12}
    .finance-table-actions{display:flex;flex-wrap:wrap;gap:.5rem}
    .finance-sheet-form{margin-top:1rem;border-top:1px solid #374151;padding-top:1rem}
    .finance-plan-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));border-top:1px solid #374151;border-left:1px solid #374151}
    .finance-plan-grid>.scm-field{border-right:1px solid #374151;border-bottom:1px solid #374151;padding:.5rem;min-width:0}
    .finance-sheet .scm-field :is(input,select,textarea){border:0;border-bottom:1px solid #4b5563;border-radius:0;background:#fff;color:#111;font-family:Arial,Helvetica,sans-serif}
    .finance-sheet .scm-help{color:#374151}
    .scm-workspace .finance-sheet :is(button,a,input,select,textarea,[tabindex]):focus-visible{outline:3px solid #005fcc;outline-offset:2px}
    .finance-plan-grid .finance-plan-notes{grid-column:span 2}
    .finance-overview{background:var(--scm-bg);border:1px solid var(--scm-line);border-radius:.75rem;overflow:hidden;min-width:0}
    .finance-overview-header{padding:1.5rem;border-bottom:1px solid var(--scm-line)}
    .finance-overview-header h2{font-size:1.125rem;font-weight:700}.finance-overview-header p{color:var(--scm-muted);margin-top:.35rem}
    .finance-overview-metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));background:var(--scm-soft);border-bottom:1px solid var(--scm-line)}
    .finance-overview-metrics>div{padding:1rem 1.5rem;min-width:0}.finance-overview-metrics>div+div{border-left:1px solid var(--scm-line)}
    .finance-overview-metrics dt{font-size:.8rem;color:var(--scm-muted)}.finance-overview-metrics dd{font-size:1.15rem;font-weight:700;margin-top:.25rem}
    .finance-overview-search{padding:1rem 1.5rem;border-bottom:1px solid var(--scm-line)}.finance-overview-search .scm-field{max-width:42rem}
    .finance-overview-table{width:100%;min-width:65rem;border-collapse:collapse;font-size:.875rem}
    .finance-overview-table :is(th,td){padding:1rem;vertical-align:top;text-align:left;border-bottom:1px solid var(--scm-line)}
    .finance-overview-table th{background:var(--scm-soft);font-weight:700}.finance-overview-table td:first-child,.finance-overview-table th:first-child{padding-left:1.5rem}
    .finance-overview-table strong{display:block}.finance-overview-meta{display:block;color:var(--scm-muted);font-size:.8rem;margin-top:.3rem}
    .finance-progress{height:.5rem;background:var(--scm-line);border-radius:1rem;margin:.5rem 0;overflow:hidden}.finance-progress span{display:block;height:100%;background:#2563eb}
    .finance-overview-footer{padding:1rem 1.5rem}.finance-overview-footer p{color:var(--scm-muted);font-size:.875rem;margin-bottom:.5rem}
    @media(max-width:900px){.finance-overview-metrics{grid-template-columns:repeat(2,minmax(0,1fr))}.finance-overview-metrics>div:nth-child(3){border-left:0}.finance-overview-metrics>div:nth-child(n+3){border-top:1px solid var(--scm-line)}}
    @media(max-width:700px){.finance-overview-header,.finance-overview-search,.finance-overview-footer{padding:1rem}.finance-overview-metrics>div{padding:.75rem 1rem}.scm-scroll .finance-overview-table,.finance-overview-table tbody{display:block;min-width:0}.finance-overview-table thead{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0)}.finance-overview-table tr{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));padding:.5rem 1rem;border-bottom:1px solid var(--scm-line)}.finance-overview-table td,.finance-overview-table td:first-child{display:block;min-width:0;padding:.5rem;border:0}.finance-overview-table td::before{content:attr(data-label);display:block;color:var(--scm-muted);font-size:.8rem;font-weight:600;margin-bottom:.25rem}.finance-overview-table td:nth-child(5),.finance-overview-table td:nth-child(6),.finance-overview-table .finance-overview-empty{grid-column:1/-1}}
    @media(max-width:900px){.finance-plan-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.finance-plan-grid .finance-plan-notes{grid-column:auto}}
    @media(max-width:700px){.finance-sheet{padding:.75rem}.finance-table-wrap{overflow:visible}.finance-table,.finance-table tbody{display:block;min-width:0}.finance-table thead{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0)}.finance-table tr{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));border:1px solid #374151;margin-bottom:.75rem}.finance-table td{display:block;border:0;min-width:0}.finance-table td:nth-child(3){text-align:left}.finance-table td:nth-child(5),.finance-table td:nth-child(6),.finance-table .finance-empty{grid-column:1/-1}.finance-table td::before{content:attr(data-label);display:block;font-size:.8rem;font-weight:700;margin-bottom:.25rem}.finance-plan-grid{grid-template-columns:minmax(0,1fr)}}
    .finance-detail{display:grid;gap:1rem;min-width:0}
    .finance-detail-header,.finance-detail-block,.finance-detail .finance-sheet{background:var(--scm-bg);color:var(--scm-text);border:1px solid var(--scm-line);border-radius:.75rem;padding:1.5rem;min-width:0}
    .finance-detail-heading{display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap}
    .finance-detail-heading h2{font-size:1.25rem;font-weight:700;line-height:1.35}.finance-detail-heading p{margin-top:.35rem;color:var(--scm-muted)}
    .finance-detail-links{display:flex;align-items:center;gap:.5rem;flex-wrap:wrap}.finance-detail-links a{justify-content:flex-start}
    .finance-detail-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:1rem;background:var(--scm-soft);border:1px solid var(--scm-line);border-radius:.5rem;padding:1rem;margin-top:1.25rem}
    .finance-detail-summary>div{min-width:0}.finance-detail-summary dt,.finance-bank-facts dt{font-size:.8rem;color:var(--scm-muted)}.finance-detail-summary dd{font-size:1.1rem;font-weight:700;margin-top:.25rem;font-variant-numeric:tabular-nums}
    .finance-detail-columns{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem}
    .finance-detail-block h3{font-size:1rem}.finance-detail-block>p{margin-top:.4rem;color:var(--scm-muted)}
    .finance-bank-facts{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1rem;margin-top:1rem}.finance-bank-facts dd{font-weight:600;margin-top:.25rem;overflow-wrap:anywhere}
    .finance-bank-warning{padding:.65rem .75rem;border:1px solid var(--scm-line);border-radius:.375rem}
    .finance-receipt-list{display:grid;gap:.75rem;margin-top:.75rem}.finance-receipt-list>div+div{border-top:1px solid var(--scm-line);padding-top:.75rem}
    .finance-detail .finance-sheet{font:inherit}.finance-detail .finance-sheet h2{text-align:left;text-transform:none;letter-spacing:normal;font:inherit;font-size:1.125rem;font-weight:700}
    .finance-detail .finance-table-wrap{margin-top:1rem}.finance-detail .finance-table :is(th,td){border:0;border-bottom:1px solid var(--scm-line);padding:.75rem;text-align:left}.finance-detail .finance-table th{background:var(--scm-soft)}
    .finance-detail .finance-table td:nth-child(3){text-align:left}.finance-detail .finance-table a{color:var(--scm-accent)}
    .finance-detail .finance-plan-grid{border:0;gap:1rem}.finance-detail .finance-plan-grid>.scm-field{padding:0;border:0}
    .finance-detail .finance-sheet .scm-field :is(input,select,textarea){border:1px solid var(--scm-line);border-radius:.375rem;background:var(--scm-bg);color:var(--scm-text);font:inherit}
    .finance-detail .finance-sheet .scm-help{color:var(--scm-muted)}.finance-detail .finance-sheet-form{border-color:var(--scm-line)}
    @media(max-width:900px){.finance-detail-summary{grid-template-columns:repeat(2,minmax(0,1fr))}.finance-detail-columns{grid-template-columns:1fr}}
    @media(max-width:700px){.finance-detail-header,.finance-detail-block,.finance-detail .finance-sheet{padding:1rem}.finance-bank-facts{grid-template-columns:1fr;gap:.75rem}.finance-detail .finance-table tr{border:1px solid var(--scm-line);border-radius:.5rem}.finance-detail .finance-table :is(th,td){border:0;padding:.5rem}.finance-detail .finance-table td:nth-child(3){text-align:left}}
</style>
<div class="scm-workspace">
    @if($errors->any())<div class="scm-error" role="alert"><h2>Periksa data pembayaran</h2><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <p role="status" wire:loading.delay>Memuat atau menyimpan data pembayaran...</p>
    @if($schemaMissing)
        <section class="scm-panel"><p role="alert">Modul pembayaran belum disiapkan di database. Administrator database perlu menjalankan migrasi Finance.</p><button type="button" wire:click="$refresh">Periksa lagi</button></section>
    @elseif($loadError)
        <section class="scm-panel"><p role="alert">Data pembayaran belum dapat dimuat. Hubungi administrator untuk memeriksa database.</p><button type="button" wire:click="$refresh">Coba lagi</button></section>
    @elseif(! $order)
        <section class="finance-overview"><header class="finance-overview-header"><h2>Purchase Order</h2>
            <p>Pilih PO untuk mencatat termin, invoice, dan bukti pembayaran. Penerimaan barang tetap dicatat oleh gudang.</p></header>
            <dl class="finance-overview-metrics" aria-label="Ringkasan PO pada halaman ini">
                <div><dt>PO pada halaman ini</dt><dd>{{ $orders->count() }}</dd></div>
                <div><dt>PO dengan penerimaan</dt><dd>{{ $orders->where('receipts_exists', true)->count() }}</dd></div>
                <div><dt>Termin belum dibayar</dt><dd>{{ $orders->sum('planned_count') }}</dd></div>
                <div><dt>Total PO ditemukan</dt><dd>{{ $orders->total() }}</dd></div>
            </dl>
            <div class="finance-overview-search"><x-scm.field model="search" label="Cari nomor PO atau PR/SR" :live="true" /></div>
            <div class="scm-scroll" role="region" aria-label="Daftar Purchase Order" tabindex="0"><table class="finance-overview-table"><thead><tr><th scope="col">PO / PR</th><th scope="col">Vendor</th><th scope="col">Penerimaan & termin</th><th scope="col">Grand Total</th><th scope="col">Sudah dibayar</th><th scope="col">Tindakan</th></tr></thead><tbody>
                @forelse($orders as $row)
                    @php
                        $currency = $row->snapshot['currency'] ?? 'IDR';
                        $total = (int) ($row->snapshot['evaluation']['total_minor'] ?? 0);
                        $paid = (int) ($row->paid_minor ?? 0);
                        $progress = $total > 0 ? min(100, max(0, (int) round($paid / $total * 100))) : 0;
                        $vendor = $row->cbe?->selectedVendor;
                    @endphp
                    <tr wire:key="finance-order-{{ $row->id }}"><td data-label="PO / PR"><strong>{{ $row->number }}</strong><span class="finance-overview-meta">{{ $row->request->number }}</span>@if($row->status === 'cancelled')<span class="finance-overview-meta">PO dibatalkan</span>@endif</td>
                        <td data-label="Vendor"><strong>{{ $row->snapshot['vendor']['name'] ?? '-' }}</strong><span class="finance-overview-meta">{{ $vendor?->bank_name ?: 'Bank belum diisi' }}</span></td>
                        <td data-label="Penerimaan & termin">{{ $row->receipts_exists ? 'Penerimaan tercatat' : 'Belum ada penerimaan' }}<span class="finance-overview-meta">{{ $row->payments_count }} termin tercatat</span></td>
                        <td data-label="Grand Total"><strong>{{ \App\Services\Scm\Decimal::money($total, $currency) }}</strong></td>
                        <td data-label="Sudah dibayar"><strong>{{ \App\Services\Scm\Decimal::money($paid, $currency) }}</strong><div class="finance-progress" role="progressbar" aria-label="Pembayaran {{ $row->number }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $progress }}"><span style="width:{{ $progress }}%"></span></div><span class="finance-overview-meta">{{ $progress }}% dibayar · Sisa {{ \App\Services\Scm\Decimal::money(max(0, $total - $paid), $currency) }}</span></td>
                        <td data-label="Tindakan"><button type="button" wire:click="openOrder({{ $row->id }})">Buka {{ $row->number }}</button></td></tr>
                @empty<tr><td class="finance-overview-empty" colspan="6">Belum ada PO yang sesuai dengan pencarian dan akses Anda.</td></tr>@endforelse
            </tbody></table></div><footer class="finance-overview-footer"><p>Ringkasan di atas mengikuti PO yang tampil pada halaman ini, kecuali total PO ditemukan.</p>{{ $orders->links() }}</footer>
        </section>
    @else
        @php
            $currency = $order->snapshot['currency'] ?? 'IDR';
            $total = (int) ($order->snapshot['evaluation']['total_minor'] ?? 0);
            $paidTotal = $payments->where('status', 'paid')->sum('amount_minor');
            $plannedTotal = $payments->where('status', 'planned')->sum('amount_minor');
            $selected = $payments->firstWhere('id', $selectedPayment);
            $vendorMaster = $order->cbe?->selectedVendor;
            $progress = $total > 0 ? min(100, max(0, (int) round($paidTotal / $total * 100))) : 0;
            $canCancelOrder = $order->status !== 'cancelled' && $paidTotal === 0 && $receipts->isEmpty();
        @endphp
        <div class="finance-detail">
        <section class="finance-detail-header"><header class="finance-detail-heading"><div><h2>{{ $order->number }}</h2>
            <p>{{ $order->snapshot['vendor']['name'] ?? '-' }} · {{ $order->request->number }}</p>
            <p>Syarat pembayaran PO: {{ $order->payment_terms }}</p></div>
            <div class="finance-detail-links"><a href="{{ route('scm.purchase-order-print', $order) }}" target="_blank" rel="noopener">Lihat Purchase Order</a><button type="button" wire:click="closeOrder">Kembali ke daftar PO</button></div></header>
            <dl class="finance-detail-summary"><div><dt>Grand Total PO</dt><dd>{{ \App\Services\Scm\Decimal::money($total, $currency) }}</dd></div>
                <div><dt>Sudah dibayar</dt><dd>{{ \App\Services\Scm\Decimal::money($paidTotal, $currency) }}</dd></div>
                <div><dt>Termin belum dibayar</dt><dd>{{ \App\Services\Scm\Decimal::money($plannedTotal, $currency) }}</dd></div>
                <div><dt>Belum dijadwalkan</dt><dd>{{ \App\Services\Scm\Decimal::money(max(0, $total - $paidTotal - $plannedTotal), $currency) }}</dd></div></dl>
            <div class="finance-overview-meta">{{ $progress }}% dari total PO sudah dibayar</div><div class="finance-progress" role="progressbar" aria-label="Pembayaran PO" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $progress }}"><span style="width:{{ $progress }}%"></span></div>
            @if($order->status === 'cancelled')
                <p role="status">PO dibatalkan pada {{ $order->cancelled_at?->format('d/m/Y H:i') }}. Alasan: {{ $order->cancellation_reason }}</p>
            @elseif($canCancelOrder)
                @if($paymentAction !== 'cancel_order')
                    <button type="button" wire:click="prepareCancelOrder">Batalkan seluruh PO</button>
                @else
                    <form class="finance-sheet-form" wire:submit="cancelOrder"><fieldset wire:loading.attr="disabled"><legend>Batalkan seluruh PO</legend>
                        <p>Pembatalan melepas komitmen anggaran dan membatalkan termin yang belum dibayar. Riwayat tetap tersimpan.</p>
                        <x-scm.field model="orderCancelReason" label="Alasan pembatalan PO" multiline help="Minimal 10 karakter." required />
                        <div class="scm-actions"><x-filament::button type="submit" color="danger" wire:confirm="Batalkan seluruh PO? Pembatalan tidak dapat dibatalkan." wire:loading.attr="disabled">Konfirmasi pembatalan PO</x-filament::button>
                            <button type="button" wire:click="$set('paymentAction', '')">Kembali</button></div>
                    </fieldset></form>
                @endif
            @elseif($order->status !== 'cancelled')
                <p role="status">@if($paidTotal > 0)PO tidak dapat dibatalkan karena sudah ada pembayaran.@else PO tidak dapat dibatalkan karena penerimaan barang sudah tercatat.@endif</p>
            @endif
        </section>
        <div class="finance-detail-columns"><section class="finance-detail-block"><h3>Rekening vendor</h3>
                <p>Data master saat ini untuk {{ $vendorMaster?->name ?? ($order->snapshot['vendor']['name'] ?? 'vendor') }}.</p>
                @if(! $vendorMaster || ! $vendorMaster->bank_name || ! $vendorMaster->bank_account_number || ! $vendorMaster->bank_account_name)
                    <p class="finance-bank-warning" role="status">Data rekening vendor belum lengkap. Lengkapi profil vendor sebelum digunakan untuk pembayaran.</p>
                @endif
                <dl class="finance-bank-facts"><div><dt>Nama bank</dt><dd>{{ $vendorMaster?->bank_name ?: 'Belum diisi' }}</dd></div>
                    <div><dt>Nomor rekening</dt><dd>{{ $vendorMaster?->bank_account_number ?: 'Belum diisi' }}</dd></div>
                    <div><dt>Nama pemilik rekening</dt><dd>{{ $vendorMaster?->bank_account_name ?: 'Belum diisi' }}</dd></div></dl>
        </section>
        <section class="finance-detail-block"><h3>Penerimaan barang terkait</h3><div class="finance-receipt-list">
            @forelse($receipts as $receipt)<div><p>{{ $receipt->received_on->format('d/m/Y') }} · {{ $receipt->reference }}</p>
                @foreach($receipt->movements as $movement)<p>{{ $movement->balance?->item?->name ?? 'Barang' }}: {{ \App\Services\Scm\Decimal::quantityInput((int) $movement->quantity_milli) }} {{ $movement->balance?->item?->unit }}</p>@endforeach</div>
            @empty<p>Belum ada penerimaan barang tercatat untuk PO ini.</p>@endforelse
        </div></section></div>
        <section class="finance-sheet"><h2>Termin pembayaran</h2>
            <div class="finance-table-wrap" role="region" aria-label="Daftar termin pembayaran" tabindex="0"><table class="finance-table"><thead><tr><th scope="col">Termin</th><th scope="col">Waktu / jatuh tempo</th><th scope="col">Nominal</th><th scope="col">Status</th><th scope="col">Dokumen</th><th scope="col">Tindakan</th></tr></thead><tbody>
                @forelse($payments as $payment)<tr wire:key="finance-payment-{{ $payment->id }}"><td data-label="Termin">{{ \App\Services\Finance\PoPayments::KINDS[$payment->kind] ?? $payment->kind }}<br>{{ $payment->description }}@if($payment->invoice_number)<br>Invoice: {{ $payment->invoice_number }}@endif</td>
                    <td data-label="Waktu / jatuh tempo">{{ \App\Services\Finance\PoPayments::TIMINGS[$payment->timing] ?? $payment->timing }}<br>{{ $payment->due_on?->format('d/m/Y') ?? 'Belum ditetapkan' }}</td>
                    <td data-label="Nominal">{{ \App\Services\Scm\Decimal::money($payment->amount_minor, $payment->currency) }}</td>
                    <td data-label="Status">{{ ['planned' => 'Belum dibayar', 'paid' => 'Dibayar', 'cancelled' => 'Dibatalkan'][$payment->status] ?? $payment->status }}@if($payment->paid_on)<br>{{ $payment->paid_on->format('d/m/Y') }}<br>{{ $payment->payment_reference }}
                        <br>Rekening saat dicatat: {{ $payment->vendor_bank_snapshot['bank_name'] ?? 'Tidak tercatat' }} · {{ $payment->vendor_bank_snapshot['bank_account_number'] ?? 'Tidak tercatat' }} · {{ $payment->vendor_bank_snapshot['bank_account_name'] ?? 'Tidak tercatat' }}@endif</td>
                    <td data-label="Dokumen">@forelse($payment->files as $file)<a href="{{ route('finance.payment-file', $file) }}">{{ $file->type === 'invoice' ? 'Invoice' : 'Bukti bayar' }}: {{ $file->original_name }}</a><br>@empty Belum ada dokumen @endforelse</td>
                    <td data-label="Tindakan"><div class="finance-table-actions">@if($order->status !== 'cancelled' && $payment->status === 'planned')<button type="button" wire:click="preparePaid({{ $payment->id }})">Proses termin {{ $loop->iteration }}</button>@endif
                        @if($order->status !== 'cancelled' && $payment->status !== 'cancelled' && ! $payment->files->contains('type', 'invoice'))<button type="button" wire:click="prepareInvoice({{ $payment->id }})">Tambah invoice</button>@endif</div></td></tr>
                @empty<tr><td class="finance-empty" colspan="6">Belum ada termin pembayaran. Tambahkan sesuai syarat PO.</td></tr>@endforelse
            </tbody></table></div>
            @if($order->status !== 'cancelled' && $selected && $selected->status === 'planned' && $paymentAction === 'pay')
                <form class="finance-sheet-form" wire:submit="savePaid"><fieldset wire:loading.attr="disabled"><legend>Catat pembayaran: {{ $selected->description }}</legend>
                    <p>Catat pembayaran yang sudah dilakukan dan unggah buktinya.</p>
                    <div><strong>Rekening vendor saat formulir dibuka</strong>
                        <p>{{ $vendorMaster?->name ?? ($order->snapshot['vendor']['name'] ?? 'Vendor') }} · {{ ($confirmedVendorBank['bank_name'] ?? null) ?: 'Bank belum diisi' }} · {{ ($confirmedVendorBank['bank_account_number'] ?? null) ?: 'Nomor rekening belum diisi' }} · {{ ($confirmedVendorBank['bank_account_name'] ?? null) ?: 'Pemilik rekening belum diisi' }}</p>
                        <p>Pastikan rekening sesuai bukti pembayaran. Data ini akan disimpan pada riwayat termin.</p>
                    </div><div class="scm-grid">
                        <x-scm.field model="paid.paid_on" label="Tanggal pembayaran" type="date" required />
                        <x-scm.field model="paid.payment_reference" label="Referensi pembayaran" required />
                        <x-scm.field model="paid.invoice_number" label="Nomor invoice" />
                        <x-scm.field model="proofFile" label="Bukti pembayaran (PDF/JPG/PNG)" type="file" required accept=".pdf,.jpg,.jpeg,.png" />
                        @if(! $selected->files->contains('type', 'invoice'))<x-scm.field model="paidInvoiceFile" label="Invoice (PDF/JPG/PNG, jika ada)" type="file" accept=".pdf,.jpg,.jpeg,.png" />@endif
                    </div><div class="scm-actions"><x-filament::button type="submit" wire:loading.attr="disabled">Simpan pembayaran</x-filament::button></div>
                </fieldset></form>
                <div><x-scm.field model="cancelReason" label="Alasan pembatalan termin" multiline help="Minimal 10 karakter; hanya untuk termin yang belum dibayar." />
                    <button type="button" wire:click="cancelPlan" wire:confirm="Batalkan termin ini? Riwayat tetap disimpan." wire:loading.attr="disabled">Batalkan termin</button></div>
            @endif
            @if($order->status !== 'cancelled' && $selected && $paymentAction === 'invoice' && $selected->status !== 'cancelled' && ! $selected->files->contains('type', 'invoice'))
                <form class="finance-sheet-form" wire:submit="saveInvoice"><fieldset wire:loading.attr="disabled"><legend>Tambah invoice: {{ $selected->description }}</legend><div class="scm-grid">
                    <x-scm.field model="lateInvoiceNumber" label="Nomor invoice" required />
                    <x-scm.field model="lateInvoiceFile" label="Invoice (PDF/JPG/PNG)" type="file" required accept=".pdf,.jpg,.jpeg,.png" />
                </div><div class="scm-actions"><x-filament::button type="submit" wire:loading.attr="disabled">Simpan invoice</x-filament::button></div></fieldset></form>
            @endif
        </section>
        @if($order->status !== 'cancelled')
        <section class="finance-sheet"><h2>Tambah termin</h2>
            <p class="finance-sheet-intro">Masukkan nominal berdasarkan kesepakatan. Total termin tidak boleh melebihi Grand Total PO.</p>
            <form wire:submit="savePlan"><fieldset wire:loading.attr="disabled"><legend>Rencana pembayaran</legend><div class="finance-plan-grid">
                <x-scm.select model="plan.kind" label="Jenis termin" :options="\App\Services\Finance\PoPayments::KINDS" :live="true" required />
                <x-scm.field model="plan.description" label="Keterangan termin" required :live="true" />
                <x-scm.select model="plan.timing" label="Waktu pembayaran" :options="\App\Services\Finance\PoPayments::TIMINGS" required help="Untuk pembayaran setelah penerimaan barang PR, minimal satu penerimaan harus tercatat. Periksa jumlah yang diterima sebelum membayar." />
                <x-scm.field model="plan.due_on" label="Jatuh tempo" type="date" />
                <x-scm.field model="plan.amount" label="Nominal ({{ $currency }})" inputmode="decimal" required :help="($plan['kind'] ?? '') === 'settlement' ? 'Nominal pelunasan diisi dari sisa PO yang belum dijadwalkan, setelah termin yang dibayar dan direncanakan diperhitungkan. Nominal dapat diubah.' : 'Persentase pada keterangan dihitung dari Grand Total PO. Nominal dapat diubah; gunakan koma untuk desimal tanpa pemisah ribuan.'" />
                <x-scm.field model="plan.invoice_number" label="Nomor invoice" />
                <x-scm.field model="invoiceFile" label="Invoice (PDF/JPG/PNG, jika ada)" type="file" accept=".pdf,.jpg,.jpeg,.png" />
                <div class="scm-field finance-plan-notes"><label for="scm-plan-notes">Catatan</label><textarea id="scm-plan-notes" wire:model="plan.notes" rows="3"></textarea></div>
            </div>@if(($plan['kind'] ?? '') === 'settlement' && $total - $paidTotal - $plannedTotal <= 0)<p role="status">Tidak ada sisa PO yang belum dijadwalkan. Periksa termin yang sudah tercatat.</p>@endif<div class="scm-actions"><x-filament::button type="submit" wire:loading.attr="disabled">Simpan termin</x-filament::button></div></fieldset></form>
        </section>
        @endif
        </div>
    @endif
</div>
</x-filament-panels::page>
