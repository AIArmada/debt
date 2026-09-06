<?php

namespace Database\Seeders;

use App\Actions\Promises\CreatePromise;
use App\Actions\Promises\Data\CreatePromiseData;
use App\Domain\Enums\Direction;
use App\Domain\Enums\MemberRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->firstOrCreate(
            ['email' => 'test@example.com'],
            ['name' => 'Test User', 'password' => Hash::make('password'), 'email_verified_at' => now()],
        );
        $user->forceFill(['name' => 'Test User', 'password' => Hash::make('password'), 'email_verified_at' => now()])->save();

        $profile = $user->financialProfiles()->firstOrCreate([
            'name' => 'Personal',
        ], [
            'base_currency' => 'MYR',
            'timezone' => 'Asia/Kuala_Lumpur',
        ]);
        $profile->members()->firstOrCreate([
            'user_id' => $user->getKey(),
        ], [
            'role' => MemberRole::Owner,
            'accepted_at' => now(),
        ]);

        if ($profile->records()->exists()) {
            return;
        }

        $createPromise = app(CreatePromise::class);
        $createPromise->handle($user, $profile, CreatePromiseData::fromInput([
            'partyName' => 'Home financing',
            'partyId' => null,
            'direction' => Direction::Payable->value,
            'amount' => '4280.00',
            'dueOn' => today()->addDays(9)->toDateString(),
            'note' => 'A manual promise tracked from confirmed movements.',
        ], (string) $profile->base_currency));
        $createPromise->handle($user, $profile, CreatePromiseData::fromInput([
            'partyName' => 'Family support',
            'partyId' => null,
            'direction' => Direction::Receivable->value,
            'amount' => '1150.00',
            'dueOn' => null,
            'note' => 'A simple receivable tracked from confirmed movements.',
        ], (string) $profile->base_currency));
    }
}
