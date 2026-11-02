@if($canChooseRequester ?? false)
    <x-scm.select model="draft.requester_id" label="Requestor" :options="$requesterOptions" :live="true" required />
@else
    <div class="scm-field"><label for="{{ $requestorFieldId }}">Requestor</label><input id="{{ $requestorFieldId }}" value="{{ $requestorFieldName }}" readonly aria-readonly="true"></div>
@endif
