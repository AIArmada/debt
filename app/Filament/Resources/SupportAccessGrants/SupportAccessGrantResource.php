<?php

namespace App\Filament\Resources\SupportAccessGrants;

use App\Actions\Staff\ApproveSupportAccess;
use App\Actions\Staff\RevokeSupportAccess;
use App\Domain\Enums\SupportAccessGrantStatus;
use App\Filament\Resources\SupportAccessGrants\Pages\ManageSupportAccessGrants;
use App\Models\SupportAccessGrant;
use App\Models\User;
use App\Services\SupportAuditLogger;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class SupportAccessGrantResource extends Resource
{
    protected static ?string $model = SupportAccessGrant::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|UnitEnum|null $navigationGroup = 'Support';

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function getEloquentQuery(): Builder
    {
        $actor = Filament::auth()->user();

        if ($actor instanceof User) {
            app(SupportAuditLogger::class)->record($actor, 'support_access_grant.list_viewed');
        }

        return parent::getEloquentQuery();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('supportCase.subject')->label('Case')->limit(60),
                TextColumn::make('supportCase.profile.name')->label('Profile')->limit(40),
                TextColumn::make('requester.name')->label('Requested by'),
                TextColumn::make('approver.name')->label('Approved by'),
                TextColumn::make('status')->badge()->formatStateUsing(fn (SupportAccessGrantStatus $state): string => $state->value),
                TextColumn::make('expires_at')->dateTime()->sortable(),
            ])
            ->recordActions([
                Action::make('approve')
                    ->requiresConfirmation()
                    ->visible(function (SupportAccessGrant $record): bool {
                        $actor = auth()->user();

                        return $actor instanceof User
                            && $actor->can('supportAccess.approve')
                            && $record->status === SupportAccessGrantStatus::Pending
                            && $record->requested_by !== $actor->getKey();
                    })
                    ->action(function (SupportAccessGrant $record): void {
                        $actor = auth()->user();
                        abort_unless($actor instanceof User, 403);
                        app(ApproveSupportAccess::class)->handle($actor, $record);
                    }),
                Action::make('revoke')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (SupportAccessGrant $record): bool => auth()->user()?->can('supportAccess.revoke') === true && in_array($record->status, [SupportAccessGrantStatus::Pending, SupportAccessGrantStatus::Approved], true))
                    ->action(function (SupportAccessGrant $record): void {
                        $actor = auth()->user();
                        abort_unless($actor instanceof User, 403);
                        app(RevokeSupportAccess::class)->handle($actor, $record);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageSupportAccessGrants::route('/')];
    }
}
