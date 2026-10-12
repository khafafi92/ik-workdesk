<?php

namespace App\Filament\Pages;

use App\Enums\AiProvider;
use App\Models\AiAssistantSettings;
use App\Services\AiChatService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AiAssistantSettingsPage extends Page
{
    protected static ?string $slug = 'ai-assistant-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected string $view = 'filament.pages.ai-assistant-settings';

    public string $model = 'gpt-4o-mini';

    public string $provider = 'openai';

    public string $apiKey = '';

    public string $monthlyTokenLimit = '';

    public bool $isEnabled = false;

    public bool $apiKeyConfigured = false;

    public bool $isDefault = false;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user?->is_admin === true || $user?->hasRole('system-admin') === true;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Collaboration';
    }

    public static function getNavigationLabel(): string
    {
        return 'Master Tanya Aku';
    }

    public static function getNavigationSort(): ?int
    {
        return 2;
    }

    public function getTitle(): string|Htmlable
    {
        return 'Master Tanya Aku';
    }

    public function getHeading(): string|Htmlable
    {
        return 'Master Tanya Aku';
    }

    public function mount(): void
    {
        $this->loadProvider();
    }

    public function updatedProvider(): void
    {
        $this->validateOnly('provider', ['provider' => ['required', Rule::enum(AiProvider::class)]]);
        $this->resetValidation();
        $this->loadProvider();
    }

    protected function loadProvider(): void
    {
        abort_unless(static::canAccess(), 403);
        $settings = AiAssistantSettings::current($this->provider);

        $this->apiKey = '';
        $this->model = $settings->model;
        $this->monthlyTokenLimit = $settings->monthly_token_limit > 0
            ? (string) $settings->monthly_token_limit
            : '';
        $this->isEnabled = $settings->is_enabled;
        $this->apiKeyConfigured = $settings->hasApiKey();
        $this->isDefault = $settings->is_default;
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->model = trim($this->model);
        $this->apiKey = trim($this->apiKey);

        $validated = $this->validate([
            'provider' => ['required', Rule::enum(AiProvider::class)],
            'model' => ['required', 'string', 'max:100'],
            'apiKey' => ['nullable', 'string', 'max:500'],
            'monthlyTokenLimit' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'isEnabled' => ['boolean'],
            'isDefault' => ['boolean'],
        ]);

        $settings = AiAssistantSettings::current($validated['provider']);
        $hasApiKey = filled($validated['apiKey']) || $settings->hasApiKey();

        if ($validated['isEnabled'] && ! $hasApiKey) {
            throw ValidationException::withMessages([
                'apiKey' => 'Masukkan API key sebelum mengaktifkan Tanya Aku.',
            ]);
        }

        $settings->model = trim($validated['model']);
        $settings->monthly_token_limit = (int) $validated['monthlyTokenLimit'];
        $settings->is_enabled = (bool) $validated['isEnabled'];
        $settings->is_default = $settings->is_enabled && $validated['isDefault'];

        if (filled($validated['apiKey'])) {
            $settings->api_key = trim($validated['apiKey']);
        }

        DB::transaction(function () use ($settings): void {
            if ($settings->is_default) {
                AiAssistantSettings::query()->where('id', '!=', $settings->id)->update(['is_default' => false]);
            }

            $settings->save();

            if (! AiAssistantSettings::query()->available()->where('is_default', true)->exists()) {
                AiAssistantSettings::query()->available()->orderBy('id')->first()?->update(['is_default' => true]);
            }
        });

        $this->isDefault = $settings->fresh()->is_default;
        $this->apiKey = '';
        $this->apiKeyConfigured = $settings->hasApiKey();

        Notification::make()
            ->title('Pengaturan Tanya Aku tersimpan')
            ->success()
            ->send();
    }

    protected function getViewData(): array
    {
        $settings = AiAssistantSettings::query()->where('provider', $this->provider)->first();
        $usedTokens = app(AiChatService::class)->monthlyTokenUsage($this->provider);
        $limit = $settings?->monthly_token_limit ?? 0;
        $remainingTokens = max(0, $limit - $usedTokens);

        return [
            'providers' => AiProvider::options(),
            'providerLabel' => AiProvider::tryFrom($this->provider)?->label() ?? 'penyedia AI',
            'usedTokens' => $usedTokens,
            'limit' => $limit,
            'remainingTokens' => $remainingTokens,
            'remainingPercent' => $limit > 0
                ? (int) floor($remainingTokens / $limit * 100)
                : null,
            'periodLabel' => now()->translatedFormat('F Y'),
        ];
    }
}
