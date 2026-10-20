<?php

namespace App\Filament\Resources\StaffUsers;

use App\Actions\Staff\AssignStaffRole;
use App\Actions\Staff\RevokeStaffRole;
use App\Filament\Resources\StaffUsers\Pages\ManageStaffUsers;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class StaffUserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    protected static ?string $navigationLabel = 'Staff users';

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('roles', fn (Builder $query): Builder => $query->whereIn('name', User::STAFF_ROLES)->where('guard_name', 'web'))
            ->with('roles');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable()->sortable(),
                TextColumn::make('roles.name')->label('Staff roles')->formatStateUsing(fn (mixed $state, User $record): string => $record->getRoleNames()->intersect(User::STAFF_ROLES)->implode(', ')),
                TextColumn::make('email_verified_at')->label('Email verified')->dateTime(),
            ])
            ->recordActions([
                Action::make('assignRole')
                    ->label('Assign staff role')
                    ->visible(fn (): bool => Filament::auth()->user()?->can('staffAccess.assign') === true)
                    ->form([
                        Select::make('role_name')->label('Staff role')->options(array_combine(User::STAFF_ROLES, User::STAFF_ROLES))->required(),
                    ])
                    ->action(function (array $data, User $record): void {
                        $actor = Filament::auth()->user();
                        abort_unless($actor instanceof User, 403);
                        app(AssignStaffRole::class)->handle($actor, $record, $data['role_name']);
                    }),
                Action::make('revokeRole')
                    ->label('Revoke staff role')
                    ->color('danger')
                    ->visible(fn (): bool => Filament::auth()->user()?->can('staffAccess.assign') === true)
                    ->form([
                        Select::make('role_name')->label('Staff role')->options(fn (User $record): array => $record->getRoleNames()->intersect(User::STAFF_ROLES)->mapWithKeys(fn (string $role): array => [$role => $role])->all())->required(),
                    ])
                    ->action(function (array $data, User $record): void {
                        $actor = Filament::auth()->user();
                        abort_unless($actor instanceof User, 403);
                        app(RevokeStaffRole::class)->handle($actor, $record, $data['role_name']);
                    }),
            ]);
    }

    /** @return array<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return ['index' => ManageStaffUsers::route('/')];
    }
}
