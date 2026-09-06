<?php

use App\Domain\Enums\Direction;
use App\Domain\Enums\MemberRole;
use App\Domain\Enums\MoneyEntry;
use App\Domain\Enums\MovementStatus;
use App\Domain\Enums\ObligationStatus;
use App\Domain\Enums\PartyKind;
use App\Domain\Enums\PartyRole;
use App\Domain\Enums\PartyStatus;
use App\Domain\Enums\SubjectType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->addCheck('profile_members', 'profile_members_role_valid', 'role', MemberRole::cases());
        $this->addCheck('parties', 'parties_kind_valid', 'kind', PartyKind::cases());
        $this->addCheck('parties', 'parties_status_valid', 'status', PartyStatus::cases());
        $this->addCheck('record_parties', 'record_parties_role_valid', 'role', PartyRole::cases());
        $this->addCheck('obligations', 'obligations_direction_valid', 'direction', Direction::cases());
        $this->addCheck('obligations', 'obligations_status_valid', 'status', ObligationStatus::cases());
        $this->addCheck('obligations', 'obligations_subject_type_valid', 'subject_type', SubjectType::cases());
        $this->addCheck('money_movements', 'money_movements_entry_valid', 'entry', MoneyEntry::cases());
        $this->addCheck('money_movements', 'money_movements_status_valid', 'status', MovementStatus::cases());
        $this->addCheck('quantity_returns', 'quantity_returns_status_valid', 'status', MovementStatus::cases());
    }

    public function down(): void
    {
        foreach ([
            'profile_members' => ['profile_members_role_valid'],
            'parties' => ['parties_kind_valid', 'parties_status_valid'],
            'record_parties' => ['record_parties_role_valid'],
            'obligations' => ['obligations_direction_valid', 'obligations_status_valid', 'obligations_subject_type_valid'],
            'money_movements' => ['money_movements_entry_valid', 'money_movements_status_valid'],
            'quantity_returns' => ['quantity_returns_status_valid'],
        ] as $table => $constraints) {
            foreach ($constraints as $constraint) {
                DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$constraint}");
            }
        }
    }

    /**
     * @param  list<BackedEnum>  $cases
     */
    private function addCheck(string $table, string $constraint, string $column, array $cases): void
    {
        $values = implode("','", array_map(static fn (BackedEnum $case): string => (string) $case->value, $cases));
        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$constraint} CHECK ({$column} IN ('{$values}'))");
    }
};
