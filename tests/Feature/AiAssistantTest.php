<?php

namespace Tests\Feature;

use App\Filament\Pages\AiAssistantChat;
use App\Filament\Pages\AiAssistantSettingsPage;
use App\Models\AiAssistantMessage;
use App\Models\AiAssistantSettings;
use App\Models\User;
use App\Services\AiAssistantException;
use App\Services\OpenAiChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class AiAssistantTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_configure_openai_and_key_is_encrypted_at_rest(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Livewire::actingAs($admin)
            ->test(AiAssistantSettingsPage::class)
            ->fill([
                'model' => 'gpt-4o-mini',
                'apiKey' => 'test-openai-secret',
                'monthlyTokenLimit' => '50000',
                'isEnabled' => true,
            ])
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('apiKey', '')
            ->assertSet('apiKeyConfigured', true);

        $settings = AiAssistantSettings::current();

        $this->assertTrue($settings->is_enabled);
        $this->assertSame(50000, $settings->monthly_token_limit);
        $this->assertSame('test-openai-secret', $settings->api_key);
        $this->assertNotSame(
            'test-openai-secret',
            $settings->getRawOriginal('api_key'),
        );
        $this->assertSame(
            'test-openai-secret',
            Crypt::decryptString($settings->getRawOriginal('api_key')),
        );
    }

    public function test_non_administrators_cannot_open_provider_master(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/panel/ai-assistant-settings')
            ->assertForbidden();
    }

    public function test_administrator_can_open_provider_master_and_monthly_usage(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get('/panel/ai-assistant-settings')
            ->assertOk()
            ->assertSee('Penyedia AI')
            ->assertSee('Batas token internal per bulan')
            ->assertSee('Pemakaian token');
    }

    public function test_authenticated_user_can_open_private_chat_before_it_is_configured(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/panel/ask-me')
            ->assertOk()
            ->assertSee('Tanya Aku belum tersedia');
    }

    public function test_administrator_must_configure_api_key_before_enabling_assistant(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        Livewire::actingAs($admin)
            ->test(AiAssistantSettingsPage::class)
            ->fill([
                'model' => 'gpt-4o-mini',
                'apiKey' => '',
                'monthlyTokenLimit' => '50000',
                'isEnabled' => true,
            ])
            ->call('save')
            ->assertHasErrors('apiKey');

        $this->assertDatabaseCount('ai_assistant_settings', 1);
        $this->assertFalse(AiAssistantSettings::current()->is_enabled);
        $this->assertFalse(AiAssistantSettings::current()->hasApiKey());
    }

    public function test_chat_calls_openai_saves_private_history_and_records_actual_token_usage(): void
    {
        $user = User::factory()->create();
        $this->configureAssistant();

        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'Jawaban dari OpenAI']],
                ],
                'usage' => [
                    'prompt_tokens' => 18,
                    'completion_tokens' => 9,
                ],
            ]),
        ]);

        Livewire::actingAs($user)
            ->test(AiAssistantChat::class)
            ->set('question', 'Apa fungsi laporan ini?')
            ->call('send')
            ->assertHasNoErrors()
            ->assertSet('question', '')
            ->assertSee('Jawaban dari OpenAI');

        $this->assertDatabaseHas('ai_assistant_messages', [
            'user_id' => $user->id,
            'role' => 'user',
            'content' => 'Apa fungsi laporan ini?',
        ]);
        $this->assertDatabaseHas('ai_assistant_messages', [
            'user_id' => $user->id,
            'role' => 'assistant',
            'content' => 'Jawaban dari OpenAI',
            'prompt_tokens' => 18,
            'completion_tokens' => 9,
        ]);
        $this->assertSame(27, app(OpenAiChatService::class)->monthlyTokenUsage());

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.openai.com/v1/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer test-openai-secret')
            && $request['model'] === 'gpt-4o-mini'
            && $request['messages'][1]['role'] === 'user'
            && $request['messages'][1]['content'] === 'Apa fungsi laporan ini?');
    }

    public function test_user_history_is_not_included_in_another_users_request(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();
        $this->configureAssistant();

        AiAssistantMessage::query()->create([
            'user_id' => $firstUser->id,
            'role' => 'user',
            'content' => 'Rahasia pengguna pertama',
        ]);

        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'Jawaban kedua']],
                ],
                'usage' => [
                    'prompt_tokens' => 4,
                    'completion_tokens' => 3,
                ],
            ]),
        ]);

        app(OpenAiChatService::class)->respond($secondUser, 'Pertanyaan kedua');

        Http::assertSent(fn (Request $request): bool => collect($request['messages'])
            ->every(fn (array $message): bool => ! str_contains($message['content'], 'Rahasia pengguna pertama')));
    }

    public function test_assistant_refuses_to_call_openai_when_disabled_or_monthly_limit_is_reached(): void
    {
        $user = User::factory()->create();
        $settings = $this->configureAssistant();
        Http::fake();

        $settings->update(['is_enabled' => false]);

        try {
            app(OpenAiChatService::class)->respond($user, 'Pertanyaan');
            $this->fail('Expected disabled assistant to reject the request.');
        } catch (AiAssistantException $exception) {
            $this->assertStringContainsString('belum disiapkan', $exception->getMessage());
        }

        $settings->update(['is_enabled' => true, 'monthly_token_limit' => 10]);
        AiAssistantMessage::query()->create([
            'user_id' => $user->id,
            'role' => 'assistant',
            'content' => 'Jawaban lama',
            'prompt_tokens' => 7,
            'completion_tokens' => 3,
        ]);

        try {
            app(OpenAiChatService::class)->respond($user, 'Pertanyaan baru');
            $this->fail('Expected exhausted monthly token budget to reject the request.');
        } catch (AiAssistantException $exception) {
            $this->assertStringContainsString('sudah tercapai', $exception->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_openai_failure_is_reported_without_saving_a_false_answer(): void
    {
        $user = User::factory()->create();
        $this->configureAssistant();

        Http::fake([
            'api.openai.com/v1/chat/completions' => Http::response([
                'error' => ['message' => 'invalid api key'],
            ], 401),
        ]);

        try {
            app(OpenAiChatService::class)->respond($user, 'Pertanyaan');
            $this->fail('Expected invalid API key to be reported.');
        } catch (AiAssistantException $exception) {
            $this->assertStringContainsString('menolak API key', $exception->getMessage());
        }

        $this->assertDatabaseCount('ai_assistant_messages', 0);
    }

    private function configureAssistant(): AiAssistantSettings
    {
        $settings = AiAssistantSettings::current();
        $settings->api_key = 'test-openai-secret';
        $settings->model = 'gpt-4o-mini';
        $settings->monthly_token_limit = 50000;
        $settings->is_enabled = true;
        $settings->save();

        return $settings;
    }
}
