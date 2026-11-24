<?php

namespace Tests\Feature;

use App\Filament\Pages\LanguageSettings;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkdeskLocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_uses_the_default_locale_and_cannot_change_an_account(): void
    {
        $this->post(route('workdesk.language'), [
            'locale' => 'id',
        ])->assertRedirect(route('login'));
        $this->get(LanguageSettings::getUrl())->assertRedirect(route('login'));

        $this->withCookie('workdesk_locale', 'id')->get('/login')
            ->assertOk()
            ->assertSee('<html lang="en"', false)
            ->assertSee('Sign in to IK WorkDesk')
            ->assertDontSee(route('workdesk.language'), false);
    }

    public function test_regular_user_can_save_language_from_system_settings_and_keep_it_after_signing_in_again(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $url = LanguageSettings::getUrl();

        $this->actingAs($user)->get($url)
            ->assertOk()
            ->assertSee('data-group-label="Pengaturan Sistem"', false)
            ->assertSee('System Settings')
            ->assertSee('Choose a language to save it to your account.');
        $this->get('/panel/module-access-settings')->assertForbidden();

        $this->post(route('workdesk.language'), [
            'locale' => 'id',
            'user_id' => $other->id,
        ])->assertStatus(303)->assertRedirect($url);
        $this->assertSame('id', $user->fresh()->preferred_locale);
        $this->assertNull($other->fresh()->preferred_locale);

        $this->get($url)->assertOk()->assertSee('Pilihan bahasa Anda tersimpan.');
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->post(route('login'), ['email' => $other->email, 'password' => 'password'])
            ->assertRedirect('/panel');
        $this->get($url)->assertOk()->assertSee('Choose a language to save it to your account.');

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/panel');
        $response = $this->get($url)->assertOk()
            ->assertSee('Pilih bahasa untuk menyimpannya ke akun Anda.');
        $this->assertMatchesRegularExpression('/<html\s+lang="id"/', $response->getContent());
    }

    public function test_panel_form_respects_the_selected_language(): void
    {
        Department::query()->create(['code' => 'IT', 'name' => 'IT']);
        $admin = User::factory()->create(['is_admin' => true]);
        $role = Role::query()->create(['code' => 'system-admin', 'name' => 'Administrator', 'is_active' => true]);
        $admin->roles()->attach($role);
        $this->actingAs($admin);

        $this->post(route('workdesk.language'), ['locale' => 'id'])
            ->assertRedirect(LanguageSettings::getUrl());
        $this->get('/panel/it-devices/create')
            ->assertOk()
            ->assertSee('lang="id"', false)
            ->assertSee('Tambah Perangkat')
            ->assertSee('Nama Perangkat')
            ->assertDontSee(route('workdesk.language'), false);

        $this->post(route('workdesk.language'), ['locale' => 'en'])
            ->assertRedirect(LanguageSettings::getUrl());
        $this->get('/panel/it-devices/create')
            ->assertOk()
            ->assertSee('lang="en"', false)
            ->assertSee('Add Device')
            ->assertSee('Device Name');
    }

    public function test_language_settings_rejects_unsupported_locales(): void
    {
        $user = User::factory()->create();
        $url = LanguageSettings::getUrl();

        $this->actingAs($user)->from($url)->post(route('workdesk.language'), ['locale' => 'fr'])
            ->assertRedirect($url)
            ->assertSessionHasErrors('locale');
        $this->assertNull($user->fresh()->preferred_locale);
    }
}
