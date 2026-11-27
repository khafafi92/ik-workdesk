<x-filament-panels::page>
    <link rel="stylesheet" href="{{ asset('css/travel.css') }}?v={{ filemtime(public_path('css/travel.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/finance-requests.css') }}?v={{ filemtime(public_path('css/finance-requests.css')) }}">
    @php
        $companyOptions = array_combine($companies, $companies);
        $filterCompanies = array_combine(\App\Models\FinanceRequest::COMPANIES, \App\Models\FinanceRequest::COMPANIES);
        $statuses = \App\Models\FinanceRequest::STATUSES;
        $expenseReport = $kind === 'expense_report';
        $financeApprovalTabs = ['expense_report' => 'expense_report', 'reimbursement' => 'reimbursement',
            'payment' => $fixedCompany === 'APCA' ? 'payment_request' : 'payment'];
    @endphp
    <div @class(['travel-workspace', 'finance-travel', 'travel-editor' => $editable])
        @if(!$editable && (!$record || ($record->status === 'submitted' && !$canDecide))) wire:poll.10s.visible="refreshProgress" @endif>
        @if($errors->any())
            <div class="travel-validation" role="alert">
                <strong>Periksa pengajuan</strong>
                <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif
        <p class="travel-help" wire:loading.delay wire:target.except="refreshProgress" role="status">Memproses pengajuan…</p>
        @if($kind === 'reimbursement' && (!$record || $record->company === 'KPMOG'))
            <p class="travel-rule-notice">Untuk reimbursement KPMOG, pilih ER atau LPJ.
                @if(\App\Filament\Pages\ExpenseReports::canAccess())
                    <a class="travel-control" href="{{ \App\Filament\Pages\ExpenseReports::getUrl() }}">Buka ER</a>
                @endif
                @if($canCreateLpj)
                    <a class="travel-control" href="{{ \App\Filament\Pages\TravelLpj::getUrl(['reimbursement' => 1]) }}">Buat LPJ Reimbursement</a>
                @else
                    Hubungi pengelola Perjalanan Dinas untuk akses LPJ.
                @endif
            </p>
        @endif
        @if($record || $editable)
            <div class="travel-toolbar">
                <x-filament::button color="gray" wire:click="closeRequest" :wire:confirm="$editable ? 'Kembali ke daftar? Perubahan yang belum disimpan akan hilang.' : null">Kembali ke daftar</x-filament::button>
                @if($record)
                    <strong role="status">{{ $record->reference() }} · {{ $statuses[$record->status] }}</strong>
                    <a class="travel-control" href="{{ route('finance.requests.print', $record->id) }}" target="_blank" rel="noopener">Cetak / PDF</a>
                    @if(\App\Services\Finance\KpmogForms::supports($record) || \App\Services\Finance\ApcaForms::supports($record))
                        <a class="travel-control" href="{{ route('finance.requests.excel', $record->id) }}">Unduh Excel master</a>
                    @endif
                    @if($canCreateReimbursement)
                        <x-filament::button tag="a" :href="\App\Filament\Pages\Reimbursements::getUrl($source ? ['pr' => $source->id] : ['pv' => $record->id])">Buat Reimbursement</x-filament::button>
                    @endif
                    @if($canCreateEr)
                        <x-filament::button tag="a" :href="\App\Filament\Pages\ExpenseReports::getUrl(['pv' => $record->id])">Buat ER Penyelesaian</x-filament::button>
                    @elseif($linkedEr)
                        <a class="travel-control" href="{{ \App\Filament\Pages\FinanceRequests::urlFor($linkedEr) }}">Buka {{ $linkedEr->reference() }} · {{ $statuses[$linkedEr->status] }}</a>
                    @endif
                    @if($version !== $record->version)
                        <x-filament::button color="gray" wire:click="openRequest({{ $record->id }})">Muat ulang pengajuan</x-filament::button>
                    @endif
                @endif
            </div>
            @if($record && $record->requesterId() !== (int) $record->created_by)
                <p class="travel-help">Disiapkan oleh {{ $record->creator?->name }} untuk {{ $record->data['applicant'] }}.</p>
            @endif
            @if($source)
                <p>PR sumber <strong>{{ $source->number }}</strong>
                    @if(\App\Services\Scm\Access::authors(auth()->user(), $source))
                        <a class="travel-control" href="{{ \App\Filament\Pages\PurchaseRequests::getUrl(['permintaan' => $source->id]) }}">Buka PR</a>
                    @endif
                </p>
            @endif
            @if($editable)
                <div class="travel-toolbar">
                    @if($canChooseRequester)
                        <x-finance-request.field model="input.requester_id" label="Pemohon"
                            :options="$requesterOptions" live required :disabled="$requesterFromSource" />
                    @endif
                    @if($supportsPurchaseRequest && !$source && !$record?->round)
                        <x-finance-request.field model="input.procurement_id" label="PR APCA sumber"
                            :options="['' => ($requiresPurchaseRequest ? 'Pilih PR APCA' : 'Tanpa referensi PR')] + $purchaseRequests"
                            live :required="$requiresPurchaseRequest" />
                    @endif
                    @if($expenseReport || ($kind !== 'payment' && !$linkedPurchaseRequest && $vouchers))
                        <x-finance-request.field model="input.payment_voucher_id" :label="$expenseReport ? 'Referensi PV' : 'Referensi Payment Request'"
                            :options="['' => ($expenseReport ? 'Tanpa PV' : 'Tanpa Payment Request')] + $vouchers" live :disabled="(bool) $record?->round" />
                    @endif
                    <x-finance-request.field model="input.company" label="Perusahaan" :options="['' => 'Pilih perusahaan'] + $companyOptions" live required :disabled="$fixedCompany !== null || (bool) $record?->round || filled($input['payment_voucher_id'] ?? null)" />
                    <x-finance-request.field model="input.department_id" label="Departemen" :options="['' => 'Pilih departemen'] + $departments" live required :disabled="$onBehalf || $linkedPurchaseRequest || filled($input['payment_voucher_id'] ?? null)" />
                    @unless($expenseReport || $linkedPurchaseRequest || $requiresPurchaseRequest)<div wire:key="lpj-{{ $input['company'] ?? '' }}-{{ $input['department_id'] ?? '' }}">
                        <x-finance-request.field model="input.travel_document_id" label="Referensi SPJ / LPJ" :options="['' => 'Tanpa referensi'] + $lpjs" live />
                    </div>@endunless
                </div>
                @if($requiresPurchaseRequest && !$linkedPurchaseRequest)
                    <p class="travel-help">Buat PR APCA, lalu pilih PR yang sama untuk mengisi kebutuhan dan daftar item.
                        @if(\App\Filament\Pages\PurchaseRequests::canAccess())
                            <a class="travel-control" href="{{ \App\Filament\Pages\PurchaseRequests::getUrl() }}">Buka daftar PR</a>
                        @endif
                    </p>
                @elseif($linkedPurchaseRequest)
                    <p class="travel-help">{{ $kind === 'reimbursement' ? 'Uraian dan nominal mengikuti Payment Request dari PR yang sama. Kirim Reimbursement setelah Payment Request disetujui.' : 'Keperluan dan item diambil dari PR. Lengkapi nominal per item dan kirim setelah PR disetujui.' }}</p>
                @endif
                @if($planError)
                    <p class="travel-rule-notice" role="status">{{ $planError }}
                        <button type="button" class="travel-control" wire:click="$refresh" wire:loading.attr="disabled">Periksa kembali jalur approval</button>
                        @if(\App\Services\Finance\RequestApprovals::canConfigure(auth()->user()))
                            <a class="travel-control" href="{{ \App\Filament\Pages\ScmProcurement::getUrl(['bagian' => 'settings', 'dokumen' => $financeApprovalTabs[$kind]]) }}">Buka Master Approval SCM</a>
                        @endif
                    </p>
                @endif
            @else
                @if(!$expenseReport && $record->paymentVoucher)<p>Referensi {{ $record->paymentVoucher->documentLabel() }} <strong>{{ $record->paymentVoucher->reference() }}</strong></p>@endif
                @if($record->travelDocument)<p>Referensi SPJ / LPJ <strong>{{ $record->travelDocument->number }}</strong></p>@endif
            @endif

            @if($voucherPaper || $expenseReport)
                @include('finance.partials.kpmog-form')
            @else
                @include('filament.pages.partials.finance-request-form')
            @endif

            @if($record)
                <section class="travel-attachments" aria-labelledby="finance-attachments-title">
                    <h2 id="finance-attachments-title">Bukti pendukung</h2>
                    @if($editable && $requiresInvoice && !$hasInvoice)
                        <p role="status">Unggah invoice dengan jenis lampiran <strong>Invoice</strong> sebelum mengirim PV KPMOG.</p>
                    @endif
                    @if($editable && $expenseReport && !$hasEvidence)
                        <p role="status">Unggah struk, bon, atau bukti pengeluaran sebelum mengirim ER.</p>
                    @endif
                    @forelse($record->files as $file)
                        <div class="travel-toolbar" wire:key="finance-file-{{ $file->id }}">
                            <a class="travel-control" href="{{ route('finance.requests.file', $file) }}">{{ $file->original_name }}</a>
                            @if($file->kind === 'invoice')<strong>Invoice</strong>@endif
                            @if($editable)
                                <x-filament::button color="danger" wire:click="removeAttachment({{ $file->id }})"
                                    wire:confirm="Hapus lampiran ini?" aria-label="Hapus {{ $file->original_name }}">Hapus</x-filament::button>
                            @endif
                        </div>
                    @empty
                        <p>Belum ada lampiran.</p>
                    @endforelse
                    @if($editable)
                        <form wire:submit="uploadAttachment" class="travel-toolbar">
                            @if($kind === 'payment')
                                <x-finance-request.field model="attachmentKind" label="Jenis lampiran" :options="$attachmentKinds" required />
                            @endif
                            <div class="travel-context" wire:key="finance-file-input-{{ $version }}">
                                <label for="finance-attachment">Tambah bukti</label>
                                <input id="finance-attachment" type="file" wire:model="attachment" accept=".pdf,.png,.jpg,.jpeg" aria-describedby="finance-file-help" required>
                                <small id="finance-file-help">PDF, JPG, atau PNG hingga 10 MB per berkas.</small>
                            </div>
                            <x-filament::button type="submit" color="gray" wire:loading.attr="disabled">Unggah</x-filament::button>
                        </form>
                    @endif
                </section>
            @elseif($editable)
                <p class="travel-help">{{ $requiresInvoice ? 'Simpan draft, lalu unggah invoice untuk mengirim PV KPMOG.' : ($expenseReport ? 'Simpan draft, lalu unggah bukti pengeluaran untuk mengirim ER.' : 'Simpan draft untuk menambahkan bukti pendukung.') }}</p>
            @endif
            @if($editable)
                <div class="travel-toolbar">
                    <x-filament::button color="gray" wire:click="saveRequest" wire:loading.attr="disabled"
                        :disabled="$requiresPurchaseRequest && !$linkedPurchaseRequest">Simpan draft</x-filament::button>
                    @if(!$onBehalf)
                        <x-filament::button wire:click="submitRequest" wire:confirm="Kirim pengajuan ini ke approver?" wire:loading.attr="disabled"
                            :disabled="blank($input['department_id'] ?? null) || filled($planError) || ($requiresInvoice && !$hasInvoice) || ($expenseReport && !$hasEvidence) || ($requiresPurchaseRequest && !$linkedPurchaseRequest)">Kirim untuk approval</x-filament::button>
                    @endif
                </div>
                @if($onBehalf)<p class="travel-help">{{ $applicant ?: 'Pemohon' }} mengirim draft untuk approval dari akunnya.</p>@endif
            @endif
            @if($record)
                <section class="travel-approval-history" aria-labelledby="finance-approvals-title">
                    <h2 id="finance-approvals-title">Persetujuan online</h2>
                    <p role="status" @if(!$currentStep) hidden @endif>@if($currentStep)Menunggu {{ $currentStep->label }} dari <strong>{{ $currentStep->reviewer_name }}</strong>.@endif</p>
                    @foreach($record->approvals->groupBy('round')->reverse() as $round => $approvals)
                        <div>
                            <h3>Pengajuan ke-{{ $round }}</h3>
                            <ol>
                                @foreach($approvals as $approval)
                                    <li>
                                        <strong>{{ $approval->label }} · {{ $approval->reviewer_name }}</strong>
                                        @if($approval->decided_at)
                                            · {{ $statuses[$approval->status] }} · {{ $approval->decided_at->format('d/m/Y H:i') }}
                                        @else
                                            · {{ $approvals->contains('status', 'returned') ? 'Tahap dilewati' : 'Belum diproses' }}
                                        @endif
                                        @if($approval->note)<p>{{ $approval->note }}</p>@endif
                                    </li>
                                @endforeach
                            </ol>
                        </div>
                    @endforeach
                    @if($record->round === 0)<p>Pengajuan belum dikirim.</p>@endif
                    @if($canDecide)
                        <x-finance-request.field model="note" type="textarea" label="Catatan (wajib saat mengembalikan)" />
                        <div class="travel-toolbar">
                            <x-filament::button wire:click="decideRequest(true)" wire:confirm="Setujui pengajuan ini?" wire:loading.attr="disabled">Setujui</x-filament::button>
                            <x-filament::button color="danger" wire:click="decideRequest(false)" wire:loading.attr="disabled">Kembalikan</x-filament::button>
                        </div>
                    @endif
                </section>
            @endif
        @else
            @if($expenseReport && $canCreateLpj)
                <p>Karyawan KPMOG juga dapat mengajukan reimbursement melalui <a class="travel-control" href="{{ \App\Filament\Pages\TravelLpj::getUrl(['reimbursement' => 1]) }}">LPJ Reimbursement</a>.</p>
            @endif
            <div class="travel-toolbar">
                @if($canCreate)
                    <x-filament::button wire:click="newRequest" wire:loading.attr="disabled">Buat {{ $kind === 'payment' ? ($fixedCompany === 'APCA' ? 'Payment Request' : 'PV') : ($expenseReport ? 'ER' : 'Reimbursement APCA') }}</x-filament::button>
                @endif
                <div class="travel-search">
                    <label for="finance-search">Nomor dokumen, pemohon, atau keperluan</label>
                    <input id="finance-search" type="search" wire:model.live.debounce.350ms="search" maxlength="100">
                </div>
                @unless($fixedCompany)<x-finance-request.field model="companyFilter" label="Perusahaan" :options="['' => 'Semua perusahaan'] + $filterCompanies" live />@endunless
                <x-finance-request.field model="statusFilter" label="Status" :options="['' => 'Semua status'] + $statuses" live />
            </div>
            <div class="travel-list-scroll" role="region" aria-label="Daftar {{ $documentLabel }}" tabindex="0">
                <table class="travel-list">
                    <thead><tr><th>Nomor dokumen</th><th>Perusahaan</th><th>Pemohon / Departemen</th><th>Tanggal</th><th>Total</th><th>Status</th><th>Aksi</th></tr></thead>
                    <tbody>
                        @forelse($records as $item)
                            <tr wire:key="finance-request-{{ $item->id }}">
                                <td>{{ $item->reference() }}<br><small>{{ $item->data['description'] }}</small></td>
                                <td>{{ $item->company }}</td>
                                <td>{{ $item->data['applicant'] }}<br><small>{{ $item->data['department'] }}</small></td>
                                <td>{{ $item->issued_on->format('d/m/Y') }}</td>
                                <td class="travel-money">{{ \App\Services\Scm\Decimal::money($item->total_minor) }}</td>
                                <td>{{ $statuses[$item->status] }}</td>
                                <td><x-filament::button color="gray" wire:click="openRequest({{ $item->id }})" aria-label="Buka {{ $item->reference() }}">Buka</x-filament::button></td>
                            </tr>
                        @empty
                            <tr><td colspan="7">{{ $search || $companyFilter || $statusFilter ? 'Tidak ada dokumen yang cocok. Ubah filter untuk mencari pengajuan lain.' : ($canCreate ? 'Belum ada pengajuan.' : 'Belum ada pengajuan yang ditugaskan kepada Anda.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $records->links() }}
        @endif
    </div>
</x-filament-panels::page>
