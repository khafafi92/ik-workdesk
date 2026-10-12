<?php

namespace App\Filament\Pages;

use App\Enums\AiProvider;
use App\Models\AiAssistantMessage;
use App\Models\AiAssistantSettings;
use App\Services\AiAssistantException;
use App\Services\AiChatService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Report;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class AiAssistantChat extends Page
{
    protected static ?string $slug = 'ask-me';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleBottomCenterText;

    protected string $view = 'filament.pages.ai-assistant-chat';

    public string $question = '';

    public string $provider = '';

    public function mount(): void
    {
        $this->provider = AiAssistantSettings::defaultProvider() ?? '';
    }

    public function updatedProvider(): void
    {
        $this->resetValidation();
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

    public static function getNavigationLabel(): string
    {
        return 'Tanya Aku';
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    public function getTitle(): string|Htmlable
    {
        return 'Tanya Aku';
    }

    public function getHeading(): string|Htmlable
    {
        return 'Tanya Aku';
    }

    public function send(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->question = trim($this->question);

        $validated = $this->validate([
            'question' => ['required', 'string', 'max:4000'],
            'provider' => ['required', Rule::in(AiAssistantSettings::query()->available()->pluck('provider')->all())],
        ]);

        try {
            app(AiChatService::class)->respond(
                auth()->user(),
                trim($validated['question']),
                $validated['provider'],
            );
        } catch (AiAssistantException $exception) {
            throw ValidationException::withMessages([
                'question' => $exception->getMessage(),
            ]);
        } catch (Throwable $exception) {
            Report::report($exception);

            Notification::make()
                ->title('Jawaban belum tersedia')
                ->body('Terjadi gangguan saat meminta jawaban. Coba lagi nanti atau hubungi administrator.')
                ->danger()
                ->send();

            return;
        }

        $this->reset('question');
    }

    protected function getViewData(): array
    {
        $availableModels = AiAssistantSettings::query()->available()
            ->orderBy('id')
            ->get(['provider', 'model']);

        return [
            'availableModels' => $availableModels,
            'providerLabel' => AiProvider::tryFrom($this->provider)?->label() ?? 'penyedia AI',
            'messages' => AiAssistantMessage::query()
                ->where('user_id', auth()->id())
                ->where('provider', $this->provider)
                ->latest('id')
                ->limit(40)
                ->get()
                ->reverse()
                ->values(),
            'assistantAvailable' => $availableModels->isNotEmpty(),
        ];
    }
}
