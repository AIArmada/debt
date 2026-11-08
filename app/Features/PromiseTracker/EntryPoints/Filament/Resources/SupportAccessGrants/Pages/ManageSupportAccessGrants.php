<?php

namespace App\Features\PromiseTracker\EntryPoints\Filament\Resources\SupportAccessGrants\Pages;

use App\Features\PromiseTracker\EntryPoints\Filament\Resources\SupportAccessGrants\SupportAccessGrantResource;
use Filament\Resources\Pages\ManageRecords;

class ManageSupportAccessGrants extends ManageRecords
{
    protected static string $resource = SupportAccessGrantResource::class;
}
