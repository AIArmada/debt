<?php

namespace App\Features\PromiseTracker\EntryPoints\Filament\Resources\FinancialProfiles\Pages;

use App\Features\PromiseTracker\EntryPoints\Filament\Resources\FinancialProfiles\FinancialProfileResource;
use Filament\Resources\Pages\ManageRecords;

class ManageFinancialProfiles extends ManageRecords
{
    protected static string $resource = FinancialProfileResource::class;
}
