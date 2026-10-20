<?php

namespace App\Filament\Resources\SupportCases;

use App\Actions\Staff\CloseSupportCase;
use App\Actions\Staff\RequestSupportAccess;
use App\Domain\Enums\SupportCaseStatus;
use App\Filament\Pages\SupportCaseWorkspace;
use App\Filament\Resources\SupportCases\Pages\ManageSupportCases;
use App\Models\SupportCase;
use App\Models\User;
use App\Services\SupportAuditLogger;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class SupportCaseResource extends Resource
{
    protected static ?string $model = SupportCase::class;

    protected static ?string $recordTitleAttribute = 'subject';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLifebuoy;

    protected static string|UnitEnum|null $navigationGroup = 'Support';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('profile_id')
                ->label('Financial profile')
                ->relationship('profile', 'name')
                ->searchable()
                ->required(),
            TextInput::make('subject')->maxLength(160)->required(),
            Textarea::make('reason')->rows(4)->required(),
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $actor = Filament::auth()->user();

        if ($actor instanceof User) {
            app(SupportAuditLogger::class)->record($actor, 'support_case.list_viewed');
        }

        return parent::getEloquentQuery();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('subject')->searchable()->limit(70),
                TextColumn::make('profile.name')->label('Profile')->limit(50),
                TextColumn::make('opener.name')->label('Opened by'),
                TextColumn::make('status')->badge()->formatStateUsing(fn (SupportCaseStatus $state): string => $state->value),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->recordActions([
                Action::make('workspace')
                    ->label('Open workspace')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (SupportCase $record): string => SupportCaseWorkspace::getUrl(['record' => $record])),
                Action::make('requestAccess')
                    ->label('Request profile access')
                    ->requiresConfirmation()
                    ->visible(fn (SupportCase $record): bool => auth()->user()?->can('supportAccess.request') === true
                        && $record->isOpen()
                        && $record->activeGrant() === null
                        && ! $record->grants()->where('status', 'pending')->exists())
                    ->action(function (SupportCase $record): void {
                        $actor = auth()->user();
                        abort_unless($actor instanceof User, 403);
                        app(RequestSupportAccess::class)->handle($actor, $record);
                    }),
                Action::make('close')
                    ->label('Close case')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (SupportCase $record): bool => auth()->user()?->can('supportCase.update') === true && $record->isOpen())
                    ->action(function (SupportCase $record): void {
                        $actor = auth()->user();
                        abort_unless($actor instanceof User, 403);
                        app(CloseSupportCase::class)->handle($actor, $record);
                    }),
            ]);
    }

    /** @return array<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['subject'];
    }

    public static function getGlobalSearchResultUrl(Model $record): ?string
    {
        return $record instanceof SupportCase ? SupportCaseWorkspace::getUrl(['record' => $record]) : null;
    }

    public static function getPages(): array
    {
        return ['index' => ManageSupportCases::route('/')];
    }
}
