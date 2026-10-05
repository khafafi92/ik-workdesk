<?php

namespace App\Filament\Pages;

use App\Events\GlobalChatMessageCreated;
use App\Models\GlobalChatMessage;
use App\Models\PermitCompany;
use App\Models\User;
use App\Services\GlobalChatAccessService;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Throwable;

class GlobalChatRoom extends Page
{
    use WithFileUploads;

    protected static string $routePath = 'global-chat-room';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected string $view = 'filament.pages.global-chat-room';

    #[Locked]
    public ?int $selectedCompanyId = null;

    #[Locked]
    public int $loadedMessageCount = 50;

    public string $message = '';

    /** @var array<int, TemporaryUploadedFile> */
    public array $attachments = [];

    public bool $realtimeConnected = false;

    public function mount(): void
    {
        $this->selectedCompanyId = $this->companies()->first()?->id;
    }

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Collaboration';
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    public static function getNavigationLabel(): string
    {
        return 'Chat Room Global';
    }

    public function getTitle(): string|Htmlable
    {
        return 'Chat Room Global';
    }

    public function getHeading(): string|Htmlable
    {
        return 'Chat Room Global';
    }

    public function selectCompany(int $companyId): void
    {
        abort_unless($this->canAccessCompany($companyId), 403);

        $this->selectedCompanyId = $companyId;
        $this->loadedMessageCount = 50;
        $this->reset('message', 'attachments');
    }

    public function loadOlderMessages(): void
    {
        $company = $this->selectedCompany();

        if ($company === null) {
            return;
        }

        $total = GlobalChatMessage::query()
            ->where('permit_company_id', $company->getKey())
            ->count();

        $this->loadedMessageCount = min($this->loadedMessageCount + 50, $total);
    }

    public function removeAttachment(int $index): void
    {
        if (! array_key_exists($index, $this->attachments)) {
            return;
        }

        unset($this->attachments[$index]);
        $this->attachments = array_values($this->attachments);
    }

    public function addMessage(): void
    {
        $company = $this->selectedCompany();
        abort_unless($company !== null, 403);

        $this->validate([
            'message' => ['nullable', 'string', 'max:5000'],
            'attachments' => ['array', 'max:10'],
            'attachments.*' => [
                'file',
                'max:10240',
                'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png',
            ],
        ]);

        $body = trim($this->message);

        if ($body === '' && $this->attachments === []) {
            throw ValidationException::withMessages([
                'message' => 'Tulis pesan atau pilih lampiran sebelum mengirim.',
            ]);
        }

        $storedPaths = [];

        try {
            $chatMessage = DB::transaction(function () use ($company, $body, &$storedPaths): GlobalChatMessage {
                $chatMessage = GlobalChatMessage::query()->create([
                    'permit_company_id' => $company->getKey(),
                    'user_id' => auth()->id(),
                    'body' => $body === '' ? null : $body,
                ]);

                foreach ($this->attachments as $file) {
                    $path = $file->store('global-chat/'.$company->getKey(), 'local');

                    if ($path === false) {
                        throw new \RuntimeException('Lampiran tidak dapat disimpan.');
                    }

                    $storedPaths[] = $path;

                    $chatMessage->attachments()->create([
                        'disk' => 'local',
                        'path' => $path,
                        'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                        'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                        'size' => $file->getSize(),
                    ]);
                }

                return $chatMessage;
            });
        } catch (Throwable $exception) {
            foreach ($storedPaths as $path) {
                Storage::disk('local')->delete($path);
            }

            throw $exception;
        }

        if ($this->realtimeBroadcastingIsConfigured()) {
            GlobalChatMessageCreated::dispatch(
                (int) $company->getKey(),
                (int) $chatMessage->getKey(),
            );
        }

        $this->reset('message', 'attachments');
        $this->loadedMessageCount = max(
            50,
            min(
                $this->loadedMessageCount,
                GlobalChatMessage::query()
                    ->where('permit_company_id', $company->getKey())
                    ->count()
            )
        );

        Notification::make()
            ->title('Pesan terkirim')
            ->success()
            ->send();
    }

    public function refreshMessages(): void
    {
        abort_unless(
            $this->selectedCompanyId === null
                || $this->canAccessCompany($this->selectedCompanyId),
            403
        );
    }

    /**
     * @return Collection<int, PermitCompany>
     */
    private function companies(): Collection
    {
        return app(GlobalChatAccessService::class)
            ->companiesFor($this->currentUser())
            ->get();
    }

    private function selectedCompany(): ?PermitCompany
    {
        if ($this->selectedCompanyId === null) {
            return null;
        }

        return app(GlobalChatAccessService::class)
            ->companiesFor($this->currentUser())
            ->whereKey($this->selectedCompanyId)
            ->first();
    }

    private function canAccessCompany(int $companyId): bool
    {
        return app(GlobalChatAccessService::class)
            ->canAccessCompany($this->currentUser(), $companyId);
    }

    private function currentUser(): User
    {
        return auth()->user();
    }

    private function realtimeBroadcastingIsConfigured(): bool
    {
        return config('broadcasting.default') === 'reverb'
            && filled(config('broadcasting.connections.reverb.key'));
    }

    protected function getViewData(): array
    {
        $companies = $this->companies();
        $company = $this->selectedCompany();

        $messages = $company === null
            ? collect()
            : GlobalChatMessage::query()
                ->with(['user.employee', 'attachments'])
                ->where('permit_company_id', $company->getKey())
                ->latest('id')
                ->limit($this->loadedMessageCount)
                ->get()
                ->reverse()
                ->values();

        return [
            'companies' => $companies,
            'company' => $company,
            'messages' => $messages,
            'hasOlderMessages' => $company !== null
                && GlobalChatMessage::query()
                    ->where('permit_company_id', $company->getKey())
                    ->count() > $messages->count(),
            'realtimeConfigured' => $this->realtimeBroadcastingIsConfigured(),
        ];
    }
}
