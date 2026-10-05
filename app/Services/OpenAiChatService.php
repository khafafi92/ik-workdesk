<?php

namespace App\Services;

use App\Models\AiAssistantMessage;
use App\Models\AiAssistantSettings;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class OpenAiChatService
{
    private const MAX_HISTORY_MESSAGES = 20;

    private const MAX_COMPLETION_TOKENS = 1200;

    public function respond(User $user, string $question): AiAssistantMessage
    {
        $settings = AiAssistantSettings::current();

        if (! $settings->is_enabled || ! $settings->hasApiKey()) {
            throw new AiAssistantException('Layanan Tanya Aku belum disiapkan administrator.');
        }

        if ($settings->monthly_token_limit < 1) {
            throw new AiAssistantException('Administrator belum menetapkan batas token bulanan.');
        }

        $usedTokens = $this->monthlyTokenUsage();
        $remainingTokens = max(0, $settings->monthly_token_limit - $usedTokens);

        if ($remainingTokens === 0) {
            throw new AiAssistantException('Batas token bulanan Tanya Aku sudah tercapai. Hubungi administrator.');
        }

        $history = AiAssistantMessage::query()
            ->where('user_id', $user->getKey())
            ->latest('id')
            ->limit(self::MAX_HISTORY_MESSAGES)
            ->get(['role', 'content'])
            ->reverse()
            ->map(fn (AiAssistantMessage $message): array => [
                'role' => $message->role,
                'content' => $message->content,
            ])
            ->values()
            ->all();

        $payload = [
            'model' => $settings->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Anda adalah asisten AI umum untuk pengguna IK WorkDesk. Jawab dengan jelas sesuai bahasa pengguna. Anda tidak memiliki akses ke data internal IK WorkDesk kecuali pengguna menuliskannya dalam percakapan. Jangan mengaku telah memeriksa data atau dokumen yang tidak diberikan.',
                ],
                ...$history,
                ['role' => 'user', 'content' => $question],
            ],
            'max_completion_tokens' => min(self::MAX_COMPLETION_TOKENS, $remainingTokens),
        ];

        try {
            $response = Http::acceptJson()
                ->withToken($settings->api_key)
                ->timeout(60)
                ->post('https://api.openai.com/v1/chat/completions', $payload);
        } catch (ConnectionException $exception) {
            throw new AiAssistantException('Tidak dapat tersambung ke OpenAI. Coba lagi beberapa saat.', previous: $exception);
        }

        if ($response->status() === 401 || $response->status() === 403) {
            throw new AiAssistantException('OpenAI menolak API key. Minta administrator memeriksa pengaturan.');
        }

        if ($response->status() === 429) {
            throw new AiAssistantException('OpenAI membatasi permintaan atau saldo API tidak mencukupi. Hubungi administrator.');
        }

        if (! $response->successful()) {
            throw new AiAssistantException('OpenAI belum dapat menjawab. Coba lagi nanti.');
        }

        $answer = $response->json('choices.0.message.content');
        $promptTokens = $response->json('usage.prompt_tokens');
        $completionTokens = $response->json('usage.completion_tokens');

        if (
            ! is_string($answer)
            || trim($answer) === ''
            || ! is_numeric($promptTokens)
            || ! is_numeric($completionTokens)
        ) {
            throw new AiAssistantException('Respons OpenAI tidak lengkap. Coba lagi nanti.');
        }

        return DB::transaction(function () use (
            $user,
            $question,
            $answer,
            $promptTokens,
            $completionTokens,
        ): AiAssistantMessage {
            AiAssistantMessage::query()->create([
                'user_id' => $user->getKey(),
                'role' => 'user',
                'content' => $question,
            ]);

            return AiAssistantMessage::query()->create([
                'user_id' => $user->getKey(),
                'role' => 'assistant',
                'content' => $answer,
                'prompt_tokens' => (int) $promptTokens,
                'completion_tokens' => (int) $completionTokens,
            ]);
        });
    }

    public function monthlyTokenUsage(): int
    {
        return (int) AiAssistantMessage::query()
            ->where('role', 'assistant')
            ->where('created_at', '>=', now()->startOfMonth())
            ->selectRaw('COALESCE(SUM(COALESCE(prompt_tokens, 0) + COALESCE(completion_tokens, 0)), 0) as total')
            ->value('total');
    }
}
