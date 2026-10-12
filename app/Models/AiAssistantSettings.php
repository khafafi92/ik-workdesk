<?php

namespace App\Models;

use App\Enums\AiProvider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AiAssistantSettings extends Model
{
    protected $hidden = ['api_key'];

    protected $fillable = [
        'provider',
        'model',
        'api_key',
        'monthly_token_limit',
        'is_enabled',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'monthly_token_limit' => 'integer',
            'is_enabled' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    public static function current(string $provider = 'openai'): self
    {
        $aiProvider = AiProvider::from($provider);

        return static::query()->firstOrCreate(
            ['provider' => $aiProvider->value],
            [
                'model' => $aiProvider->defaultModel(),
                'monthly_token_limit' => 0,
                'is_enabled' => false,
                'is_default' => false,
            ],
        );
    }

    public function hasApiKey(): bool
    {
        return filled($this->api_key);
    }

    public function scopeAvailable(Builder $query): void
    {
        $query->whereIn('provider', array_keys(AiProvider::options()))
            ->where('is_enabled', true)
            ->where('monthly_token_limit', '>', 0)
            ->whereNotNull('api_key')
            ->where('api_key', '!=', '')
            ->where('model', '!=', '');
    }

    public static function defaultProvider(): ?string
    {
        return static::query()->available()
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->value('provider');
    }
}
