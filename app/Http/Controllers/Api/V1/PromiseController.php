<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Promises\CreatePromise;
use App\Actions\Promises\Data\CreatePromiseData;
use App\Actions\Promises\Data\RecordMovementData;
use App\Actions\Promises\RecordMoneyMovement;
use App\Domain\Enums\PartyRole;
use App\Domain\Enums\SubjectType;
use App\Domain\Queries\OutstandingBalance;
use App\Models\FinancialProfile;
use App\Models\Obligation;
use App\Models\Record;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class PromiseController
{
    public function index(Request $request, OutstandingBalance $outstandingBalance): JsonResponse
    {
        $profile = $this->profile($request);
        $records = $profile->records()
            ->where('is_archived', false)
            ->with(['partyLinks' => function (Relation $query): void {
                $query->where('role', PartyRole::Counterparty->value)->with('party');
            }, 'obligations'])
            ->latest()
            ->get();

        return response()->json(['data' => $records->map(fn (Record $record): array => $this->recordPayload($record, $outstandingBalance))->values()]);
    }

    public function store(Request $request, CreatePromise $createPromise): JsonResponse
    {
        $profile = $this->profile($request);
        $user = $this->user($request);
        $record = $createPromise->handle($user, $profile, CreatePromiseData::fromInput($request->all(), (string) $profile->base_currency));

        return response()->json(['data' => ['id' => $record->getKey(), 'title' => $record->title]], 201);
    }

    public function storeMovement(Request $request, Obligation $obligation, RecordMoneyMovement $recordMoneyMovement, OutstandingBalance $outstandingBalance): JsonResponse
    {
        $profile = $this->profile($request);
        $obligation->loadMissing('record.profile', 'moneySubject');
        abort_unless($obligation->record->profile->is($profile), 404);
        abort_unless($obligation->subject_type === SubjectType::Money && $obligation->moneySubject !== null, 422);
        $currency = (string) $obligation->moneySubject->currency;
        $data = RecordMovementData::settlementFromInput($request->all(), $currency, today()->toDateString());
        $movement = $recordMoneyMovement->handle($this->user($request), $obligation, $data);

        return response()->json(['data' => ['id' => $movement->getKey(), 'balance' => $outstandingBalance->forObligation($obligation->fresh())]], 201);
    }

    private function profile(Request $request): FinancialProfile
    {
        $profile = $request->attributes->get('api_profile');
        abort_unless($profile instanceof FinancialProfile, 401);

        return $profile;
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    /**
     * @return array{id: mixed, title: string, note: string|null, parts: Collection<int, array{id: mixed, direction: string, subject_type: string, status: string, balances: array<string, int>}>}
     */
    private function recordPayload(Record $record, OutstandingBalance $outstandingBalance): array
    {
        return [
            'id' => $record->getKey(),
            'title' => $record->title,
            'note' => $record->note,
            'parts' => $record->obligations
                ->map(fn (Obligation $obligation): array => $this->obligationPayload($obligation, $outstandingBalance))
                ->values(),
        ];
    }

    /**
     * @return array{id: mixed, direction: string, subject_type: string, status: string, balances: array<string, int>}
     */
    private function obligationPayload(Obligation $obligation, OutstandingBalance $outstandingBalance): array
    {
        return [
            'id' => $obligation->getKey(),
            'direction' => $obligation->direction->value,
            'subject_type' => $obligation->subject_type->value,
            'status' => $obligation->status->value,
            'balances' => $obligation->subject_type === SubjectType::Money ? $outstandingBalance->forObligation($obligation) : [],
        ];
    }
}
