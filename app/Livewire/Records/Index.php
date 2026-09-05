<?php

namespace App\Livewire\Records;

use App\Livewire\Concerns\InteractsWithAccessibleProfiles;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use InteractsWithAccessibleProfiles;
    use WithPagination;

    #[Url(as: 'profile', keep: true)]
    public ?string $profileId = null;

    public string $direction = 'all';

    public string $kind = 'all';

    public function mount(): void
    {
        $profiles = $this->accessibleProfilesCollection();
        $selectedProfileId = request()->query('profile') ?? session('selected_profile_id');
        $this->profileId = is_string($selectedProfileId) && $profiles->contains('id', $selectedProfileId)
            ? $selectedProfileId
            : $profiles->first()?->getKey();
    }

    public function updatedProfileId(): void
    {
        $this->accessibleProfile($this->profileId);
        session()->put('selected_profile_id', $this->profileId);
        $this->resetPage();
    }

    public function updatedDirection(): void
    {
        $this->resetPage();
    }

    public function updatedKind(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $profiles = $this->accessibleProfilesCollection();
        $profile = $this->accessibleProfile($this->profileId);
        $recordsQuery = $profile->records()
            ->select(['id', 'profile_id', 'title', 'description', 'is_archived', 'created_at'])
            ->with([
                'partyLinks' => fn ($query) => $query->select(['id', 'record_id', 'party_id', 'role', 'is_primary']),
                'partyLinks.party' => fn ($query) => $query->select(['id', 'preferred_name']),
                'obligations' => fn ($query) => $query->select([
                    'id', 'record_id', 'obligation_kind', 'direction', 'category', 'title', 'status', 'currency',
                    'current_total_balance', 'currency_balances', 'current_subject_quantity', 'subject_unit', 'created_at',
                ]),
            ])
            ->where('is_archived', false)
            ->latest();

        if ($this->kind !== 'all' || $this->direction !== 'all') {
            $recordsQuery->whereHas('obligations', function (Builder $query): void {
                if ($this->kind !== 'all') {
                    $query->where('obligation_kind', $this->kind);
                }

                if ($this->direction === 'all') {
                    return;
                }

                $query->where(function (Builder $directionQuery): void {
                    $directionQuery
                        ->where(function (Builder $query): void {
                            $query->where('obligation_kind', '!=', 'money')
                                ->where('direction', $this->direction);
                        })
                        ->orWhere(function (Builder $query): void {
                            $query->where('obligation_kind', 'money')
                                ->where(function (Builder $moneyQuery): void {
                                    $moneyQuery
                                        ->where(function (Builder $query): void {
                                            $query->where('current_total_balance', '>', 0)
                                                ->where('direction', $this->direction);
                                        })
                                        ->orWhere(function (Builder $query): void {
                                            $query->where('current_total_balance', '<', 0)
                                                ->where('direction', $this->direction === 'payable' ? 'receivable' : 'payable');
                                        });
                                });
                        });
                });
            });
        }

        $records = $recordsQuery->paginate(12);

        return view('livewire.records.index', ['profile' => $profile, 'records' => $records, 'profiles' => $profiles])
            ->layout('layouts.app', ['title' => 'Records']);
    }
}
