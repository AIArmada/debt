<?php

namespace App\Filament\Resources\SupportAuditEntries;

use App\Filament\Resources\SupportAuditEntries\Pages\ManageSupportAuditEntries;
use App\Models\SupportAuditEntry;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class SupportAuditEntryResource extends Resource
{
    protected static ?string $model = SupportAuditEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Support';

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('action')->searchable(),
                TextColumn::make('actor.name')->label('Actor'),
                TextColumn::make('supportCase.subject')->label('Case')->limit(60),
                TextColumn::make('metadata.resource')->label('Viewed data'),
                TextColumn::make('metadata.status')->label('Status'),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageSupportAuditEntries::route('/')];
    }
}
