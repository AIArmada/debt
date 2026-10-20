<?php

namespace App\Filament\Resources\StaffUsers\Pages;

use App\Filament\Resources\StaffUsers\StaffUserResource;
use Filament\Resources\Pages\ManageRecords;

class ManageStaffUsers extends ManageRecords
{
    protected static string $resource = StaffUserResource::class;
}
