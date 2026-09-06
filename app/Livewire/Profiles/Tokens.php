<?php

namespace App\Livewire\Profiles;

use App\Actions\Promises\CreateApiToken;
use App\Actions\Promises\Data\CreateApiTokenData;
use App\Actions\Promises\RevokeApiToken;
use App\Domain\Enums\ApiTokenAbility;
use App\Models\FinancialProfile;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

final class Tokens extends Component
{
    public FinancialProfile $profile;

    public string $name = '';

    /** @var list<string> */
    public array $abilities = [ApiTokenAbility::Read->value, ApiTokenAbility::Write->value];

    public ?string $latestPlainToken = null;

    public function mount(FinancialProfile $profile): void
    {
        $this->profile = $profile;
        Gate::authorize('update', $profile);
    }

    public function createToken(CreateApiToken $createApiToken): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        $token = $createApiToken->handle($user, $this->profile, CreateApiTokenData::fromInput([
            'name' => $this->name,
            'abilities' => $this->abilities,
        ]));
        $this->latestPlainToken = (string) $token->getAttribute('plain_token');
        $this->reset('name');
    }

    public function revoke(string $tokenId, RevokeApiToken $revokeApiToken): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        $token = $this->profile->apiTokens()->whereKey($tokenId)->firstOrFail();
        $revokeApiToken->handle($user, $this->profile, $token);
        $this->latestPlainToken = null;
    }

    public function render(): View
    {
        return view('livewire.profiles.tokens', [
            'tokens' => $this->profile->apiTokens()->latest()->get(),
            'abilityOptions' => ApiTokenAbility::cases(),
        ])->layout('layouts.app', ['title' => 'API tokens']);
    }
}
