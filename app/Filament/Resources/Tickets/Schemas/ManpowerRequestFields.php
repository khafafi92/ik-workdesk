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
        return Section::make('The need for manpower')
            ->extraAttributes(['class' => 'manpower-request-form'])
            ->description('Explain the workforce\'s needs, and then add positions and numbers and requirements.')
            ->visible(fn (Get $get, ?Ticket $record): bool => self::isVisible($get, $record))
            ->columns(['default' => 1, 'md' => 2])->columnSpanFull()
            ->schema([
                Textarea::make('manpower_details.purpose')->label('Need for what?')
                    ->helperText('Describe jobs, projects, or operational needs that will be supported.')
                    ->validationMessages(['required' => 'Explain the purpose of labor.'])
                    ->required()->maxLength(3000)->rows(3)->columnSpanFull(),
                TextInput::make('manpower_details.work_location')->label('Location / assignment project')
                    ->extraInputAttributes(['style' => 'min-height:44px'])
                    ->validationMessages(['required' => 'Fill in the location or assignment project.'])
                    ->required()->maxLength(255),
                DatePicker::make('manpower_details.needed_at')->label('Workforce date required')
                    ->extraInputAttributes(['style' => 'min-height:44px'])
                    ->validationMessages(['required' => 'Select labor date is required.'])
                    ->helperText('Labor dates are expected available at the assignment location.')->required(),
                Select::make('manpower_details.reason')->label('Requirement reasons (optional)')
                    ->extraInputAttributes(['style' => 'min-height:44px'])
                    ->options(ManpowerRequestDetails::REASONS),
                TextEntry::make('manpower_total')->label('Total need')->state(function (Get $get): string {
                    $total = collect($get('manpower_details.positions') ?? [])->sum(fn (array $position): int => filter_var($position['quantity'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0);

                    return $total.' orang';
                }),
                Repeater::make('manpower_details.positions')->label('The required position')
                    ->schema([
                        TextInput::make('title')->label('Position / position')->required()->maxLength(150)->live(onBlur: true)
                            ->extraInputAttributes(['style' => 'min-height:44px'])
                            ->validationMessages(['required' => 'Fill the required positions or positions.']),
                        TextInput::make('quantity')->label('Number of people')->numeric()->rules(['integer'])
                            ->minValue(1)->maxValue(10000)->required()->live(onBlur: true)
                            ->extraInputAttributes(['style' => 'min-height:44px'])
                            ->validationMessages(['required' => 'Fill in the amount of people needed.', 'integer' => 'The number of people must be an integer.', 'min' => 'The number of needs is at least 1 person.']),
                        Textarea::make('specifications')->label('Job specification')
                            ->helperText('Describe the primary tasks, work skills, and tools or areas of work that are to be handled.')
                            ->validationMessages(['required' => 'Describe the job specification for this position.'])
                            ->required()->maxLength(3000)->rows(3),
                        Textarea::make('requirements')->label('Candidate requirements')
                            ->helperText('Write down the needed education, experience, and certification or special skills.')
                            ->validationMessages(['required' => 'Explain the candidates\' requirements for this position.'])
                            ->required()->maxLength(3000)->rows(3),
                        Select::make('employment_type')->label('Type of labor needs (optional)')
                            ->extraInputAttributes(['style' => 'min-height:44px'])
                            ->options(ManpowerRequestDetails::EMPLOYMENT_TYPES)->columnSpanFull(),
                    ])
                    ->columns(['default' => 1, 'md' => 2])->defaultItems(1)->minItems(1)->maxItems(50)
                    ->required()->reorderable(false)->addActionLabel('Add position')
                    ->addAction(fn (Action $action) => $action->extraAttributes(['style' => 'min-height:44px!important']))
                    ->deleteAction(fn (Action $action) => $action->label('Delete position')->tooltip('Delete position')->extraAttributes(['style' => 'min-height:44px;min-width:44px']))
                    ->itemLabel(fn (array $state): string => filled($state['title'] ?? null) ? $state['title'] : 'New position')
                    ->columnSpanFull(),
                Textarea::make('manpower_details.notes')->label('Additional notes (optional)')
                    ->helperText('If necessary, explain work shifts, the duration of assignments, or other support information.')
                    ->maxLength(3000)->rows(3)->columnSpanFull(),
            ]);
    }
}
