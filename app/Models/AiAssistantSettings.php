<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiAssistantSettings extends Model
{
    protected $fillable = [
        'provider',
        'model',
        'api_key',
        'monthly_token_limit',
        'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'monthly_token_limit' => 'integer',
            'is_enabled' => 'boolean',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate(
            ['id' => 1],
            [
                'provider' => 'openai',
                'model' => 'gpt-4o-mini',
                'monthly_token_limit' => 0,
                'is_enabled' => false,
            ],
        );
    }

    public function hasApiKey(): bool
    {
        return filled($this->api_key);
    }
}
