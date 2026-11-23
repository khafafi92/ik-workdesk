<x-filament-panels::page>
    <div class="scm-workspace">
        @if($errors->any())<div class="scm-error" role="alert"><h2>Check the signature image</h2><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <section class="scm-panel">
            <h2>TTD {{ auth()->user()->name }}</h2>
            <p>Save your signature image for PR/SR. Each document will need you to sign at the stage that is your task by pressing the Signature button.</p>
            @if($signature)
                <img class="scm-profile-signature" src="{{ route('scm.signature-profile', $signature) }}" alt="Sign your profile" width="240" height="100">
                <p class="scm-help">Replacing images in the profile does not change the TTD on signed documents.</p>
            @else
                <p>No signatures in the profile yet.</p>
            @endif
            <form wire:submit="saveSignature">
                <fieldset wire:loading.attr="disabled" wire:target="signatureFile,saveSignature">
                    <legend class="scm-sheet-sr">Upload profile signature</legend>
                    <x-scm.field model="signatureFile" label="TTD PNG/JPG image" type="file" accept="image/png,image/jpeg" required help="Maximum 2 MB and 4096 × 4096 pixels. Use white or transparent images." />
                    <div class="scm-actions"><x-filament::button type="submit">{{ $signature ? 'Replace profile signature' : 'Save profile TTD' }}</x-filament::button></div>
                </fieldset>
                <p role="status" wire:loading wire:target="signatureFile,saveSignature">Uploading signature. Please wait...</p>
            </form>
        </section>
    </div>
</x-filament-panels::page>
