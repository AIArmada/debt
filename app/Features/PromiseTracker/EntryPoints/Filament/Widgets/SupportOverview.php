<?php

namespace App\Features\PromiseTracker\EntryPoints\Filament\Widgets;

use App\Features\PromiseTracker\Domain\Enums\SupportAccessGrantStatus;
use App\Features\PromiseTracker\Domain\Enums\SupportCaseStatus;
use App\Features\PromiseTracker\Models\SupportAccessGrant;
use App\Features\PromiseTracker\Models\SupportCase;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SupportOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Open cases', SupportCase::query()->where('status', SupportCaseStatus::Open->value)->count()),
            Stat::make('Pending access requests', SupportAccessGrant::query()->where('status', SupportAccessGrantStatus::Pending->value)->count()),
            Stat::make('Grants expiring within 15 minutes', SupportAccessGrant::query()
                ->where('status', SupportAccessGrantStatus::Approved->value)
                ->whereNull('revoked_at')
                ->where('starts_at', '<=', now())
                ->where('expires_at', '>', now())
                ->where('expires_at', '<=', now()->addMinutes(15))
                ->whereHas('supportCase', fn ($query) => $query->where('status', SupportCaseStatus::Open->value))
                ->count()),
        ];
    }
}
