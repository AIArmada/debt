<?php

namespace App\Livewire\Promises;

use App\Actions\Promises\CreatePromise as CreatePromiseAction;
use App\Actions\Promises\Data\CreatePromiseData;
use App\Domain\Enums\Direction;
use App\Domain\Enums\PartyStatus;
use App\Domain\Enums\SubjectType;
use App\Domain\StringNormalizer;
use App\Models\FinancialProfile;
use App\Models\Party;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

final class Create extends Component
{
    public FinancialProfile $profile;

    public string $partyName = '';

    public ?string $partyId = null;

    public string $direction = Direction::Payable->value;

    public SubjectType $subjectType = SubjectType::Money;

    public string $amount = '';

    public ?string $dueOn = null;

    public string $note = '';

    public string $quantityName = '';

    public string $quantityTotal = '';

    public string $quantityUnit = '';

    public bool $isFractionable = false;

    public string $doneCriteria = '';

    public function mount(FinancialProfile $profile): void
    {
        $this->profile = $profile;
        Gate::authorize('createObligation', $profile);

        $this->partyName = (string) request()->query('partyName', '');
        $direction = Direction::tryFrom((string) request()->query('direction', $this->direction));
        $this->direction = $direction === null ? $this->direction : $direction->value;
        $this->subjectType = SubjectType::tryFrom((string) request()->query('subjectType', $this->subjectType->value)) ?? $this->subjectType;
        $this->amount = (string) request()->query('amount', '');
        $this->dueOn = filled(request()->query('dueOn')) ? (string) request()->query('dueOn') : null;
        $this->note = (string) request()->query('note', '');
        $this->quantityName = (string) request()->query('quantityName', '');
        $this->quantityTotal = (string) request()->query('quantityTotal', '');
        $this->quantityUnit = (string) request()->query('quantityUnit', '');
        $this->isFractionable = request()->boolean('isFractionable');
        $this->doneCriteria = (string) request()->query('doneCriteria', '');
    }

    public function updatedPartyName(): void
    {
        $this->partyId = null;
    }

    public function selectParty(string $partyId): void
    {
        $party = $this->profile->parties()
            ->whereKey($partyId)
            ->where('status', PartyStatus::Active->value)
            ->firstOrFail();

        $this->partyName = $party->display_name;
        $this->partyId = $party->getKey();
    }

    public function selectDirection(string $direction): void
    {
        $this->direction = Direction::fromRequest($direction)->value;
    }

    public function selectSubjectType(string $subjectType): void
    {
        $this->subjectType = SubjectType::from($subjectType);
    }

    public function save(CreatePromiseAction $createPromise): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        $data = CreatePromiseData::fromInput([
            'partyName' => $this->partyName,
            'partyId' => $this->partyId,
            'direction' => $this->direction,
            'subjectType' => $this->subjectType->value,
            'amount' => $this->amount,
            'dueOn' => $this->dueOn,
            'note' => $this->note,
            'quantityName' => $this->quantityName,
            'quantityTotal' => $this->quantityTotal,
            'quantityUnit' => $this->quantityUnit,
            'isFractionable' => $this->isFractionable,
            'doneCriteria' => $this->doneCriteria,
        ], (string) $this->profile->base_currency);

        $record = $createPromise->handle($user, $this->profile, $data);

        session()->flash('promise-created', 'Promise added.');
        $this->redirectRoute('promises.show', ['profile' => $this->profile, 'record' => $record], navigate: true);
    }

    public function render(): View
    {
        $partyMatches = $this->partyMatches();

        return view('livewire.promises.create', compact('partyMatches'))
            ->layout('layouts.app', ['title' => 'New promise']);
    }

    /** @return Collection<int, Party> */
    private function partyMatches(): Collection
    {
        $term = StringNormalizer::trimmed($this->partyName);
        if ($term === '' || $this->partyId !== null) {
            return new Collection;
        }

        return $this->profile->parties()
            ->select(['id', 'profile_id', 'display_name', 'kind', 'status'])
            ->where('status', PartyStatus::Active->value)
            ->where(function (Builder $query) use ($term): void {
                $query->where('display_name', 'like', "{$term}%");
            })
            ->orderBy('display_name')
            ->limit(6)
            ->get();
    }
}
