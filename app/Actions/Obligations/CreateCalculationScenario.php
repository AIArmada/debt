<?php

namespace App\Actions\Obligations;

use App\Domain\Calculations\AdvancedObligationCalculator;
use App\Domain\Money\MoneyAmount;
use App\Models\CalculationScenario;
use App\Models\Obligation;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CreateCalculationScenario
{
    public function __construct(
        private readonly AdvancedObligationCalculator $calculator,
        private readonly AuditLogger $auditLogger,
    ) {}

    /** @param array{name: string, extra_payment: string, payment_frequency: string, horizon_months: int} $data */
    public function handle(User $user, Obligation $obligation, array $data): CalculationScenario
    {
        Gate::forUser($user)->authorize('view', $obligation);
        if ($obligation->currentPositionDirection() !== 'payable') {
            throw ValidationException::withMessages(['extra_payment' => 'Payment scenarios are only available for a current payable position.']);
        }

        try {
            $extraPayment = MoneyAmount::fromMajorOrZero($data['extra_payment'], (string) $obligation->currency);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['extra_payment' => $exception->getMessage()]);
        }
        $result = $this->calculator->project($obligation, $obligation->terms()->first(), $data['horizon_months'], $extraPayment, $data['payment_frequency']);
        $scenario = $obligation->calculationScenarios()->create([
            'created_by_user_id' => $user->getKey(),
            'name' => $data['name'],
            'extra_payment' => $extraPayment,
            'payment_frequency' => $data['payment_frequency'],
            'horizon_months' => $data['horizon_months'],
            'result' => $result,
        ]);
        $this->auditLogger->record($obligation->record->profile, $user, CalculationScenario::class, $scenario->getKey(), 'created', after: $scenario->only(['name', 'extra_payment', 'payment_frequency', 'horizon_months']));

        return $scenario;
    }
}
