<x-filament-panels::page>
<link rel="stylesheet" href="{{ asset('css/travel.css') }}?v={{ filemtime(public_path('css/travel.css')) }}">
@php $canDecide = $record && app(\App\Services\Travel\TravelWorkflow::class)->canDecide(auth()->user(), $record); @endphp
<div @class(['travel-workspace', 'travel-editor' => $editable])
    @if(!$missing && !$editable && (!$record || ($record->status === 'submitted' && !$canDecide))) wire:poll.10s.visible="refreshProgress" @endif>
    @if($missing)
        <p role="alert">{{ __('Business Travel is unavailable. Contact your administrator.') }}</p>
    @elseif($this->creating || $record)
        <div class="travel-toolbar"><x-filament::button color="gray" wire:click="closeDocument" wire:confirm="Return to the list? Unsaved changes will be lost.">{{ __('Return to list') }}</x-filament::button>
            @if($record)<strong role="status">{{ $record->displayNumber() }} · {{ $record->statusLabel() }}</strong><a class="travel-control" href="{{ route('travel.print', $record->id) }}" target="_blank" rel="noopener">Print / PDF</a>@endif
            @if($kind === 'lpj' && $record?->status === 'approved' && !$employeeReimbursement
                && \App\Services\Finance\Requests::canCreate(auth()->user(), $record->company === 'APCA' ? 'reimbursement' : 'payment', $record->company)
                && auth()->user()->canAccessDepartment($record->department_id)
                && $record->department?->is_active)
                @if($record->company === 'APCA')
                    <x-filament::button tag="a" color="gray" :href="\App\Filament\Pages\Reimbursements::getUrl(['spj' => $record->id])">Buat Reimbursement</x-filament::button>
                @else
                    <x-filament::button tag="a" :href="\App\Filament\Pages\FinanceRequests::paymentUrl($record->company, ['spj' => $record->id])">Buat PV</x-filament::button>
                @endif
            @endif
        </div>
        @if($errors->any())<div role="alert" class="travel-validation"><strong>{{ __('Review the following errors.') }}</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @if($editable)<p class="travel-help">{{ __('WorkDesk assigns the') }} {{ strtoupper($kind) }} {{ __('number when you save, for example 001/') }}{{ strtoupper($kind) }}/01-2026. Each company uses a yearly sequence. The issue date sets the month. You can correct the number while the document is a draft. @if($kind === 'spd')Select Time TBA if you do not know the time yet. Enter quantity and unit separately, for example 20 and Day. @endif Enter amounts without thousands separators, for example 15000 or 150000,50.</p>@endif
        @if($kind === 'lpj' && !$record)
            <div class="travel-context">
                <label for="travel-lpj-mode">Jenis LPJ</label>
                <select id="travel-lpj-mode" wire:model.live="input.lpj_mode">
                    <option value="spd">LPJ perjalanan dinas dengan SPD</option>
                    <option value="reimbursement">LPJ reimbursement KPMOG</option>
                </select>
            </div>
        @endif
        @if($employeeReimbursement && $editable && !$canChooseRequester)
            <div class="travel-context">
                <label for="travel-lpj-employee">Karyawan</label>
                <select id="travel-lpj-employee" wire:model.live="input.employee_id">
                    <option value="">Pilih karyawan</option>
                    @foreach($employees as $person)<option value="{{ $person->id }}">{{ $person->name }}</option>@endforeach
                </select>
            </div>
        @elseif($kind === 'lpj' && !$record && !$employeeReimbursement)
            <div class="travel-context"><label for="travel-spd-reference">{{ __('Approved SPD') }}</label><select id="travel-spd-reference" wire:model.live="input.spd_id"><option value="">{{ __('Select SPD') }}</option>@foreach($spds as $item)<option value="{{ $item->id }}">{{ $item->number }} · {{ $item->employee_snapshot['name'] }}</option>@endforeach</select>
                @if($spds->isEmpty())<p>{{ __('No approved SPD is available to link to this LPJ.') }}</p>@endif</div>
        @elseif($kind === 'lpj' && $spd)<p>{{ __('Linked SPD:') }} <strong>{{ $spd->number }}</strong></p>@endif
        @if($kind === 'spd' && $editable)
            <div class="travel-spd-context-row">
                <div class="travel-context"><label for="travel-company">{{ __('Company') }}</label><select id="travel-company" wire:model.live="input.company"><option value="APCA">APCA</option><option value="KPMOG">KPMOG</option></select></div>
                <section class="travel-context travel-budget-context" aria-labelledby="travel-budget-heading">
                    <div class="travel-budget-context-heading"><h2 id="travel-budget-heading">{{ __('SPD budget') }}</h2><label for="travel-budget-line">{{ __('Budget allocation') }}</label></div>
                    <select id="travel-budget-line" wire:model.live="input.finance_budget_line_id">
                        <option value="">{{ __('Select a budget allocation') }}</option>
                        @foreach($budgetLineOptions as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
                    </select>
                    @if($budgetUsage && $budgetLine)
                        <p role="status">{{ __('Allocation') }} {{ \App\Services\Scm\Decimal::money($budgetLine->amount_minor, $budgetLine->scope->budget->currency) }}. Used and committed {{ \App\Services\Scm\Decimal::money($budgetUsage['used_minor'], $budgetLine->scope->budget->currency) }}. Remaining {{ \App\Services\Scm\Decimal::money($budgetUsage['available_minor'], $budgetLine->scope->budget->currency) }}.</p>
                    @elseif($budgetLineOptions)
                        <p role="status">{{ __('Select an allocation to see the remaining balance. Submitting the SPD commits the estimated amount.') }}</p>
                    @else
                        <p role="status">{{ __('Contact Finance to set up the budget allocation for this division and project.') }}</p>
                    @endif
                </section>
            </div>
        @elseif($budgetLine && $budgetUsage)
            <section class="travel-context" aria-labelledby="travel-budget-heading"><h2 id="travel-budget-heading">{{ __('Linked SPD budget') }}</h2>
                <p role="status">{{ __('Used and committed') }} {{ \App\Services\Scm\Decimal::money($budgetUsage['used_minor'], $budgetLine->scope->budget->currency) }} {{ __('of allocation') }} {{ \App\Services\Scm\Decimal::money($budgetLine->amount_minor, $budgetLine->scope->budget->currency) }}. Remaining {{ \App\Services\Scm\Decimal::money($budgetUsage['available_minor'], $budgetLine->scope->budget->currency) }}.</p>
            </section>
        @endif
        @if($canArrange)
            <section class="travel-rule-panel" aria-labelledby="travel-administration-heading">
                <h2 id="travel-administration-heading">{{ __('Khafafi Settings') }}</h2>
                @if($canChooseRequester)
                    <div class="travel-context"><label for="travel-requester">Pemohon</label>
                        <select id="travel-requester" wire:model.live="input.requester_id"><option value="">{{ __('Select a requester') }}</option>@foreach($requesters as $user)<option value="{{ $user->id }}">{{ $user->name }} · {{ $user->employee->department?->name }}</option>@endforeach</select>
                        @if($requesters->isEmpty())<p>{{ __('No active users are linked to employee and department records.') }}</p>@endif
                    </div>
                @elseif($requesterSnapshot)
                    <p>Pemohon sesuai SPD: {{ $requesterSnapshot['name'] }}.</p>
                @endif
                <label class="travel-rule-active"><input type="checkbox" wire:model.live="input.arrange_approvals">{{ __('Assign reviewers for this document') }}</label>
                <p class="travel-help">{{ __('Choose the HR reviewer, Finance reviewer, and supervisor for this document. Clear the option to use the standard approval rules. The activity history records the change.') }}</p>
                <span wire:loading wire:target="input.requester_id,input.arrange_approvals,input.approver_ids" role="status">{{ __('Updating settings…') }}</span>
            </section>
        @endif
        @php $values = $this->input; $printing = false; @endphp
        @if($approvalPlanError)<p role="status" class="travel-rule-notice">{{ $approvalPlanError }} @if(!$arrangingApprovals && \App\Services\Travel\TravelAccess::has(auth()->user(),'travel.configure'))<a class="travel-control" href="{{ \App\Filament\Pages\TravelApprovalRules::getUrl() }}">{{ __('Open Approval Settings') }}</a>@endif</p>@elseif($approvalPlan && $editable)<p class="travel-help">@if(($approvalPlan['policy']['source'] ?? '') === 'khafafi')This document uses its assigned reviewers.@else Reviewers follow the rules for {{ \App\Services\Travel\TravelApprovalPolicies::KINDS[$approvalPlan['policy']['document_kind']] }} for the selected company and department.@endif</p>@endif
        <div class="travel-paper-scroll" role="region" aria-label="{{ strtoupper($kind) }} form. Scroll to see all columns." tabindex="0">
            @include('travel.partials.'.$kind)
        </div>
        @if($kind === 'lpj' && $totals)<p class="travel-balance" role="status">@if($totals['balance_minor'] < 0){{ __('Employee owes the company:') }} {{ \App\Services\Travel\TravelPresentation::money(abs($totals['balance_minor'])) }}.@elseif($totals['balance_minor'] > 0)Company owes the employee: {{ \App\Services\Travel\TravelPresentation::money($totals['balance_minor']) }}.@else Expenses match the advance.@endif</p>@endif
        <section class="travel-attachments" aria-labelledby="travel-attachments-heading"><h2 id="travel-attachments-heading">{{ $kind === 'lpj' ? __('Expense receipts') : __('Travel attachments') }}</h2>
            @if($editable && $kind === 'lpj' && ($employeeReimbursement || (($input['company'] ?? '') === 'KPMOG' && ($totals['balance_minor'] ?? 0) > 0)))
                <p>LPJ reimbursement KPMOG memerlukan struk, bon, atau bukti pengeluaran sebelum dikirim.</p>
            @endif
            @foreach($record?->files ?? [] as $file)<div class="travel-toolbar"><a class="travel-control" href="{{ route('travel.file', $file) }}">{{ $file->original_name }}</a>@if($editable)<x-filament::button color="danger" size="sm" wire:click="removeAttachment({{ $file->id }})" wire:confirm="Remove this attachment?">{{ __('Remove attachment') }}</x-filament::button>@endif</div>@endforeach
            @if($editable)<label for="travel-uploads">{{ __('Attach up to 20 PDF, JPG, or PNG files, each up to 10 MB.') }}</label><input id="travel-uploads" type="file" multiple accept="application/pdf,image/png,image/jpeg" wire:model="uploads"><span wire:loading wire:target="uploads" role="status">{{ __('Uploading attachment…') }}</span>@foreach($this->uploads as $file)<span>{{ $file->getClientOriginalName() }}</span>@endforeach @elseif(!$record?->files->count())<p>{{ __('No attachments yet.') }}</p>@endif
        </section>
        @if($editable)
            <div class="travel-toolbar">
                <x-filament::button color="gray" wire:click="saveDraft" wire:loading.attr="disabled" wire:target="saveDraft,submitDocument,uploads">{{ __('Save Draft') }}</x-filament::button>
                @if(!$onBehalf)<x-filament::button wire:click="submitDocument" wire:confirm="Submit this document to the listed reviewers? Editing will be locked during review." wire:loading.attr="disabled" wire:target="saveDraft,submitDocument,uploads">{{ __('Submit for Approval') }}</x-filament::button>@endif
            </div>
            @if($onBehalf)<p class="travel-help">{{ $requesterSnapshot['name'] ?? 'Pemohon' }} menandatangani dan mengirim draft dari akunnya.</p>@endif
            <p class="travel-help">@if($arrangingApprovals)This document uses its assigned reviewers.@else This document uses the standard approval rules.@endif</p>
        @endif
        @if($record)
            @php $currentApproval = $record->status === 'submitted' ? $record->approvals->where('round', $record->approval_round)->firstWhere('status', 'pending') : null; @endphp
            @if($currentApproval)<p role="status">{{ __('Waiting for approval from :name.', ['name' => $currentApproval->assigned_name]) }}</p>@endif
            <section class="travel-approval-history"><h2>{{ __('Online Approvals') }}</h2><ol>@forelse($record->approvals->where('round',$record->approval_round) as $approval)<li><strong>{{ __($approval->role === 'finance' ? \App\Services\Travel\TravelApprovalPolicies::reviewerLabel(\App\Services\Travel\TravelWorkflow::travelType($record->project_numbering_id)) : \App\Services\Travel\TravelWorkflow::ROLES[$approval->role]) }}: {{ $approval->assigned_name }}</strong> · {{ ['pending' => 'Pending', 'approved' => 'Approved', 'returned' => 'Returned'][$approval->status] }}@if($approval->decided_at) · {{ $approval->decided_at->format('d/m/Y H:i') }}@endif @if($approval->reason)<p>{{ $approval->reason }}</p>@endif</li>@empty<li>This document has not been submitted.</li>@endforelse</ol>
            @if($canDecide)<div class="travel-toolbar"><x-filament::button wire:click="approveDocument" wire:confirm="Approve this document and all its costs?" wire:loading.attr="disabled">{{ __('Approve document') }}</x-filament::button></div><label for="travel-return-reason">{{ __('Reason for return') }}</label><textarea id="travel-return-reason" wire:model="returnReason" rows="3"></textarea><x-filament::button color="danger" wire:click="returnDocument" wire:loading.attr="disabled">{{ __('Return for revision') }}</x-filament::button>@endif
            </section><details class="travel-audit"><summary>{{ __('Document history') }}</summary><ol>@foreach($record->events as $event)<li>{{ $event->created_at->format('d/m/Y H:i') }} · {{ $event->actor->name }} · {{ ['draft_created'=>'Draft created','draft_updated'=>'Draft updated','submitted'=>'Submitted','step_approved'=>'Approved','returned'=>'Returned','attachment_removed'=>'Attachment deleted'][$event->action] ?? $event->action }}</li>@endforeach</ol></details>
        @endif
    @else
        <div class="travel-toolbar">@if($canCreate)<x-filament::button wire:click="createDocument">{{ __('Create') }} {{ strtoupper($kind) }}</x-filament::button>@endif<div class="travel-search"><label for="travel-search">{{ __('Find an employee number or name') }}</label><input id="travel-search" type="search" wire:model.live.debounce.350ms="search"></div><div><label for="travel-status">{{ __('Status') }}</label><select id="travel-status" wire:model.live="statusFilter"><option value="">{{ __('All statuses') }}</option><option value="draft">{{ __('Draft') }}</option><option value="submitted">{{ __('Awaiting approval') }}</option><option value="returned">{{ __('Returned') }}</option><option value="approved">{{ __('Approved') }}</option></select></div></div>
        <div class="travel-list-scroll" role="region" aria-label="{{ __('List') }} {{ strtoupper($kind) }}" tabindex="0"><table class="travel-list"><thead><tr><th>{{ __('Document number') }}</th><th>{{ __('Company') }}</th><th>{{ __('Employee / Department') }}</th><th>{{ __('Travel period') }}</th><th>{{ __('Total cost') }}</th><th>{{ __('Status') }}</th><th>{{ __('Actions') }}</th></tr></thead><tbody>@forelse($records as $document)<tr wire:key="travel-document-{{ $document->id }}"><td>{{ $document->displayNumber() }}</td><td>{{ $document->company }}</td><td>{{ $document->employee_snapshot['name'] }}<br><small>{{ $document->employee_snapshot['department'] }}</small></td><td>{{ \App\Services\Travel\TravelPresentation::date($document->data['depart_at']) }} to {{ \App\Services\Travel\TravelPresentation::date($document->data['return_at']) }}</td><td class="travel-money">{{ \App\Services\Travel\TravelPresentation::money($document->total_minor) }}</td><td>{{ $document->statusLabel() }}</td><td><x-filament::button color="gray" wire:click="openDocument({{ $document->id }})">{{ __('Open') }}</x-filament::button></td></tr>@empty<tr><td colspan="7">{{ __('No') }} {{ strtoupper($kind) }} {{ __('match your filters.') }}</td></tr>@endforelse</tbody></table></div>{{ $records->links() }}
    @endif
</div>
</x-filament-panels::page>
