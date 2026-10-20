<?php

namespace App\Filament\Actions;

use App\Filament\Resources\ItDevices\Pages\EditItDevice;
use App\Models\Employee;
use App\Models\ItDevice;
use App\Services\ItInfrastructure\ItDeviceService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class TransferItDeviceAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'transferDevice';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Pindahkan aset')->icon('heroicon-o-arrows-right-left')
            ->visible(fn (ItDevice $record): bool => Gate::allows('transfer', $record))
            ->modalHeading('Pindahkan pemakai aset')
            ->modalWidth('2xl')->extraModalWindowAttributes(['class' => 'it-device-transfer-modal'])
            ->modalDescription('Pemakai baru mengubah status menjadi Digunakan. Pengembalian ke stok mengosongkan pemakai. Riwayat pemeriksaan tetap tersimpan.')
            ->modalSubmitActionLabel('Simpan perpindahan')->modalCancelActionLabel('Batal')
            ->fillForm(fn (ItDevice $record): array => [
                'destination' => 'user', 'assigned_employee_id' => null,
                'assignment_version' => $record->assignmentVersion(),
                'transferred_on' => today()->format('Y-m-d'),
            ])
            ->schema([
                TextEntry::make('current_assignee')->label('Pemakai saat ini')->state(fn (ItDevice $record): string => $record->assigneeLabel()),
                Hidden::make('assignment_version')->required(),
                Select::make('destination')->label('Tujuan perpindahan')->options(['user' => 'Pemakai baru', 'stock' => 'Kembalikan ke stok'])->required()->live(),
                Select::make('assigned_employee_id')->label('Pemakai baru')->searchable()
                    ->options(fn (ItDevice $record): array => Employee::query()->where('is_active', true)->whereHas('user')->where('id', '!=', $record->assigned_employee_id ?? 0)
                        ->with(['user', 'department'])->orderBy('name')->get()
                        ->mapWithKeys(fn (Employee $employee): array => [$employee->id => $employee->user->name.' / '.($employee->department?->name ?? 'Tanpa department')])->all())
                    ->visible(fn (Get $get): bool => $get('destination') === 'user')->required(fn (Get $get): bool => $get('destination') === 'user'),
                DatePicker::make('transferred_on')->label('Tanggal perpindahan')->required()->maxDate(today()),
                Textarea::make('notes')->label('Catatan serah terima')->helperText('Tuliskan alasan perpindahan atau kondisi saat serah terima.')->required()->maxLength(2000)->rows(3),
            ])
            ->action(function (array $data, ItDevice $record, Component $livewire): void {
                try {
                    app(ItDeviceService::class)->transfer(auth()->user(), $record, $data);
                } catch (ValidationException $exception) {
                    Notification::make()->title('Perpindahan belum disimpan')->body(collect($exception->errors())->flatten()->first())->danger()->send();
                    $this->halt();
                }
                Notification::make()->title('Perpindahan aset tersimpan')->success()->send();
                if ($livewire instanceof EditItDevice) {
                    $livewire->redirect(EditItDevice::getUrl(['record' => $record->id]));
                }
            });
    }
}
