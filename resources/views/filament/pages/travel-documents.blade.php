<x-filament-panels::page>
<link rel="stylesheet" href="{{ asset('css/travel.css') }}?v={{ filemtime(public_path('css/travel.css')) }}">
<div @class(['travel-workspace', 'travel-editor' => $editable])>
    @if($missing)
        <p role="alert">Business Travel is unavailable. Contact your administrator.</p>
    @elseif($this->creating || $record)
        <div class="travel-toolbar"><x-filament::button color="gray" wire:click="closeDocument" wire:confirm="Return to the list? Unsaved changes will be lost.">Return to list</x-filament::button>
            @if($record)<strong>{{ $record->displayNumber() }} · {{ $record->statusLabel() }}</strong><a class="travel-control" href="{{ route('travel.print', $record->id) }}" target="_blank" rel="noopener">Print / PDF</a>@endif
        </div>
        @if($errors->any())<div role="alert" class="travel-validation"><strong>Review the following errors.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @if($editable)<p class="travel-help">WorkDesk assigns the {{ strtoupper($kind) }} number when you save, for example 001/{{ strtoupper($kind) }}/01-2026. Each company uses a yearly sequence. The issue date sets the month. You can correct the number while the document is a draft. @if($kind === 'spd')Select Time TBA if you do not know the time yet. Enter quantity and unit separately, for example 20 and Day. @endif Enter amounts without thousands separators, for example 15000 or 150000,50.</p>@endif
        @if($kind === 'lpj' && !$record)
            <div class="travel-context"><label for="travel-spd-reference">Approved SPD</label><select id="travel-spd-reference" wire:model.live="input.spd_id"><option value="">Select SPD</option>@foreach($spds as $item)<option value="{{ $item->id }}">{{ $item->number }} · {{ $item->employee_snapshot['name'] }}</option>@endforeach</select>
                @if($spds->isEmpty())<p>No approved SPD is available to link to this LPJ.</p>@endif</div>
        @elseif($kind === 'lpj' && $spd)<p>Linked SPD: <strong>{{ $spd->number }}</strong></p>@endif
        @if($kind === 'spd' && $editable)
            <div class="travel-spd-context-row">
                <div class="travel-context"><label for="travel-company">Company</label><select id="travel-company" wire:model.live="input.company"><option value="APCA">APCA</option><option value="KPMOG">KPMOG</option></select></div>
                <section class="travel-context travel-budget-context" aria-labelledby="travel-budget-heading">
                    <div class="travel-budget-context-heading"><h2 id="travel-budget-heading">SPD budget</h2><label for="travel-budget-line">Budget allocation</label></div>
                    <select id="travel-budget-line" wire:model.live="input.finance_budget_line_id">
                        <option value="">Select a budget allocation</option>
                        @foreach($budgetLineOptions as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
                    </select>
                    @if($budgetUsage && $budgetLine)
                        <p role="status">Allocation {{ \App\Services\Scm\Decimal::money($budgetLine->amount_minor, $budgetLine->scope->budget->currency) }}. Used and committed {{ \App\Services\Scm\Decimal::money($budgetUsage['used_minor'], $budgetLine->scope->budget->currency) }}. Remaining {{ \App\Services\Scm\Decimal::money($budgetUsage['available_minor'], $budgetLine->scope->budget->currency) }}.</p>
                    @elseif($budgetLineOptions)
                        <p role="status">Select an allocation to see the remaining balance. Submitting the SPD commits the estimated amount.</p>
                    @else
                        <p role="status">Contact Finance to set up the budget allocation for this division and project.</p>
                    @endif
                </section>
            </div>
        @elseif($budgetLine && $budgetUsage)
            <section class="travel-context" aria-labelledby="travel-budget-heading"><h2 id="travel-budget-heading">Linked SPD budget</h2>
                <p role="status">Used and committed {{ \App\Services\Scm\Decimal::money($budgetUsage['used_minor'], $budgetLine->scope->budget->currency) }} of allocation {{ \App\Services\Scm\Decimal::money($budgetLine->amount_minor, $budgetLine->scope->budget->currency) }}. Remaining {{ \App\Services\Scm\Decimal::money($budgetUsage['available_minor'], $budgetLine->scope->budget->currency) }}.</p>
            </section>
        @endif
        @if($canArrange)
            <section class="travel-rule-panel" aria-labelledby="travel-administration-heading">
                <h2 id="travel-administration-heading">Khafafi Settings</h2>
                <div class="travel-context"><label for="travel-requester">Creator / requester</label>
                    <select id="travel-requester" wire:model.live="input.requester_id"><option value="">Select a requester</option>@foreach($requesters as $user)<option value="{{ $user->id }}">{{ $user->name }} · {{ $user->employee->department?->name }}</option>@endforeach</select>
                    @if($requesters->isEmpty())<p>No active users are linked to employee and department records.</p>@endif
                </div>
                <label class="travel-rule-active"><input type="checkbox" wire:model.live="input.arrange_approvals">Assign reviewers for this document</label>
                <p class="travel-help">Choose the HR reviewer, Finance reviewer, and supervisor for this document. Clear the option to use the standard approval rules. The activity history records the change.</p>
                <span wire:loading wire:target="input.requester_id,input.arrange_approvals,input.approver_ids" role="status">Updating settings…</span>
            </section>
        @endif
        @php $values = $this->input; $printing = false; @endphp
        @if($approvalPlanError)<p role="status" class="travel-rule-notice">{{ $approvalPlanError }} @if(!$arrangingApprovals && \App\Services\Travel\TravelAccess::has(auth()->user(),'travel.configure'))<a class="travel-control" href="{{ \App\Filament\Pages\TravelApprovalRules::getUrl() }}">Open Approval Settings</a>@endif</p>@elseif($approvalPlan && $editable)<p class="travel-help">@if(($approvalPlan['policy']['source'] ?? '') === 'khafafi')This document uses its assigned reviewers.@else Reviewers follow the rules for {{ \App\Services\Travel\TravelApprovalPolicies::KINDS[$approvalPlan['policy']['document_kind']] }} for the selected company and department.@endif</p>@endif
        <div class="travel-paper-scroll" role="region" aria-label="{{ strtoupper($kind) }} form. Scroll to see all columns." tabindex="0">
            @include('travel.partials.'.$kind)
        </div>
        @if($kind === 'lpj' && $totals)<p class="travel-balance" role="status">@if($totals['balance_minor'] < 0)Employee owes the company: {{ \App\Services\Travel\TravelPresentation::money(abs($totals['balance_minor'])) }}.@elseif($totals['balance_minor'] > 0)Company owes the employee: {{ \App\Services\Travel\TravelPresentation::money($totals['balance_minor']) }}.@else Expenses match the advance.@endif</p>@endif
        <section class="travel-attachments" aria-labelledby="travel-attachments-heading"><h2 id="travel-attachments-heading">{{ $kind === 'lpj' ? 'Expense receipts' : 'Travel attachments' }}</h2>
            @foreach($record?->files ?? [] as $file)<div class="travel-toolbar"><a class="travel-control" href="{{ route('travel.file', $file) }}">{{ $file->original_name }}</a>@if($editable)<x-filament::button color="danger" size="sm" wire:click="removeAttachment({{ $file->id }})" wire:confirm="Remove this attachment?">Remove attachment</x-filament::button>@endif</div>@endforeach
            @if($editable)<label for="travel-uploads">Attach up to 20 PDF, JPG, or PNG files, each up to 10 MB.</label><input id="travel-uploads" type="file" multiple accept="application/pdf,image/png,image/jpeg" wire:model="uploads"><span wire:loading wire:target="uploads" role="status">Uploading attachment…</span>@foreach($this->uploads as $file)<span>{{ $file->getClientOriginalName() }}</span>@endforeach @elseif(!$record?->files->count())<p>No attachments yet.</p>@endif
        </section>
        @if($editable)<div class="travel-toolbar"><x-filament::button color="gray" wire:click="saveDraft" wire:loading.attr="disabled" wire:target="saveDraft,submitDocument,uploads">Save Draft</x-filament::button><x-filament::button wire:click="submitDocument" wire:confirm="Submit this document to the listed reviewers? Editing will be locked during review." wire:loading.attr="disabled" wire:target="saveDraft,submitDocument,uploads">Submit for Approval</x-filament::button></div><p class="travel-help">@if($arrangingApprovals)This document uses its assigned reviewers.@else This document uses the standard approval rules.@endif</p>@endif
        @if($record)
            <section class="travel-approval-history"><h2>Online Approvals</h2><ol>@forelse($record->approvals->where('round',$record->approval_round) as $approval)<li><strong>{{ __($approval->role === 'finance' ? \App\Services\Travel\TravelApprovalPolicies::reviewerLabel(\App\Services\Travel\TravelWorkflow::travelType($record->project_numbering_id)) : \App\Services\Travel\TravelWorkflow::ROLES[$approval->role]) }}: {{ $approval->assigned_name }}</strong> · {{ ['pending' => 'Pending', 'approved' => 'Approved', 'returned' => 'Returned'][$approval->status] }}@if($approval->decided_at) · {{ $approval->decided_at->format('d/m/Y H:i') }}@endif @if($approval->reason)<p>{{ $approval->reason }}</p>@endif</li>@empty<li>This document has not been submitted.</li>@endforelse</ol>
            @if(app(\App\Services\Travel\TravelWorkflow::class)->canDecide(auth()->user(),$record))<div class="travel-toolbar"><x-filament::button wire:click="approveDocument" wire:confirm="Approve this document and all its costs?" wire:loading.attr="disabled">Approve document</x-filament::button></div><label for="travel-return-reason">Reason for return</label><textarea id="travel-return-reason" wire:model="returnReason" rows="3"></textarea><x-filament::button color="danger" wire:click="returnDocument" wire:loading.attr="disabled">Return for revision</x-filament::button>@endif
            </section><details class="travel-audit"><summary>Document history</summary><ol>@foreach($record->events as $event)<li>{{ $event->created_at->format('d/m/Y H:i') }} · {{ $event->actor->name }} · {{ ['draft_created'=>'Draft created','draft_updated'=>'Draft updated','submitted'=>'Submitted','step_approved'=>'Approved','returned'=>'Returned','attachment_removed'=>'Attachment deleted'][$event->action] ?? $event->action }}</li>@endforeach</ol></details>
        @endif
    @else
        <div class="travel-toolbar">@if($canCreate)<x-filament::button wire:click="createDocument">Create {{ strtoupper($kind) }}</x-filament::button>@endif<div class="travel-search"><label for="travel-search">Find an employee number or name</label><input id="travel-search" type="search" wire:model.live.debounce.350ms="search"></div><div><label for="travel-status">Status</label><select id="travel-status" wire:model.live="statusFilter"><option value="">All statuses</option><option value="draft">Draft</option><option value="submitted">Awaiting approval</option><option value="returned">Returned</option><option value="approved">Approved</option></select></div></div>
        <div class="travel-list-scroll" role="region" aria-label="List {{ strtoupper($kind) }}" tabindex="0"><table class="travel-list"><thead><tr><th>Document number</th><th>Company</th><th>Employee / Department</th><th>Travel period</th><th>Total cost</th><th>Status</th><th>Actions</th></tr></thead><tbody>@forelse($records as $document)<tr wire:key="travel-document-{{ $document->id }}"><td>{{ $document->displayNumber() }}</td><td>{{ $document->company }}</td><td>{{ $document->employee_snapshot['name'] }}<br><small>{{ $document->employee_snapshot['department'] }}</small></td><td>{{ \App\Services\Travel\TravelPresentation::date($document->data['depart_at']) }} to {{ \App\Services\Travel\TravelPresentation::date($document->data['return_at']) }}</td><td class="travel-money">{{ \App\Services\Travel\TravelPresentation::money($document->total_minor) }}</td><td>{{ $document->statusLabel() }}</td><td><x-filament::button color="gray" wire:click="openDocument({{ $document->id }})">Open</x-filament::button></td></tr>@empty<tr><td colspan="7">No {{ strtoupper($kind) }} match your filters.</td></tr>@endforelse</tbody></table></div>{{ $records->links() }}
    @endif
</div>
</x-filament-panels::page>
