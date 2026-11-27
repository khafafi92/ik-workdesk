<?php

namespace App\Livewire;

use App\Services\Scm\ServiceReceipts;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

class ScmServiceReceipts extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $orderId;

    #[Locked]
    public bool $readOnly;

    #[Locked]
    public string $token;

    public array $receipt = [];

    public $evidence;

    public function mount(int $orderId, bool $readOnly = false): void
    {
        $this->orderId = $orderId;
        $this->readOnly = $readOnly;
        $this->resetForm();
    }

    public function save(ServiceReceipts $service): void
    {
        abort_unless(auth()->check() && ! $this->readOnly, 403);
        $service->receive(auth()->user(), $this->orderId, [...$this->receipt, 'token' => $this->token], $this->evidence);
        $this->resetForm();
        $this->resetValidation();

        Notification::make()->title('Penerimaan jasa tersimpan.')->success()->send();
    }

    private function resetForm(): void
    {
        $this->token = (string) Str::uuid();
        $this->receipt = ['received_on' => today()->toDateString(), 'reference' => '', 'notes' => '', 'quantities' => []];
        $this->evidence = null;
    }

    public function render(): View
    {
        abort_unless(auth()->check(), 403);
        $service = app(ServiceReceipts::class);
        $order = $service->order(auth()->user(), $this->orderId);
        $history = $order->serviceReceipts()->with('lines')->latest('id')->get();
        $progress = $service->progress($order, $history);
        $canRecord = ! $this->readOnly && $service->canReceive(auth()->user(), $order);

        return view('livewire.scm-service-receipts', compact('order', 'progress', 'history', 'canRecord'));
    }
}
