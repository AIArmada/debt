<?php

namespace App\Filament\Resources\FinancialProfiles;

use App\Filament\Resources\FinancialProfiles\Pages\ManageFinancialProfiles;
use App\Models\FinancialProfile;
use App\Models\User;
use App\Services\SupportAuditLogger;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\GlobalSearch\GlobalSearchResult;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use UnitEnum;

class FinancialProfileResource extends Resource
{
    protected static ?string $model = FinancialProfile::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Directory';

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function getEloquentQuery(): Builder
    {
        $actor = Filament::auth()->user();

        if ($actor instanceof User) {
            app(SupportAuditLogger::class)->record($actor, 'profile_directory.viewed', metadata: ['resource' => 'financial_profiles']);
        }

        return parent::getEloquentQuery()->with('owner')->withCount(['members', 'records']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('owner.name')->label('Owner name')->searchable(),
                TextColumn::make('owner.email')->label('Owner email')->searchable(),
                TextColumn::make('base_currency')->label('Base currency'),
                TextColumn::make('timezone'),
                TextColumn::make('members_count')->label('Members')->numeric(),
                TextColumn::make('records_count')->label('Records')->numeric(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ]);
    }

    /** @return array<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'owner.name', 'owner.email'];
    }

    public static function getGlobalSearchResultUrl(Model $record): ?string
    {
        return $record instanceof FinancialProfile ? static::getUrl('index') : null;
    }

    /** @return array<string, string> */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        if (! $record instanceof FinancialProfile) {
            return [];
        }

        return ['Owner' => $record->owner->name, 'Owner email' => $record->owner->email];
    }

    /** @return Collection<int, GlobalSearchResult> */
    public static function getGlobalSearchResults(string $search): Collection
    {
        $results = parent::getGlobalSearchResults($search);
        $actor = Filament::auth()->user();

        if ($actor instanceof User) {
            app(SupportAuditLogger::class)->record($actor, 'profile_directory.searched', metadata: ['resource' => 'financial_profiles', 'count' => $results->count()]);
        }

        return $results;
    }

    public static function getPages(): array
    {
        return ['index' => ManageFinancialProfiles::route('/')];
    }
}
