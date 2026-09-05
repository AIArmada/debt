<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::query()->where('email', 'test@example.com')->first();

        if ($user === null) {
            $user = User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);
        } else {
            $user->forceFill([
                'name' => 'Test User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ])->save();
        }

        $profile = $user->financialProfiles()->firstOrCreate(
            ['name' => 'Personal'],
            ['type' => 'personal', 'base_currency' => 'MYR', 'timezone' => 'Asia/Kuala_Lumpur'],
        );

        $home = $profile->records()->firstOrCreate(
            ['title' => 'Home financing'],
            ['description' => 'A single money obligation tracked from the current statement.', 'sensitivity' => 'private'],
        );

        $home->obligations()->firstOrCreate(
            ['title' => 'Home financing balance'],
            [
                'direction' => 'payable',
                'obligation_kind' => 'money',
                'category' => 'bank_financing',
                'status' => 'active',
                'currency' => 'MYR',
                'current_principal_balance' => 428000,
                'current_total_balance' => 428000,
                'minimum_payment_amount' => 65000,
                'next_due_on' => today()->addDays(9),
                'data_confidence' => 'verified',
                'tracking_mode' => 'snapshot',
                'currency_opening_balances' => ['MYR' => 428000],
                'currency_balances' => ['MYR' => 428000],
            ],
        );

        $family = $profile->records()->firstOrCreate(
            ['title' => 'Family arrangement'],
            ['description' => 'One arrangement with different kinds of commitments, kept separate for clarity.', 'sensitivity' => 'private'],
        );

        $family->obligations()->firstOrCreate(
            ['title' => 'Family advance'],
            [
                'direction' => 'receivable',
                'obligation_kind' => 'money',
                'category' => 'family_support',
                'status' => 'active',
                'currency' => 'MYR',
                'current_total_balance' => 115000,
                'data_confidence' => 'partial',
                'tracking_mode' => 'snapshot',
                'currency_opening_balances' => ['MYR' => 115000],
                'currency_balances' => ['MYR' => 115000],
            ],
        );

        $family->obligations()->firstOrCreate(
            ['title' => 'Gold bracelet to return'],
            [
                'direction' => 'payable',
                'obligation_kind' => 'asset',
                'category' => 'precious_item',
                'status' => 'active',
                'subject_name' => '916 gold bracelet',
                'subject_quantity' => '1.0000',
                'current_subject_quantity' => '1.0000',
                'subject_unit' => 'item',
                'subject_condition' => 'good',
                'asset_type' => 'physical',
                'data_confidence' => 'partial',
                'due_on' => today()->addDays(14),
                'next_due_on' => today()->addDays(14),
            ],
        );

        $family->obligations()->firstOrCreate(
            ['title' => 'Send inheritance documents'],
            [
                'direction' => 'receivable',
                'obligation_kind' => 'action',
                'category' => 'estate_family',
                'status' => 'active',
                'completion_criteria' => 'Send the signed documents and confirm receipt.',
                'data_confidence' => 'partial',
                'next_due_on' => today()->addDays(5),
            ],
        );
    }
}
