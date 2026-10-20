<?php

namespace App\Filament\Widgets;

use App\Models\SupportAuditEntry;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class RecentSupportAuditEntries extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return Filament::auth()->user()?->can('supportAudit.viewAny') === true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => SupportAuditEntry::query()->with('actor')->orderByDesc('created_at')->limit(6))
            ->columns([
                TextColumn::make('action'),
                TextColumn::make('actor.name')->label('Staff member'),
                TextColumn::make('metadata.resource')->label('Viewed data'),
                TextColumn::make('metadata.status')->label('Status'),
                TextColumn::make('created_at')->dateTime(),
            ])
            ->paginated(false);
    }
}
