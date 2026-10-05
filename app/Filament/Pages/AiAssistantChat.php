<?php

namespace App\Filament\Pages;

use App\Models\AiAssistantMessage;
use App\Models\AiAssistantSettings;
use App\Services\AiAssistantException;
use App\Services\OpenAiChatService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Report;
use Illuminate\Validation\ValidationException;
use Throwable;

class AiAssistantChat extends Page
{
    protected static ?string $slug = 'ask-me';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleBottomCenterText;

    protected string $view = 'filament.pages.ai-assistant-chat';

    public string $question = '';

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
        $validated = $this->validate([
            'question' => ['required', 'string', 'max:4000'],
        ]);

        try {
            app(OpenAiChatService::class)->respond(
                auth()->user(),
                trim($validated['question']),
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
        $settings = AiAssistantSettings::current();

        return [
            'messages' => AiAssistantMessage::query()
                ->where('user_id', auth()->id())
                ->latest('id')
                ->limit(40)
                ->get()
                ->reverse()
                ->values(),
            'assistantAvailable' => $settings->is_enabled
                && $settings->hasApiKey()
                && $settings->monthly_token_limit > 0,
        ];
    }
}
