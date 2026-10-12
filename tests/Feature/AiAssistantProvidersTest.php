<?php

namespace Tests\Feature;

use App\Filament\Pages\AiAssistantChat;
use App\Filament\Pages\AiAssistantSettingsPage;
use App\Models\AiAssistantMessage;
use App\Models\AiAssistantSettings;
use App\Models\User;
use App\Services\AiAssistantException;
use App\Services\AiChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AiAssistantProvidersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    public function test_admin_can_keep_separate_encrypted_keys_and_switch_provider_without_exposing_them(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $openai = $this->configure('openai', 'gpt-4o-mini');

        $page = Livewire::actingAs($admin)->test(AiAssistantSettingsPage::class)
            ->set('apiKey', 'unsaved-openai-key')
            ->set('provider', 'deepseek')
            ->assertSet('apiKey', '')
            ->assertSet('apiKeyConfigured', false)
            ->assertSet('model', '')
            ->fill(['model' => 'deepseek-chat', 'apiKey' => 'deepseek-secret', 'monthlyTokenLimit' => '70000', 'isEnabled' => true])
            ->call('save')->assertHasNoErrors()->assertSet('apiKey', '');

        $deepseek = AiAssistantSettings::current('deepseek');
        $this->assertSame('deepseek-secret', $deepseek->api_key);
        $this->assertNotSame('deepseek-secret', $deepseek->getRawOriginal('api_key'));
        $this->assertArrayNotHasKey('api_key', $deepseek->toArray());
        $this->assertSame('openai-secret', $openai->fresh()->api_key);
        $page->call('save')->assertHasNoErrors();
        $this->assertSame('deepseek-secret', $deepseek->fresh()->api_key);

        $page->set('provider', 'anthropic')
            ->assertSet('apiKeyConfigured', false)
            ->fill(['model' => 'claude-sonnet-4-6', 'apiKey' => 'claude-secret', 'monthlyTokenLimit' => '20000', 'isEnabled' => true])
            ->call('save')->assertHasNoErrors()
            ->set('provider', 'openai')->assertSet('model', 'gpt-4o-mini')->assertSet('apiKey', '');
        $this->assertSame('claude-secret', AiAssistantSettings::current('anthropic')->api_key);
    }

    public function test_each_provider_requires_its_own_key_and_rejects_whitespace_model(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->configure('openai', 'gpt-4o-mini');
        Livewire::actingAs($admin)->test(AiAssistantSettingsPage::class)
            ->set('provider', 'anthropic')
            ->fill(['model' => 'claude-sonnet-4-6', 'monthlyTokenLimit' => '100', 'isEnabled' => true, 'apiKey' => '   '])
            ->call('save')->assertHasErrors('apiKey')
            ->set('apiKey', 'claude-secret')->set('model', '   ')
            ->call('save')->assertHasErrors('model');
        $this->assertFalse(AiAssistantSettings::current('anthropic')->is_enabled);
    }

    public function test_admin_can_choose_default_and_disabling_it_falls_back_to_an_enabled_provider(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->configure('openai', 'gpt-4o-mini');
        $this->configure('deepseek', 'deepseek-chat');

        $page = Livewire::actingAs($admin)->test(AiAssistantSettingsPage::class)
            ->set('provider', 'deepseek')->set('isDefault', true)
            ->call('save')->assertHasNoErrors();
        $this->assertSame('deepseek', AiAssistantSettings::defaultProvider());
        $this->assertDatabaseCount('ai_assistant_settings', 2);
        $this->assertSame(1, AiAssistantSettings::query()->where('is_default', true)->count());
        Livewire::actingAs(User::factory()->create())->test(AiAssistantChat::class)->assertSet('provider', 'deepseek');

        Livewire::actingAs($admin)->test(AiAssistantSettingsPage::class)
            ->set('provider', 'deepseek')->set('isEnabled', false)->call('save')->assertHasNoErrors();
        $this->assertSame('openai', AiAssistantSettings::defaultProvider());
        $this->assertFalse(AiAssistantSettings::current('deepseek')->is_default);
    }

    public function test_deepseek_uses_its_endpoint_key_and_max_tokens_and_records_provider_and_model(): void
    {
        $user = User::factory()->create();
        $this->configure('deepseek', 'deepseek-chat');
        $this->fakeChatCompletion('api.deepseek.com/chat/completions');

        Livewire::actingAs($user)->test(AiAssistantChat::class)
            ->assertSet('provider', 'deepseek')
            ->set('question', 'Pertanyaan DeepSeek')->call('send')
            ->assertHasNoErrors()->assertSee('Jawaban uji')->assertSee('deepseek-chat');

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.deepseek.com/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer deepseek-secret')
            && $request['model'] === 'deepseek-chat'
            && $request['max_tokens'] === 1200
            && ! isset($request['max_completion_tokens']));
        $this->assertDatabaseHas('ai_assistant_messages', [
            'user_id' => $user->id, 'role' => 'assistant', 'provider' => 'deepseek', 'model' => 'deepseek-chat',
            'prompt_tokens' => 12, 'completion_tokens' => 6,
        ]);
    }

    public function test_claude_uses_messages_api_and_only_displays_text_blocks_with_total_input_usage(): void
    {
        $user = User::factory()->create();
        $this->configure('anthropic', 'claude-sonnet-4-6');
        Http::fake(['api.anthropic.com/v1/messages' => Http::response([
            'content' => [
                ['type' => 'thinking', 'thinking' => 'Tidak ditampilkan'],
                ['type' => 'text', 'text' => 'Jawaban Claude'],
                ['type' => 'text', 'text' => 'Bagian berikutnya'],
            ],
            'usage' => ['input_tokens' => 10, 'output_tokens' => 8, 'cache_creation_input_tokens' => 3, 'cache_read_input_tokens' => 5],
        ])]);

        $message = app(AiChatService::class)->respond($user, 'Pertanyaan Claude', 'anthropic');
        $this->assertSame("Jawaban Claude\nBagian berikutnya", $message->content);
        $this->assertSame(18, $message->prompt_tokens);
        $this->assertSame(8, $message->completion_tokens);
        $this->assertSame('anthropic', $message->provider);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.anthropic.com/v1/messages'
            && $request->hasHeader('x-api-key', 'anthropic-secret')
            && $request->hasHeader('anthropic-version', '2023-06-01')
            && ! $request->hasHeader('Authorization')
            && $request['model'] === 'claude-sonnet-4-6'
            && $request['max_tokens'] === 1200
            && is_string($request['system'])
            && $request['messages'] === [['role' => 'user', 'content' => 'Pertanyaan Claude']]);
    }

    public function test_switching_provider_separates_user_history_and_never_sends_it_to_another_provider(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $this->configure('openai', 'gpt-4o-mini');
        $this->configure('deepseek', 'deepseek-chat');
        foreach ([[$user, 'openai', 'Riwayat OpenAI'], [$user, 'deepseek', 'Riwayat DeepSeek'], [$otherUser, 'deepseek', 'Rahasia akun lain']] as [$owner, $provider, $content]) {
            AiAssistantMessage::query()->create(['user_id' => $owner->id, 'role' => 'user', 'provider' => $provider, 'content' => $content]);
        }
        $this->fakeChatCompletion('api.deepseek.com/chat/completions');
        Livewire::actingAs($user)->test(AiAssistantChat::class)
            ->assertSee('Riwayat OpenAI')->assertDontSee('Riwayat DeepSeek')
            ->set('provider', 'deepseek')->assertSee('Riwayat DeepSeek')->assertDontSee('Riwayat OpenAI')->assertDontSee('Rahasia akun lain')
            ->set('question', 'Pertanyaan baru')->call('send')->assertHasNoErrors();
        Http::assertSent(fn (Request $request): bool => $request['messages'][1]['content'] === 'Riwayat DeepSeek'
            && ! str_contains(json_encode($request['messages']), 'Riwayat OpenAI')
            && ! str_contains(json_encode($request['messages']), 'Rahasia akun lain'));
    }

    public function test_admin_can_enable_gemini_as_default_with_its_own_encrypted_key(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $openai = $this->configure('openai', 'gpt-4o-mini');
        Livewire::actingAs($admin)->test(AiAssistantSettingsPage::class)
            ->set('provider', 'gemini')->assertSet('model', '')
            ->fill(['model' => 'gemini-test', 'monthlyTokenLimit' => '10000', 'isEnabled' => true, 'isDefault' => true])
            ->call('save')->assertHasErrors('apiKey')
            ->set('apiKey', 'google-secret')->call('save')->assertHasNoErrors()->assertSet('apiKey', '');

        $settings = AiAssistantSettings::current('gemini');
        $this->assertSame('google-secret', $settings->api_key);
        $this->assertNotSame('google-secret', $settings->getRawOriginal('api_key'));
        $this->assertSame('openai-secret', $openai->fresh()->api_key);
        $this->assertSame('gemini', AiAssistantSettings::defaultProvider());
        Livewire::actingAs(User::factory()->create())->test(AiAssistantChat::class)
            ->assertSet('provider', 'gemini')->assertSee('Gemini (Google)')->assertDontSee('google-secret');
    }

    public function test_gemini_uses_google_api_and_private_history_and_counts_thinking_without_displaying_it(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $this->configure('gemini', 'gemini-test', 100);
        foreach ([
            [$user, 'gemini', 'user', 'Pertanyaan lama'],
            [$user, 'gemini', 'assistant', 'Jawaban lama'],
            [$user, 'openai', 'user', 'Riwayat provider lain'],
            [$otherUser, 'gemini', 'user', 'Riwayat akun lain'],
        ] as [$owner, $provider, $role, $content]) {
            AiAssistantMessage::query()->create(['user_id' => $owner->id, 'provider' => $provider, 'role' => $role, 'content' => $content]);
        }
        Http::fake(['generativelanguage.googleapis.com/v1beta/models/gemini-test:generateContent' => Http::response([
            'candidates' => [['content' => ['parts' => [
                ['thought' => true, 'text' => 'Pemikiran tersembunyi'],
                ['text' => 'Jawaban Gemini'],
                ['text' => 'Bagian kedua'],
            ]]]],
            'usageMetadata' => ['promptTokenCount' => 12, 'candidatesTokenCount' => 6, 'thoughtsTokenCount' => 4, 'cachedContentTokenCount' => 5],
        ])]);

        Livewire::actingAs($user)->test(AiAssistantChat::class)
            ->set('question', 'Pertanyaan Gemini')->call('send')->assertHasNoErrors()
            ->assertSee('Jawaban Gemini')->assertDontSee('Pemikiran tersembunyi');
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://generativelanguage.googleapis.com/v1beta/models/gemini-test:generateContent'
            && $request->hasHeader('x-goog-api-key', 'gemini-secret')
            && ! $request->hasHeader('Authorization')
            && is_string($request['systemInstruction']['parts'][0]['text'])
            && $request['generationConfig']['maxOutputTokens'] === 100
            && $request['contents'] === [
                ['role' => 'user', 'parts' => [['text' => 'Pertanyaan lama']]],
                ['role' => 'model', 'parts' => [['text' => 'Jawaban lama']]],
                ['role' => 'user', 'parts' => [['text' => 'Pertanyaan Gemini']]],
            ]);
        $this->assertDatabaseHas('ai_assistant_messages', [
            'user_id' => $user->id, 'role' => 'assistant', 'provider' => 'gemini', 'model' => 'gemini-test',
            'content' => "Jawaban Gemini\nBagian kedua", 'prompt_tokens' => 12, 'completion_tokens' => 10,
        ]);
        $this->assertSame(22, app(AiChatService::class)->monthlyTokenUsage('gemini'));
    }

    public function test_gemini_can_answer_without_thinking_usage_and_cannot_continue_after_reaching_quota(): void
    {
        $user = User::factory()->create();
        $this->configure('gemini', 'gemini-test', 18);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'Jawaban Gemini']]]]],
            'usageMetadata' => ['promptTokenCount' => 12, 'candidatesTokenCount' => 6],
        ])]);
        $service = app(AiChatService::class);
        $this->assertSame(6, $service->respond($user, 'Pertanyaan', 'gemini')->completion_tokens);
        try {
            $service->respond($user, 'Pertanyaan lanjutan', 'gemini');
            $this->fail('Exhausted Gemini quota must reject the next request.');
        } catch (AiAssistantException $exception) {
            $this->assertStringContainsString('sudah tercapai', $exception->getMessage());
        }
        Http::assertSentCount(1);
        $this->assertDatabaseCount('ai_assistant_messages', 2);
    }

    public function test_gemini_blocked_or_incomplete_responses_do_not_save_messages(): void
    {
        $user = User::factory()->create();
        $this->configure('gemini', 'gemini-test');
        $content = ['candidates' => [['content' => ['parts' => [['text' => 'Jawaban']]]]]];
        Http::fake(['generativelanguage.googleapis.com/*' => Http::sequence()
            ->push(['promptFeedback' => ['blockReason' => 'SAFETY']])
            ->push($content)
            ->push(['candidates' => [['content' => ['parts' => [['thought' => true, 'text' => 'Hidden']]]]], 'usageMetadata' => ['promptTokenCount' => 1, 'candidatesTokenCount' => 2]])
            ->push([...$content, 'usageMetadata' => ['promptTokenCount' => 1, 'candidatesTokenCount' => 2, 'thoughtsTokenCount' => -1]])]);
        foreach (range(1, 4) as $attempt) {
            try {
                app(AiChatService::class)->respond($user, 'Pertanyaan', 'gemini');
                $this->fail('Incomplete Gemini response must be rejected.');
            } catch (AiAssistantException $exception) {
                $this->assertStringContainsString('tidak lengkap', $exception->getMessage());
            }
        }
        $this->assertDatabaseCount('ai_assistant_messages', 0);
    }

    public function test_token_limits_and_usage_are_separate_and_old_openai_usage_is_still_counted(): void
    {
        $user = User::factory()->create();
        $this->configure('openai', 'gpt-4o-mini', 10);
        $this->configure('deepseek', 'deepseek-chat', 20);
        AiAssistantMessage::query()->create(['user_id' => $user->id, 'role' => 'assistant', 'content' => 'Jawaban lama', 'prompt_tokens' => 7, 'completion_tokens' => 3]);
        $this->fakeChatCompletion('api.deepseek.com/chat/completions');
        $service = app(AiChatService::class);
        $service->respond($user, 'Pertanyaan DeepSeek', 'deepseek');
        Http::assertSent(fn (Request $request): bool => $request['max_tokens'] === 20);
        $this->assertSame(10, $service->monthlyTokenUsage('openai'));
        $this->assertSame(18, $service->monthlyTokenUsage('deepseek'));
        $this->assertSame(28, $service->monthlyTokenUsage());
        $this->expectException(AiAssistantException::class);
        $this->expectExceptionMessage('sudah tercapai');
        $service->respond($user, 'Pertanyaan OpenAI', 'openai');
    }

    public function test_users_cannot_submit_disabled_or_arbitrary_providers_or_see_api_keys(): void
    {
        $user = User::factory()->create();
        $this->configure('openai', 'gpt-4o-mini');
        $disabled = $this->configure('deepseek', 'deepseek-chat');
        $disabled->update(['is_enabled' => false]);
        Http::fake();
        Livewire::actingAs($user)->test(AiAssistantChat::class)
            ->assertDontSee('openai-secret')->assertDontSee('deepseek-secret')->assertDontSee('deepseek-chat')
            ->set('provider', 'deepseek')->set('question', 'Pertanyaan')->call('send')->assertHasErrors('provider')
            ->set('provider', 'https://example.com')->call('send')->assertHasErrors('provider');
        Http::assertNothingSent();
        $this->assertDatabaseCount('ai_assistant_messages', 0);
    }

    public function test_provider_can_be_disabled_after_page_mount_and_requests_are_still_rejected(): void
    {
        $user = User::factory()->create();
        $settings = $this->configure('deepseek', 'deepseek-chat');
        $page = Livewire::actingAs($user)->test(AiAssistantChat::class);
        $settings->update(['is_enabled' => false]);
        Http::fake();
        $page->set('question', 'Pertanyaan')->call('send')->assertHasErrors('provider')->assertSee('Tanya Aku belum tersedia');
        Http::assertNothingSent();
    }

    #[DataProvider('providerFailures')]
    public function test_provider_failures_do_not_save_messages_or_leak_upstream_errors(string $provider, string $endpoint, int $status): void
    {
        $user = User::factory()->create();
        $this->configure($provider, 'test-model');
        Http::fake([$endpoint => Http::response(['error' => ['message' => 'sensitive-provider-detail']], $status)]);
        try {
            app(AiChatService::class)->respond($user, 'Pertanyaan', $provider);
            $this->fail('Provider failure must reject the answer.');
        } catch (AiAssistantException $exception) {
            $this->assertStringNotContainsString('sensitive-provider-detail', $exception->getMessage());
        }
        $this->assertDatabaseCount('ai_assistant_messages', 0);
    }

    public static function providerFailures(): array
    {
        return [
            'deepseek unauthorized' => ['deepseek', 'api.deepseek.com/chat/completions', 401],
            'deepseek balance' => ['deepseek', 'api.deepseek.com/chat/completions', 402],
            'claude rate limit' => ['anthropic', 'api.anthropic.com/v1/messages', 429],
            'claude unavailable' => ['anthropic', 'api.anthropic.com/v1/messages', 529],
            'gemini forbidden' => ['gemini', 'generativelanguage.googleapis.com/v1beta/models/test-model:generateContent', 403],
            'gemini rate limit' => ['gemini', 'generativelanguage.googleapis.com/v1beta/models/test-model:generateContent', 429],
        ];
    }

    public function test_claude_missing_usage_or_only_thinking_is_rejected_without_saving_an_answer(): void
    {
        $user = User::factory()->create();
        $this->configure('anthropic', 'test-model');
        Http::fake(['api.anthropic.com/v1/messages' => Http::sequence()
            ->push(['content' => [['type' => 'text', 'text' => 'Incomplete answer']]])
            ->push(['content' => [['type' => 'thinking', 'thinking' => 'Hidden']], 'usage' => ['input_tokens' => 1, 'output_tokens' => 2]])]);
        foreach ([1, 2] as $attempt) {
            try {
                app(AiChatService::class)->respond($user, 'Pertanyaan', 'anthropic');
                $this->fail('Incomplete provider response must be rejected.');
            } catch (AiAssistantException $exception) {
                $this->assertStringContainsString('tidak lengkap', $exception->getMessage());
            }
        }
        $this->assertDatabaseCount('ai_assistant_messages', 0);
    }

    public function test_upgrade_preserves_legacy_settings_key_history_and_token_usage(): void
    {
        $migration = require database_path('migrations/2026_10_12_000000_add_multiple_ai_providers.php');
        $migration->down();
        $user = User::factory()->create();
        $encryptedKey = encrypt('legacy-openai-key', false);
        DB::table('ai_assistant_settings')->insert([
            'id' => 1, 'provider' => 'openai', 'model' => 'gpt-4o-mini', 'api_key' => $encryptedKey,
            'monthly_token_limit' => 10000, 'is_enabled' => true,
        ]);
        DB::table('ai_assistant_messages')->insert([
            'user_id' => $user->id, 'role' => 'assistant', 'content' => 'Riwayat lama',
            'prompt_tokens' => 7, 'completion_tokens' => 3, 'created_at' => now(),
        ]);
        $migration->up();
        $this->assertSame('legacy-openai-key', AiAssistantSettings::current()->api_key);
        $this->assertSame($encryptedKey, AiAssistantSettings::current()->getRawOriginal('api_key'));
        $this->assertSame('openai', AiAssistantSettings::defaultProvider());
        $this->assertSame(10, app(AiChatService::class)->monthlyTokenUsage('openai'));
        $this->assertDatabaseHas('ai_assistant_messages', ['content' => 'Riwayat lama', 'provider' => 'openai', 'model' => null]);
    }

    private function configure(string $provider, string $model, int $limit = 50000): AiAssistantSettings
    {
        $settings = AiAssistantSettings::current($provider);
        $settings->update(['model' => $model, 'api_key' => $provider.'-secret', 'monthly_token_limit' => $limit, 'is_enabled' => true]);

        return $settings;
    }

    private function fakeChatCompletion(string $endpoint): void
    {
        Http::fake([$endpoint => Http::response([
            'choices' => [['message' => ['content' => 'Jawaban uji']]],
            'usage' => ['prompt_tokens' => 12, 'completion_tokens' => 6],
        ])]);
    }
}
