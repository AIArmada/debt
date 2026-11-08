<?php

namespace App\Features\PromiseTracker\EntryPoints\Http\Controllers\HttpApi\V1;

use App\Features\PromiseTracker\Domain\Enums\PartyStatus;
use App\Features\PromiseTracker\Models\FinancialProfile;
use App\Features\PromiseTracker\Models\Party;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PeopleController
{
    public function index(Request $request): JsonResponse
    {
        $profile = $request->attributes->get('api_profile');
        abort_unless($profile instanceof FinancialProfile, 401);

        return response()->json(['data' => $profile->parties()->where('status', PartyStatus::Active->value)->orderBy('display_name')->get()->map(static function (Party $party): array {
            return [
                'id' => $party->getKey(),
                'name' => $party->display_name,
                'kind' => $party->kind->value,
            ];
        })->values()]);
    }
}
