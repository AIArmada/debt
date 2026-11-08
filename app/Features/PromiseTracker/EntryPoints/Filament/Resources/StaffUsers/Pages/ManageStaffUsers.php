<?php

namespace App\Features\PromiseTracker\EntryPoints\Filament\Resources\StaffUsers\Pages;

use App\Features\PromiseTracker\EntryPoints\Filament\Resources\StaffUsers\StaffUserResource;
use Filament\Resources\Pages\ManageRecords;

class ManageStaffUsers extends ManageRecords
{
    protected static string $resource = StaffUserResource::class;
}
