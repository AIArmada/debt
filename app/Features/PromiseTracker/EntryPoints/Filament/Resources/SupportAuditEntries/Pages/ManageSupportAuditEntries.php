<?php

namespace App\Features\PromiseTracker\EntryPoints\Filament\Resources\SupportAuditEntries\Pages;

use App\Features\PromiseTracker\EntryPoints\Filament\Resources\SupportAuditEntries\SupportAuditEntryResource;
use Filament\Resources\Pages\ManageRecords;

class ManageSupportAuditEntries extends ManageRecords
{
    protected static string $resource = SupportAuditEntryResource::class;
}
