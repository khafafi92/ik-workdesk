<?php

namespace App\Filament\Pages;

use App\Models\AiAssistantSettings;
use App\Services\OpenAiChatService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\ValidationException;

class AiAssistantSettingsPage extends Page
{
    protected static ?string $slug = 'ai-assistant-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected string $view = 'filament.pages.ai-assistant-settings';

    public string $model = 'gpt-4o-mini';

    public string $apiKey = '';

    public string $monthlyTokenLimit = '';

    public bool $isEnabled = false;

    public bool $apiKeyConfigured = false;

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
        $settings = AiAssistantSettings::current();

        $this->model = $settings->model;
        $this->monthlyTokenLimit = $settings->monthly_token_limit > 0
            ? (string) $settings->monthly_token_limit
            : '';
        $this->isEnabled = $settings->is_enabled;
        $this->apiKeyConfigured = $settings->hasApiKey();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'model' => ['required', 'string', 'max:100'],
            'apiKey' => ['nullable', 'string', 'max:500'],
            'monthlyTokenLimit' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'isEnabled' => ['boolean'],
        ]);

        $settings = AiAssistantSettings::current();
        $hasApiKey = filled($validated['apiKey']) || $settings->hasApiKey();

        if ($validated['isEnabled'] && ! $hasApiKey) {
            throw ValidationException::withMessages([
                'apiKey' => 'Masukkan API key sebelum mengaktifkan Tanya Aku.',
            ]);
        }

        $settings->model = trim($validated['model']);
        $settings->monthly_token_limit = (int) $validated['monthlyTokenLimit'];
        $settings->is_enabled = (bool) $validated['isEnabled'];

        if (filled($validated['apiKey'])) {
            $settings->api_key = trim($validated['apiKey']);
        }

        $settings->save();
        $this->apiKey = '';
        $this->apiKeyConfigured = $settings->hasApiKey();

        Notification::make()
            ->title('Pengaturan Tanya Aku tersimpan')
            ->success()
            ->send();
    }

    protected function getViewData(): array
    {
        $settings = AiAssistantSettings::current();
        $usedTokens = app(OpenAiChatService::class)->monthlyTokenUsage();
        $limit = $settings->monthly_token_limit;
        $remainingTokens = max(0, $limit - $usedTokens);

        return [
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
