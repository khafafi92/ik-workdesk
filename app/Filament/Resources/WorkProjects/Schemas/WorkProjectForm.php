<?php

namespace App\Filament\Resources\WorkProjects\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WorkProjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Project Information')->schema([
                TextInput::make('code')->label('Project Code')->required()->unique(ignoreRecord: true)->maxLength(50),
                TextInput::make('name')->label('Project Name')->required()->maxLength(255),
                TextInput::make('company_name')->label(__('Company / Client'))->maxLength(255),
                Select::make('department_id')->label('Owning Division')->relationship('department', 'name')->searchable()->preload(),
                Select::make('manager_user_id')->label(__('Project Manager'))->relationship('manager', 'name')->searchable()->preload(),
                Select::make('status')->options([
                    'active' => __('Active'), 'on_hold' => 'On Hold', 'completed' => __('Completed'), 'cancelled' => __('Cancelled'),
                ])->default('active')->required(),
                DatePicker::make('start_date')->label(__('Start Date'))->native(false),
                DatePicker::make('end_date')->label(__('End Date'))->native(false)->afterOrEqual('start_date'),
                Textarea::make('description')->label(__('Description'))->rows(4)->columnSpanFull(),
            ])->columns(2),
        ]);
    }
}
