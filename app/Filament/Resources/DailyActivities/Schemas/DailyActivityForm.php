<?php

namespace App\Filament\Resources\DailyActivities\Schemas;

use App\Filament\Resources\WorkTasks\WorkTaskResource;
use App\Models\ActivityCategory;
use App\Models\WorkProject;
use App\Models\WorkTask;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class DailyActivityForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Waktu & Pekerjaan')
                ->description('Catat pekerjaan aktual. Durasi dihitung otomatis dari jam mulai dan selesai.')
                ->schema([
                    DatePicker::make('work_date')->label('Tanggal Pekerjaan')
                        ->default(today()->toDateString())->minDate(today()->subDays(3))->maxDate(today())
                        ->native(false)->displayFormat('d/m/Y')
                        ->helperText('Dapat diisi ulang maksimal 3 hari ke belakang.')->required(),
                    TimePicker::make('start_time')->label('Jam Mulai')->seconds(false)->native(false)->live()
                        ->afterStateUpdated(fn (Get $get, Set $set) => self::updateDuration($get, $set))->required(),
                    TimePicker::make('end_time')->label('Jam Selesai')->seconds(false)->native(false)->live()
                        ->afterStateUpdated(fn (Get $get, Set $set) => self::updateDuration($get, $set))->required(),
                    TextInput::make('duration_minutes')->label('Durasi')->suffix('menit')
                        ->disabled()->dehydrated()->required(),
                    TextInput::make('title')->label('Pekerjaan yang Dilakukan')
                        ->placeholder('Contoh: Menyiapkan laporan bulanan')->required()->maxLength(255)->columnSpanFull(),
                    Textarea::make('description')->label('Detail Aktivitas')->rows(3)->columnSpanFull(),
                    Textarea::make('result')->label('Hasil / Output')
                        ->placeholder('Jelaskan hasil yang selesai atau progres yang dicapai.')->rows(3)->columnSpanFull(),
                ])->columns(4),

            Section::make('Klasifikasi Pekerjaan')
                ->description('Project/operasional dan task/manual adalah dua informasi yang berbeda.')
                ->schema([
                    Select::make('source_type')->label('Sumber Pekerjaan')->options([
                        'task' => 'Dari Task yang Masuk', 'manual' => 'Manual / Inisiatif',
                    ])->default('manual')->selectablePlaceholder(false)->live()->required(),
                    Select::make('work_task_id')->label('Source Task')
                        ->relationship(
                            name: 'workTask', titleAttribute: 'title',
                            modifyQueryUsing: fn (Builder $query): Builder => $query
                                ->whereIn('work_tasks.id', WorkTaskResource::getEloquentQuery()->select('work_tasks.id'))
                                ->latest('work_tasks.created_at')
                        )
                        ->getOptionLabelFromRecordUsing(fn (WorkTask $task): string => "{$task->task_no} · {$task->title}")
                        ->searchable(['task_no', 'title'])->preload()
                        ->visible(fn (Get $get): bool => $get('source_type') === 'task')
                        ->required(fn (Get $get): bool => $get('source_type') === 'task')
                        ->dehydrated(fn (Get $get): bool => $get('source_type') === 'task')->live()
                        ->afterStateUpdated(function (mixed $state, Set $set): void {
                            $task = filled($state)
                                ? WorkTaskResource::getEloquentQuery()->with(['project', 'ticket'])->find($state)
                                : null;
                            if (! $task) {
                                return;
                            }
                            $set('title', $task->title);
                            $set('description', $task->description);
                            if ($task->work_project_id) {
                                $set('work_context', 'project');
                                $set('work_project_id', $task->work_project_id);
                            }
                            if ($task->ticket?->requester_department_id) {
                                $set('requester_type', 'division');
                                $set('requester_department_id', $task->ticket->requester_department_id);
                            }
                        })->columnSpanFull(),
                    Select::make('work_context')->label('Konteks Pekerjaan')->options([
                        'project' => 'Project', 'operational' => 'Operasional / Non-project',
                    ])->default('operational')->selectablePlaceholder(false)->live()->required(),
                    Select::make('work_project_id')->label('Project')
                        ->relationship(
                            name: 'project', titleAttribute: 'name',
                            modifyQueryUsing: fn (Builder $query): Builder => $query->where('status', 'active')->orderBy('name')
                        )
                        ->getOptionLabelFromRecordUsing(fn (WorkProject $project): string => "{$project->code} · {$project->name}")
                        ->searchable(['code', 'name'])->preload()
                        ->visible(fn (Get $get): bool => $get('work_context') === 'project')
                        ->required(fn (Get $get): bool => $get('work_context') === 'project')
                        ->dehydrated(fn (Get $get): bool => $get('work_context') === 'project'),
                    Select::make('activity_category_id')->label('Kategori Operasional')
                        ->relationship(
                            name: 'activityCategory', titleAttribute: 'name',
                            modifyQueryUsing: fn (Builder $query): Builder => $query->where('is_active', true)->orderBy('name')
                        )
                        ->getOptionLabelFromRecordUsing(fn (ActivityCategory $category): string => "{$category->code} · {$category->name}")
                        ->searchable(['code', 'name'])->preload()
                        ->visible(fn (Get $get): bool => $get('work_context') === 'operational')
                        ->required(fn (Get $get): bool => $get('work_context') === 'operational')
                        ->dehydrated(fn (Get $get): bool => $get('work_context') === 'operational'),
                ])->columns(2),

            Section::make('Pemberi Pekerjaan')
                ->description('Jika berasal dari task, informasi ini dapat terisi otomatis.')
                ->schema([
                    Select::make('requester_type')->label('Pekerjaan Diminta Oleh')->options([
                        'company' => 'Perusahaan / Client', 'division' => 'Divisi', 'individual' => 'Individu',
                    ])->selectablePlaceholder(false)->live()->required(),
                    TextInput::make('requester_company_name')->label('Nama Perusahaan / Client')->maxLength(255)
                        ->visible(fn (Get $get): bool => $get('requester_type') === 'company')
                        ->required(fn (Get $get): bool => $get('requester_type') === 'company')
                        ->dehydrated(fn (Get $get): bool => $get('requester_type') === 'company'),
                    Select::make('requester_department_id')->label('Divisi')
                        ->relationship('requesterDepartment', 'name', fn (Builder $query) => $query->where('is_active', true))
                        ->searchable()->preload()->visible(fn (Get $get): bool => $get('requester_type') === 'division')
                        ->required(fn (Get $get): bool => $get('requester_type') === 'division')
                        ->dehydrated(fn (Get $get): bool => $get('requester_type') === 'division'),
                    Select::make('requester_employee_id')->label('Individu')
                        ->relationship('requesterEmployee', 'name', fn (Builder $query) => $query->where('is_active', true))
                        ->searchable()->preload()->visible(fn (Get $get): bool => $get('requester_type') === 'individual')
                        ->required(fn (Get $get): bool => $get('requester_type') === 'individual')
                        ->dehydrated(fn (Get $get): bool => $get('requester_type') === 'individual'),
                ])->columns(2),
        ]);
    }

    private static function updateDuration(Get $get, Set $set): void
    {
        if (blank($get('start_time')) || blank($get('end_time'))) {
            return;
        }
        $start = Carbon::parse($get('start_time'));
        $end = Carbon::parse($get('end_time'));
        $set('duration_minutes', $end->greaterThan($start) ? $start->diffInMinutes($end) : null);
    }
}
