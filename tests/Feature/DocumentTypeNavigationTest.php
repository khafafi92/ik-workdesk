<?php

namespace Tests\Feature;

use App\Filament\Resources\DocumentTypes\DocumentTypeResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentTypeNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_find_document_types_in_the_surat_navigation(): void
    {
        $administrator = User::factory()->create(['is_admin' => true]);

        $this->actingAs($administrator);

        $this->assertTrue(DocumentTypeResource::shouldRegisterNavigation());
        $this->assertTrue(DocumentTypeResource::canViewAny());
        $this->get('/panel')
            ->assertOk()
            ->assertSeeText('Jenis Surat')
            ->assertSee(DocumentTypeResource::getUrl());
    }
}
