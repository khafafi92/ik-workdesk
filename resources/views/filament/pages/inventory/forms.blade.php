<section class="scm-panel" aria-label="{{ __('Inventory login') }}">
    @if($formMode === 'catalogue')
        <form wire:submit="saveItem">
            <h2>{{ $catalogueId ? __('Edit the Master of Things') : __('Add Master of Things') }}</h2>
            <div class="scm-grid">
                <x-scm.field model="catalogueForm.code" label="{{ __('Goods code') }}" required />
                <x-scm.field model="catalogueForm.name" label="{{ __('The name of the item') }}" required />
                <x-scm.select model="catalogueForm.kind" label="{{ __('Type of goods') }}" :options="$editableKinds" :disabled="$catalogueId > 0" required />
                <x-scm.field model="catalogueForm.category" label="{{ __('Category') }}" />
                <x-scm.field model="catalogueForm.unit" label="{{ __('Unit') }}" required />
                <x-scm.field model="catalogueForm.brand" label="{{ __('Brand name') }}" />
                <x-scm.field model="catalogueForm.model" label="Model/part number" />
                <x-scm.field model="catalogueForm.size" label="{{ __('Size') }}" />
                <x-scm.select model="catalogueForm.is_active" label="{{ __('Status') }}" :options="[1=>'Aktif',0=>'Nonaktif']" required />
            </div>
            <x-scm.field model="catalogueForm.specification" label="{{ __('Goods specification') }}" multiline />
            <div class="scm-actions"><button type="submit" wire:loading.attr="disabled">{{ __('Save the Master of Things') }}</button><button type="button" wire:click="closeForm">{{ __('Cancel') }}</button></div>
            <p class="scm-help">{{ __('Master change doesn\'t change the content of saved the purchase request. The transaction unit is kept.') }}</p>
        </form>
    @elseif($formMode === 'allocation' && $allocationItem)
        <form wire:submit="allocate">
            <h2>{{ __('Office supplies balance mapping:') }} {{ $allocationItem->name }}</h2>
            <p>{{ __('Unmapped old balance:') }} {{ $allocationAvailable }} {{ $allocationItem->unit }}{{ __('Set the number of each entity based on stock data. Total number remains the same.') }}</p>
            <div class="scm-grid"><x-scm.field model="allocation.KPMOG" label="{{ __('Sum to the KPMOG HO warehouse') }}" inputmode="decimal" required /><x-scm.field model="allocation.APCA" label="{{ __('Number to H.O. APCA warehouse') }}" inputmode="decimal" required /></div>
            <div class="scm-actions"><button type="submit" wire:loading.attr="disabled">{{ __('Save entity mapping') }}</button><button type="button" wire:click="closeForm">{{ __('Cancel') }}</button></div>
        </form>
    @elseif($formMode === 'stock')
        <form wire:submit="saveStock">
            <h2>{{ __('Note the warehouse mutation') }}</h2>
            <div class="scm-grid">
                <x-scm.select model="stockForm.operation" label="{{ __('Transaction') }}" :options="!$canAdminister ? ['outgoing'=>'Distribusi / stok keluar'] : ($stockForm['balance_id'] ? ['incoming'=>'Stok masuk','outgoing'=>'Distribusi / stok keluar','transfer'=>'Transfer gudang','department_transfer'=>'Pindah ke gudang departemen asal'] : ['incoming'=>'Stok awal/masuk'])" :live="true" required />
                <x-scm.select model="stockForm.warehouse_id" label="{{ __('Source warehouse') }}" :options="$warehouseOptions" :disabled="filled($stockForm['balance_id'])" required />
                <x-scm.select model="stockForm.inventory_item_id" label="{{ __('Item') }}" :options="$itemOptions" :disabled="filled($stockForm['balance_id'])" required />
                <x-scm.select model="stockForm.department_id" label="{{ __('Recording Department') }}" :options="$departmentOptions" :disabled="filled($stockForm['balance_id'])" />
                @if($stockForm['operation'] === 'transfer')<x-scm.select model="stockForm.destination_id" label="{{ __('The warehouse of destination') }}" :options="$warehouseOptions" required />@endif
                <x-scm.field model="stockForm.quantity" label="{{ __('Quantity') }}" inputmode="decimal" required />
            </div>
            <x-scm.field model="stockForm.notes" label="{{ __('References and reasons for mutations') }}" multiline required />
            <p class="scm-help">{{ __('Move items from the main warehouse to the warehouse of the the purchase request department. For distribution, select stock out of the department warehouse and write recipients or department destinations on reference.') }}</p>
            <div class="scm-actions"><button type="submit" wire:loading.attr="disabled">{{ __('Save warehouse mutation') }}</button><button type="button" wire:click="closeForm">{{ __('Cancel') }}</button></div>
        </form>
    @elseif($formMode === 'receipt' && $receiptRequest)
        <form wire:submit="receive">
            <h2>{{ __('Reception of items:') }} {{ $receiptRequest->number }}</h2>
            <p>PO: {{ $receiptRequest->purchaseOrder?->number }}</p>
            <p>{{ __('Fill in the amount of physical received on this delivery, fill in zero for unreceived lines, Masters and Units should match the items on the purchase request.') }}</p>
            <div class="scm-grid">
                <x-scm.select model="receiptForm.warehouse_id" label="{{ __('The warehouse of destination') }}" :options="$warehouseOptions" required />
                <x-scm.field model="receiptForm.received_on" label="{{ __('Date received') }}" type="date" required />
                <x-scm.field model="receiptForm.reference" label="{{ __('Street mail number/receivement evidence') }}" required />
            </div>
            @foreach($receiptLines as $index=>$line)
                @if($source=$receiptItems->get($line['request_item_id']))
                <fieldset class="scm-item" wire:key="receipt-line-{{ $source->id }}"><legend>{{ $source->name }}</legend>
                    <p>PR: {{ $source->quantity_milli/1000 }} {{ $source->unit }}{{ __('It\'s been accepted:') }} {{ ($receiptReceived[$source->id]??0)/1000 }}. Remaining: {{ ($source->quantity_milli-($receiptReceived[$source->id]??0))/1000 }}.</p>
                    <div class="scm-grid">
                        <x-scm.select model="receiptLines.{{ $index }}.inventory_item_id" label="{{ __('Master of Things') }}" :options="$itemOptions" :live="true" :disabled="filled($source->inventory_item_id)" />
                        <x-scm.field model="receiptLines.{{ $index }}.quantity" label="{{ __('Number accepted this time') }}" inputmode="decimal" required />
                    </div>
                    @if(! $source->inventory_item_id)
                        @if($receiptNewItemSource === $source->id && ! filled($line['inventory_item_id'] ?? null))
                            <div class="scm-item"><h3>{{ __('Add Master of goods:') }} {{ $source->name }}</h3>
                                <p>{{ __('Check if the item already exists before making a new master. Units follow the purchase request:') }} {{ $source->unit }}.</p>
                                <div class="scm-grid">
                                    <x-scm.field model="receiptNewItem.code" label="{{ __('Goods code') }}" required />
                                    <x-scm.field model="receiptNewItem.name" label="{{ __('The name of the item') }}" required />
                                    <x-scm.select model="receiptNewItem.kind" label="{{ __('Type of goods') }}" :options="$editableKinds" required />
                                    <x-scm.field model="receiptNewItem.brand" label="{{ __('Brand name') }}" />
                                    <x-scm.field model="receiptNewItem.model" label="Model/part number" />
                                    <x-scm.field model="receiptNewItem.specification" label="{{ __('Goods specification') }}" multiline />
                                </div>
                                <p class="scm-help">{{ __('The master was saved when the button below was pressed. The stock increased after the storage reception was saved.') }}</p>
                                <div class="scm-actions"><button type="button" wire:click="saveReceiptItem" wire:loading.attr="disabled" wire:target="saveReceiptItem">{{ __('Save and select a Master of goods') }}</button><button type="button" wire:click="cancelReceiptItem">{{ __('Cancel the master add') }}</button></div>
                            </div>
                        @elseif(! filled($line['inventory_item_id'] ?? null))
                            <div class="scm-actions"><button type="button" wire:click="newReceiptItem({{ $source->id }})">{{ __('Create catalogue item for') }} {{ $source->name }}</button></div>
                        @endif
                    @endif
                </fieldset>
                @endif
            @endforeach
            <x-scm.field model="receiptForm.notes" label="{{ __('Receipt log') }}" multiline />
            <p class="scm-help">{{ __('This reception adds stock to the warehouse, and the office supplies requests keep confirming the reception after the officer gives up the goods.') }}</p>
            <div class="scm-actions"><button type="submit" wire:loading.attr="disabled">{{ __('Save storage reception') }}</button><button type="button" wire:click="closeForm">{{ __('Cancel') }}</button></div>
        </form>
    @endif
</section>
