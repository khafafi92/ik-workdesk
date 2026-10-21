<?php

namespace Tests\Feature;

use App\Services\MailerResolver;
use Filament\Facades\Filament;
use Filament\FontProviders\LocalFontProvider;
use Tests\TestCase;

class PerformanceConfigurationTest extends TestCase
{
    public function test_panel_uses_the_bundled_inter_font_without_an_external_font_stylesheet(): void
    {
        $panel = Filament::getPanel('admin');
        $this->assertSame('Inter Variable', $panel->getFontFamily());
        $this->assertSame(LocalFontProvider::class, $panel->getFontProvider());
        $this->assertStringNotContainsString('fonts.bunny.net', $panel->getFontHtml()->toHtml());
        $this->assertFileExists(public_path('fonts/filament/filament/inter/index.css'));
    }

    public function test_mailer_sender_uses_configuration_instead_of_requiring_uncached_environment_values(): void
    {
        config(['mail.from' => ['address' => 'fallback@example.test', 'name' => 'Fallback'],
            'mail.mailers.kpmog.from' => ['address' => 'kpmog@example.test', 'name' => 'KPMOG pengujian'],
            'mail.mailers.apca.from' => ['address' => 'apca@example.test', 'name' => 'APCA pengujian']]);

        $this->assertSame(['address' => 'kpmog@example.test', 'name' => 'KPMOG pengujian'], MailerResolver::fromAddress('kpmog'));
        $this->assertSame(['address' => 'apca@example.test', 'name' => 'APCA pengujian'], MailerResolver::fromAddress('apca'));
        $this->assertSame(['address' => 'fallback@example.test', 'name' => 'Fallback'], MailerResolver::fromAddress('array'));
    }
}
