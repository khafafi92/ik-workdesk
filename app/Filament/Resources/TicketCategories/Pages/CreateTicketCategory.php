<?php

namespace App\Filament\Resources\TicketCategories\Pages;

use App\Filament\Resources\TicketCategories\TicketCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTicketCategory extends CreateRecord
{
    protected static string $resource = TicketCategoryResource::class;
}
