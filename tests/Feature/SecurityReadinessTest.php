<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecurityReadinessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.debug' => false, 'app.key' => 'base64:'.base64_encode(str_repeat('x', 32)),
            'database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.url' => null, 'filesystems.default' => 'local',
            'session.http_only' => true, 'session.same_site' => 'lax']);
        Storage::fake('public');
    }

    public function test_local_readiness_accepts_http_without_weakening_production_checks(): void
    {
        config(['app.env' => 'local', 'app.url' => 'http://127.0.0.1:8000', 'session.secure' => false]);

        $this->artisan('security:check')->assertSuccessful();
        $this->artisan('security:check', ['--production' => true])
            ->expectsOutput('FAIL: Environment produksi')
            ->expectsOutput('FAIL: APP_URL menggunakan HTTPS')
            ->expectsOutput('FAIL: Cookie sesi Secure')->assertFailed();
    }

    public function test_insecure_configuration_is_rejected_without_printing_secrets(): void
    {
        config(['app.env' => 'local', 'app.debug' => true, 'session.http_only' => false, 'session.same_site' => 'none']);

        $this->artisan('security:check')
            ->expectsOutput('FAIL: Debug dinonaktifkan')
            ->expectsOutput('FAIL: Cookie sesi HttpOnly')
            ->expectsOutput('FAIL: Cookie sesi SameSite dibatasi')->assertFailed();
    }

    public function test_sensitive_public_uploads_fail_readiness(): void
    {
        config(['app.env' => 'local']);
        Storage::disk('public')->put('ticket-comments/private.pdf', 'test-only');

        $this->artisan('security:check')
            ->expectsOutput('FAIL: Lampiran sensitif tidak berada di disk public')->assertFailed();
    }
}
