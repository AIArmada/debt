<?php

namespace App\Filament\Resources\SupportCases\Pages;

use App\Actions\Staff\OpenSupportCase;
use App\Filament\Resources\SupportCases\SupportCaseResource;
use App\Models\FinancialProfile;
use App\Models\SupportCase;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Validation\ValidationException;

class ManageSupportCases extends ManageRecords
{
    protected static string $resource = SupportCaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Open support case')
                ->form([
                    Select::make('profile_id')->label('Financial profile')->relationship('profile', 'name')->searchable()->required(),
                    TextInput::make('subject')->maxLength(160)->required(),
                    Textarea::make('reason')->rows(4)->required(),
                ])
                ->using(function (array $data): SupportCase {
                    $actor = auth()->user();
                    abort_unless($actor instanceof User, 403);

                    $profileId = $data['profile_id'] ?? null;

                    if (! is_string($profileId)) {
                        throw ValidationException::withMessages(['profile_id' => 'Select a financial profile.']);
                    }

                    return app(OpenSupportCase::class)->handle(
                        $actor,
                        FinancialProfile::query()->whereKey($profileId)->firstOrFail(),
                        $data['subject'],
                        $data['reason'],
                    );
                }),
        ];
    }
}
