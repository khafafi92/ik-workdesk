<?php

namespace App\Filament\Resources\Tickets\Schemas;

use App\Models\Ticket;
use App\Services\ManpowerRequestDetails;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;

class ManpowerRequestFields
{
    public static function isVisible(Get $get, ?Ticket $record): bool
    {
        return ManpowerRequestDetails::usesForm($get('handler_department_id') ?? $record?->handler_department_id,
            $get('ticket_category_id') ?? $record?->ticket_category_id, $record);
    }

    public static function section(): Section
    {
        return Section::make('Kebutuhan manpower')
            ->extraAttributes(['class' => 'manpower-request-form'])
            ->description('Jelaskan kebutuhan tenaga kerja, lalu tambahkan posisi beserta jumlah dan persyaratannya.')
            ->visible(fn (Get $get, ?Ticket $record): bool => self::isVisible($get, $record))
            ->columns(['default' => 1, 'md' => 2])->columnSpanFull()
            ->schema([
                Textarea::make('manpower_details.purpose')->label('Kebutuhan untuk apa?')
                    ->helperText('Jelaskan pekerjaan, proyek, atau kebutuhan operasional yang akan didukung.')
                    ->validationMessages(['required' => 'Jelaskan tujuan kebutuhan tenaga kerja.'])
                    ->required()->maxLength(3000)->rows(3)->columnSpanFull(),
                TextInput::make('manpower_details.work_location')->label('Lokasi / proyek penempatan')
                    ->extraInputAttributes(['style' => 'min-height:44px'])
                    ->validationMessages(['required' => 'Isi lokasi atau proyek penempatan.'])
                    ->required()->maxLength(255),
                DatePicker::make('manpower_details.needed_at')->label('Tanggal tenaga kerja dibutuhkan')
                    ->extraInputAttributes(['style' => 'min-height:44px'])
                    ->validationMessages(['required' => 'Pilih tanggal tenaga kerja dibutuhkan.'])
                    ->helperText('Tanggal tenaga kerja diharapkan tersedia di lokasi penempatan.')->required(),
                Select::make('manpower_details.reason')->label('Alasan kebutuhan (opsional)')
                    ->extraInputAttributes(['style' => 'min-height:44px'])
                    ->options(ManpowerRequestDetails::REASONS),
                TextEntry::make('manpower_total')->label('Total kebutuhan')->state(function (Get $get): string {
                    $total = collect($get('manpower_details.positions') ?? [])->sum(fn (array $position): int => filter_var($position['quantity'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0);

                    return $total.' orang';
                }),
                Repeater::make('manpower_details.positions')->label('Posisi yang dibutuhkan')
                    ->schema([
                        TextInput::make('title')->label('Posisi / jabatan')->required()->maxLength(150)->live(onBlur: true)
                            ->extraInputAttributes(['style' => 'min-height:44px'])
                            ->validationMessages(['required' => 'Isi posisi atau jabatan yang dibutuhkan.']),
                        TextInput::make('quantity')->label('Jumlah orang')->numeric()->rules(['integer'])
                            ->minValue(1)->maxValue(10000)->required()->live(onBlur: true)
                            ->extraInputAttributes(['style' => 'min-height:44px'])
                            ->validationMessages(['required' => 'Isi jumlah orang yang dibutuhkan.', 'integer' => 'Jumlah orang harus berupa bilangan bulat.', 'min' => 'Jumlah kebutuhan minimal 1 orang.']),
                        Textarea::make('specifications')->label('Spesifikasi pekerjaan')
                            ->helperText('Uraikan tugas utama, keahlian kerja, dan alat atau bidang pekerjaan yang akan ditangani.')
                            ->validationMessages(['required' => 'Jelaskan spesifikasi pekerjaan untuk posisi ini.'])
                            ->required()->maxLength(3000)->rows(3),
                        Textarea::make('requirements')->label('Persyaratan kandidat')
                            ->helperText('Tuliskan pendidikan, pengalaman, serta sertifikasi atau keterampilan khusus yang diperlukan.')
                            ->validationMessages(['required' => 'Jelaskan persyaratan kandidat untuk posisi ini.'])
                            ->required()->maxLength(3000)->rows(3),
                        Select::make('employment_type')->label('Jenis kebutuhan tenaga kerja (opsional)')
                            ->extraInputAttributes(['style' => 'min-height:44px'])
                            ->options(ManpowerRequestDetails::EMPLOYMENT_TYPES)->columnSpanFull(),
                    ])
                    ->columns(['default' => 1, 'md' => 2])->defaultItems(1)->minItems(1)->maxItems(50)
                    ->required()->reorderable(false)->addActionLabel('Tambah posisi')
                    ->addAction(fn (Action $action) => $action->extraAttributes(['style' => 'min-height:44px!important']))
                    ->deleteAction(fn (Action $action) => $action->label('Hapus posisi')->tooltip('Hapus posisi')->extraAttributes(['style' => 'min-height:44px;min-width:44px']))
                    ->itemLabel(fn (array $state): string => filled($state['title'] ?? null) ? $state['title'] : 'Posisi baru')
                    ->columnSpanFull(),
                Textarea::make('manpower_details.notes')->label('Catatan tambahan (opsional)')
                    ->helperText('Bila perlu, jelaskan shift kerja, durasi penugasan, atau informasi pendukung lainnya.')
                    ->maxLength(3000)->rows(3)->columnSpanFull(),
            ]);
    }
}
