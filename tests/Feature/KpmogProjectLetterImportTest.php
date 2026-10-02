<?php

namespace Tests\Feature;

use App\Exports\KpmogProjectLettersExport;
use App\Models\OutgoingLetter;
use App\Models\PermitCompany;
use App\Models\User;
use App\Services\KpmogProjectLetterImportService;
use App\Services\KpmogProjectLetterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class KpmogProjectLetterImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_creates_legacy_project_letters_and_continues_the_yearly_sequence(): void
    {
        PermitCompany::query()->firstOrCreate(['code' => 'KPMOG'], ['name' => 'KPMOG', 'is_active' => true]);
        $actor = User::factory()->create();
        Storage::fake('local');
        Storage::disk('local')->put('kpmog-project-letter-imports/register.csv', implode("\n", [
            'NO,KPMOG,DEPT,BLN,THN,TANGGAL SURAT,PIN,PIC,TUJUAN SURAT,PERIHAL SURAT,NOMOR SURAT TERBIT,STATUS SURAT',
            '001,KPMOG,PM,I,2026,2-Jan-26,K037,Aisya,PT Pelindo Multi Terminal,Reminder Status Dokumen,001/KPMOG-PM/I/2026,Archived',
            '002,KPMOG,BD,II,2026,19-Feb-26,K082,Ruzia,INPEX Masela Ltd.,Power of Attorney PQ Evaluation Meeting,002/KPMOG-BD/II/2026,Draft',
        ]));

        $result = app(KpmogProjectLetterImportService::class)->import('kpmog-project-letter-imports/register.csv', $actor);

        $this->assertSame(2, $result['created']);
        $this->assertSame(0, $result['skippedExisting']);
        $this->assertDatabaseHas('outgoing_letters', [
            'document_number' => '001/KPMOG-PM/I/2026',
            'status' => 'issued',
            'is_legacy_number' => true,
        ]);
        $this->assertDatabaseHas('outgoing_letters', [
            'document_number' => '002/KPMOG-BD/II/2026',
            'status' => 'draft',
        ]);
        $this->assertSame('003', app(KpmogProjectLetterService::class)->nextNumber('2026-03-01'));
    }

    public function test_import_reports_existing_and_duplicate_numbers_without_creating_them_twice(): void
    {
        PermitCompany::query()->firstOrCreate(['code' => 'KPMOG'], ['name' => 'KPMOG', 'is_active' => true]);
        $actor = User::factory()->create();
        Storage::fake('local');
        Storage::disk('local')->put('kpmog-project-letter-imports/duplicates.csv', implode("\n", [
            'NO,KPMOG,DEPT,BLN,THN,TANGGAL SURAT,PIN,PIC,TUJUAN SURAT,PERIHAL SURAT,NOMOR SURAT TERBIT,STATUS SURAT',
            '001,KPMOG,PM,I,2026,2-Jan-26,K037,Aisya,PT Pelindo Multi Terminal,Reminder Status Dokumen,001/KPMOG-PM/I/2026,Archived',
            '001,KPMOG,PM,I,2026,2-Jan-26,K037,Aisya,PT Pelindo Multi Terminal,Reminder Status Dokumen,001/KPMOG-PM/I/2026,Archived',
        ]));

        $first = app(KpmogProjectLetterImportService::class)->import('kpmog-project-letter-imports/duplicates.csv', $actor);
        $second = app(KpmogProjectLetterImportService::class)->import('kpmog-project-letter-imports/duplicates.csv', $actor);

        $this->assertSame(1, $first['created']);
        $this->assertSame(1, $first['skippedDuplicate']);
        $this->assertSame(0, $second['created']);
        $this->assertSame(1, $second['skippedExisting']);
        $this->assertSame(1, $second['skippedDuplicate']);
        $this->assertDatabaseCount('outgoing_letters', 1);
    }

    public function test_project_letter_export_can_be_downloaded_as_excel(): void
    {
        Excel::fake();

        Excel::download(new KpmogProjectLettersExport(OutgoingLetter::query()), 'register-surat-kpmog-project-bd.xlsx');

        Excel::assertDownloaded('register-surat-kpmog-project-bd.xlsx', fn (KpmogProjectLettersExport $export): bool => $export instanceof KpmogProjectLettersExport);
    }
}
