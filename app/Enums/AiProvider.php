<?php

namespace App\Enums;

enum AiProvider: string
{
    case OpenAi = 'openai';
    case DeepSeek = 'deepseek';
    case Anthropic = 'anthropic';
    case Gemini = 'gemini';

    public function label(): string
    {
        return match ($this) {
            self::OpenAi => 'OpenAI',
            self::DeepSeek => 'DeepSeek',
            self::Anthropic => 'Claude (Anthropic)',
            self::Gemini => 'Gemini (Google)',
        };
    }

    public function defaultModel(): string
    {
        return match ($this) {
            self::OpenAi => 'gpt-4o-mini',
            self::DeepSeek, self::Anthropic, self::Gemini => '',
        };
    }

    public function endpoint(string $model = ''): string
    {
        return match ($this) {
            self::OpenAi => 'https://api.openai.com/v1/chat/completions',
            self::DeepSeek => 'https://api.deepseek.com/chat/completions',
            self::Anthropic => 'https://api.anthropic.com/v1/messages',
            self::Gemini => 'https://generativelanguage.googleapis.com/v1beta/models/'.rawurlencode($model).':generateContent',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $provider): array => [$provider->value => $provider->label()])
            ->all();
    }
}
