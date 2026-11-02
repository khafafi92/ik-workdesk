<?php

namespace Tests\Unit\Scm;

use App\Services\Scm\ProjectCode;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProjectCodeTest extends TestCase
{
    public static function documentCodes(): array
    {
        return [
            ['UBAD', 2023, 'SPC', '001', 'UBAD/23/SPC/001'],
            ['SULU', 2024, 'SC', 2, 'SULU/24/SC/002'],
            ['BMTH-LNG', 2025, 'SPC', 1, 'BMTH-LNG/25/SPC/001'],
            ['PGSF', 2025, 'SPC', 2, 'PGSF/25/SPC/002'],
            ['BMTH-BBM', 2025, 'SPC', 3, 'BMTH-BBM/25/SPC/003'],
            ['KTJG', 2025, 'SPC', 4, 'KTJG/25/SPC/004'],
            ['test', 2026, 'SP', 9, 'TEST/26/SP/009'],
        ];
    }

    #[DataProvider('documentCodes')]
    public function test_formats_document_examples_and_parses_them_for_editing(string $prefix, int $year, string $work, int|string $number, string $expected): void
    {
        $form = ['format' => 'standard', 'project_prefix' => $prefix, 'awarded_year' => $year, 'work_code' => $work, 'running_number' => $number];
        $this->assertSame($expected, ProjectCode::make($form));
        $parsed = ProjectCode::parse($expected);
        $this->assertSame($expected, ProjectCode::make($parsed));
        $this->assertSame($year, $parsed['awarded_year']);
    }

    public static function invalidComponents(): array
    {
        return [[['project_prefix' => 'ABC']], [['project_prefix' => 'ABCDE']], [['project_prefix' => 'ABCD/26']],
            [['awarded_year' => 26]], [['awarded_year' => 2100]], [['work_code' => 'PR']], [['running_number' => '000']],
            [['running_number' => 1000]], [['running_number' => '1.5']], [['format' => 'unknown']]];
    }

    #[DataProvider('invalidComponents')]
    public function test_invalid_components_do_not_produce_a_code(array $extra): void
    {
        $form = array_replace(['format' => 'standard', 'project_prefix' => 'ABCD', 'awarded_year' => 2026, 'work_code' => 'SPC', 'running_number' => 1], $extra);
        $this->assertNull(ProjectCode::preview($form));
        $this->expectException(ValidationException::class);
        ProjectCode::make($form);
    }

    public function test_legacy_ltro_code_is_preserved_and_incomplete_form_has_no_preview(): void
    {
        $this->assertSame('COMP-19E001', ProjectCode::make(['format' => 'legacy', 'code' => ' comp-19e001 ']));
        $this->assertNull(ProjectCode::parse('COMP-19E001'));
        $this->assertNull(ProjectCode::preview(ProjectCode::defaults()));
    }
}
