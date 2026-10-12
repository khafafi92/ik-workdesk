<?php

namespace App\Services;

use App\Enums\AiProvider;
use App\Models\AiAssistantMessage;
use App\Models\AiAssistantSettings;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class AiChatService
{
    private const MAX_HISTORY_MESSAGES = 20;

    private const MAX_COMPLETION_TOKENS = 1200;

    public function respond(User $user, string $question, ?string $provider = null): AiAssistantMessage
    {
        $provider ??= AiAssistantSettings::defaultProvider() ?? 'openai';
        $aiProvider = AiProvider::tryFrom($provider);
        $settings = AiAssistantSettings::query()->where('provider', $provider)->first();

        if (! $aiProvider || ! $settings || ! $settings->is_enabled || ! $settings->hasApiKey() || blank($settings->model)) {
            throw new AiAssistantException('Layanan Tanya Aku belum disiapkan administrator.');
        }

        if ($settings->monthly_token_limit < 1) {
            throw new AiAssistantException('Administrator belum menetapkan batas token bulanan.');
        }

        $usedTokens = $this->monthlyTokenUsage($provider);
        $remainingTokens = max(0, $settings->monthly_token_limit - $usedTokens);

        if ($remainingTokens === 0) {
            throw new AiAssistantException('Batas token bulanan Tanya Aku sudah tercapai. Hubungi administrator.');
        }

        $history = AiAssistantMessage::query()
            ->where('user_id', $user->getKey())
            ->where('provider', $provider)
            ->whereIn('role', ['user', 'assistant'])
            ->latest('id')
            ->limit(self::MAX_HISTORY_MESSAGES)
            ->get(['role', 'content'])
            ->reverse()
            ->skipWhile(fn (AiAssistantMessage $message): bool => $message->role !== 'user')
            ->map(fn (AiAssistantMessage $message): array => [
                'role' => $message->role,
                'content' => $message->content,
            ])
            ->values()
            ->all();

        $systemPrompt = 'Anda adalah asisten AI umum untuk pengguna IK WorkDesk. Jawab dengan jelas sesuai bahasa pengguna. Anda tidak memiliki akses ke data internal IK WorkDesk kecuali pengguna menuliskannya dalam percakapan. Jangan mengaku telah memeriksa data atau dokumen yang tidak diberikan.';
        $maxTokens = min(self::MAX_COMPLETION_TOKENS, $remainingTokens);
        $messages = [...$history, ['role' => 'user', 'content' => $question]];
        $payload = [
            'model' => $settings->model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $systemPrompt,
                ],
                ...$messages,
            ],
            $aiProvider === AiProvider::OpenAi ? 'max_completion_tokens' : 'max_tokens' => $maxTokens,
        ];

        if ($aiProvider === AiProvider::Anthropic) {
            $payload = [
                'model' => $settings->model,
                'system' => $systemPrompt,
                'messages' => $messages,
                'max_tokens' => $maxTokens,
            ];
        } elseif ($aiProvider === AiProvider::Gemini) {
            $payload = [
                'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
                'contents' => array_map(fn (array $message): array => [
                    'role' => $message['role'] === 'assistant' ? 'model' : 'user',
                    'parts' => [['text' => $message['content']]],
                ], $messages),
                'generationConfig' => ['maxOutputTokens' => $maxTokens],
            ];
        }

        $providerLabel = $aiProvider->label();

        try {
            $request = Http::acceptJson()
                ->withOptions(['allow_redirects' => false])
                ->timeout(60)
                ->connectTimeout(10);

            if ($aiProvider === AiProvider::Anthropic) {
                $request->withHeaders([
                    'x-api-key' => $settings->api_key,
                    'anthropic-version' => '2023-06-01',
                ]);
            } elseif ($aiProvider === AiProvider::Gemini) {
                $request->withHeaders(['x-goog-api-key' => $settings->api_key]);
            } else {
                $request->withToken($settings->api_key);
            }

            $response = $request->post($aiProvider->endpoint($settings->model), $payload);
        } catch (ConnectionException $exception) {
            throw new AiAssistantException("Tidak dapat tersambung ke {$providerLabel}. Coba lagi beberapa saat.", previous: $exception);
        }

        if ($response->status() === 401 || $response->status() === 403) {
            throw new AiAssistantException("{$providerLabel} menolak API key. Minta administrator memeriksa pengaturan.");
        }

        if ($response->status() === 402 || $response->status() === 429) {
            throw new AiAssistantException("{$providerLabel} membatasi permintaan atau saldo API tidak mencukupi. Hubungi administrator.");
        }

        if (! $response->successful()) {
            throw new AiAssistantException("{$providerLabel} belum dapat menjawab. Coba lagi nanti.");
        }

        $answer = $response->json('choices.0.message.content');
        $promptTokens = $response->json('usage.prompt_tokens');
        $completionTokens = $response->json('usage.completion_tokens');

        if ($aiProvider === AiProvider::Anthropic) {
            $content = $response->json('content');
            $answer = is_array($content)
                ? collect($content)
                    ->filter(fn ($block): bool => is_array($block)
                        && ($block['type'] ?? null) === 'text'
                        && is_string($block['text'] ?? null))
                    ->pluck('text')->implode("\n")
                : null;
            $promptTokens = $response->json('usage.input_tokens');
            $completionTokens = $response->json('usage.output_tokens');
        } elseif ($aiProvider === AiProvider::Gemini) {
            $parts = $response->json('candidates.0.content.parts');
            $answer = is_array($parts)
                ? collect($parts)
                    ->filter(fn ($part): bool => is_array($part)
                        && ($part['thought'] ?? false) !== true
                        && is_string($part['text'] ?? null))
                    ->pluck('text')->implode("\n")
                : null;
            $promptTokens = $response->json('usageMetadata.promptTokenCount');
            $completionTokens = $response->json('usageMetadata.candidatesTokenCount');
        }

        if (
            ! is_string($answer)
            || trim($answer) === ''
            || ! is_int($promptTokens) || $promptTokens < 0
            || ! is_int($completionTokens) || $completionTokens < 0
        ) {
            throw new AiAssistantException("Respons {$providerLabel} tidak lengkap. Coba lagi nanti.");
        }

        if ($aiProvider === AiProvider::Anthropic) {
            foreach (['cache_creation_input_tokens', 'cache_read_input_tokens'] as $tokenField) {
                $cachedTokens = $response->json("usage.{$tokenField}", 0) ?? 0;

                if (! is_int($cachedTokens) || $cachedTokens < 0) {
                    throw new AiAssistantException("Respons {$providerLabel} tidak lengkap. Coba lagi nanti.");
                }

                $promptTokens += $cachedTokens;
            }
        } elseif ($aiProvider === AiProvider::Gemini) {
            $thoughtTokens = $response->json('usageMetadata.thoughtsTokenCount', 0) ?? 0;

            if (! is_int($thoughtTokens) || $thoughtTokens < 0) {
                throw new AiAssistantException("Respons {$providerLabel} tidak lengkap. Coba lagi nanti.");
            }

            $completionTokens += $thoughtTokens;
        }

        return DB::transaction(function () use (
            $user,
            $question,
            $answer,
            $promptTokens,
            $completionTokens,
            $settings,
        ): AiAssistantMessage {
            AiAssistantMessage::query()->create([
                'user_id' => $user->getKey(),
                'role' => 'user',
                'content' => $question,
                'provider' => $settings->provider,
                'model' => $settings->model,
            ]);

            return AiAssistantMessage::query()->create([
                'user_id' => $user->getKey(),
                'role' => 'assistant',
                'content' => $answer,
                'prompt_tokens' => (int) $promptTokens,
                'completion_tokens' => (int) $completionTokens,
                'provider' => $settings->provider,
                'model' => $settings->model,
            ]);
        });
    }

    public function monthlyTokenUsage(?string $provider = null): int
    {
        return (int) AiAssistantMessage::query()
            ->where('role', 'assistant')
            ->when($provider !== null, fn ($query) => $query->where('provider', $provider))
            ->where('created_at', '>=', now()->startOfMonth())
            ->selectRaw('COALESCE(SUM(COALESCE(prompt_tokens, 0) + COALESCE(completion_tokens, 0)), 0) as total')
            ->value('total');
    }
}
