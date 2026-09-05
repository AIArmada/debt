<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Money\Currency;
use App\Http\Controllers\Controller;
use App\Http\Resources\PartyResource;
use App\Models\FinancialProfile;
use App\Models\Party;
use App\Models\PartyContact;
use App\Models\PartyContactRoute;
use App\Models\PartyPaymentDestination;
use App\Services\ProfileAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PartyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $profileIds = app(ProfileAccess::class)->accessibleProfiles($request->user())->pluck('id');
        $parties = Party::query()
            ->whereIn('profile_id', $profileIds)
            ->whereNull('archived_at')
            ->with(['profile', 'contacts', 'addresses', 'paymentDestinations', 'contactRoutes.viaParty', 'contactRoutes.viaContact'])
            ->orderBy('preferred_name')
            ->get();

        return response()->json(['data' => PartyResource::collection($parties)]);
    }

    public function store(Request $request): PartyResource
    {
        $validated = $request->validate([
            'profile_id' => ['required', 'uuid'],
            'preferred_name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'kind' => ['required', Rule::in(['individual', 'organization', 'group', 'estate_or_trust', 'unidentified'])],
            'contacts' => ['nullable', 'array'],
            'contacts.*.type' => ['required_with:contacts', Rule::in(['email', 'phone', 'whatsapp', 'telegram', 'website', 'other'])],
            'contacts.*.value' => ['required_with:contacts', 'string', 'max:255'],
        ]);
        $profile = FinancialProfile::query()->whereKey($validated['profile_id'])->firstOrFail();
        Gate::authorize('manageParties', $profile);
        $party = $profile->parties()->create([
            'created_by_user_id' => $request->user()->getKey(),
            'preferred_name' => trim($validated['preferred_name']),
            'legal_name' => filled($validated['legal_name'] ?? null) ? trim($validated['legal_name']) : null,
            'kind' => $validated['kind'],
            'status' => 'active',
            'verification_status' => 'unverified',
            'source' => 'api',
        ]);
        foreach ($validated['contacts'] ?? [] as $contact) {
            $party->contacts()->create([
                'created_by_user_id' => $request->user()->getKey(),
                'type' => $contact['type'],
                'label' => $contact['label'] ?? null,
                'value' => trim($contact['value']),
                'purpose' => $contact['purpose'] ?? 'communication',
                'is_primary' => (bool) ($contact['is_primary'] ?? false),
                'is_message_safe' => in_array($contact['type'], ['email', 'phone', 'whatsapp', 'telegram'], true),
                'visibility' => 'restricted',
            ]);
        }

        return new PartyResource($party->load(['profile', 'contacts', 'addresses', 'paymentDestinations', 'contactRoutes.viaParty', 'contactRoutes.viaContact']));
    }

    public function show(Party $party): PartyResource
    {
        Gate::authorize('view', $party->profile);

        return new PartyResource($party->load(['profile', 'contacts', 'addresses', 'paymentDestinations', 'contactRoutes.viaParty', 'contactRoutes.viaContact']));
    }

    public function update(Request $request, Party $party): PartyResource
    {
        Gate::authorize('manageParties', $party->profile);
        $validated = $request->validate([
            'preferred_name' => ['sometimes', 'required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'kind' => ['sometimes', Rule::in(['individual', 'organization', 'group', 'estate_or_trust', 'unidentified'])],
        ]);
        $party->forceFill($validated)->save();

        return new PartyResource($party->refresh()->load(['profile', 'contacts', 'addresses', 'paymentDestinations', 'contactRoutes.viaParty', 'contactRoutes.viaContact']));
    }

    public function addContactRoute(Request $request, Party $party): JsonResponse
    {
        Gate::authorize('manageParties', $party->profile);
        $validated = $request->validate([
            'via_party_id' => ['required', 'uuid', 'different:party_id'],
            'via_contact_id' => ['nullable', 'uuid'],
            'relationship_type' => ['required', Rule::in(['personal_assistant', 'relative', 'representative', 'friend', 'colleague', 'legal_contact', 'emergency_contact', 'other'])],
            'purpose' => ['required', Rule::in(['general', 'payment', 'delivery', 'legal', 'emergency'])],
            'priority' => ['required', 'integer', 'min:1', 'max:99'],
            'is_primary' => ['boolean'],
            'instructions' => ['nullable', 'string', 'max:2000'],
        ]);
        $viaParty = Party::query()->whereKey($validated['via_party_id'])->where('profile_id', $party->profile_id)->whereNull('archived_at')->firstOrFail();
        abort_if($viaParty->is($party), 422, 'An intermediary must be a different party from the subject.');
        $viaContact = null;
        if ($validated['via_contact_id'] !== null) {
            $viaContact = PartyContact::query()->whereKey($validated['via_contact_id'])->where('party_id', $viaParty->getKey())->firstOrFail();
        }
        if ((bool) ($validated['is_primary'] ?? false)) {
            PartyContactRoute::query()->where('party_id', $party->getKey())->where('purpose', $validated['purpose'])->update(['is_primary' => false]);
        }
        $route = PartyContactRoute::query()->updateOrCreate(
            ['party_id' => $party->getKey(), 'via_party_id' => $viaParty->getKey(), 'purpose' => $validated['purpose']],
            ['via_contact_id' => $viaContact?->getKey(), 'created_by_user_id' => $request->user()->getKey(), 'relationship_type' => $validated['relationship_type'], 'priority' => $validated['priority'], 'is_primary' => (bool) ($validated['is_primary'] ?? false), 'status' => 'active', 'instructions' => $validated['instructions'] ?? null, 'visibility' => 'restricted'],
        );

        return response()->json(['data' => ['id' => $route->getKey(), 'party_id' => $route->party_id, 'via_party_id' => $route->via_party_id, 'via_contact_id' => $route->via_contact_id, 'relationship_type' => $route->relationship_type, 'purpose' => $route->purpose, 'priority' => $route->priority, 'is_primary' => $route->is_primary]], 201);
    }

    public function addPaymentDestination(Request $request, Party $party): JsonResponse
    {
        Gate::authorize('manageParties', $party->profile);
        $validated = $request->validate([
            'method' => ['required', Rule::in(['bank_account', 'cash', 'e_wallet', 'payment_provider', 'crypto_wallet', 'other'])],
            'label' => ['required', 'string', 'max:120'],
            'provider' => ['nullable', 'string', 'max:100'],
            'currency' => ['nullable', Rule::in(Currency::codes())],
            'account_holder_name' => ['nullable', 'string', 'max:255'],
            'account_identifier' => ['required_unless:method,cash', 'nullable', 'string', 'max:255'],
            'reference_template' => ['nullable', 'string', 'max:255'],
        ]);
        $identifier = trim((string) ($validated['account_identifier'] ?? ''));
        $destination = PartyPaymentDestination::create([
            'party_id' => $party->getKey(),
            'created_by_user_id' => $request->user()->getKey(),
            'method' => $validated['method'],
            'label' => trim($validated['label']),
            'provider' => $validated['provider'] ?? null,
            'currency' => $validated['currency'] ?? null,
            'account_holder_name' => $validated['account_holder_name'] ?? null,
            'account_identifier_encrypted' => $identifier !== '' ? $identifier : null,
            'account_identifier_last4' => $identifier !== '' ? substr($identifier, -4) : null,
            'reference_template' => $validated['reference_template'] ?? null,
            'verification_status' => 'unverified',
            'status' => 'active',
        ]);

        return response()->json(['data' => ['id' => $destination->getKey(), 'party_id' => $destination->party_id, 'method' => $destination->method, 'label' => $destination->label, 'currency' => $destination->currency, 'masked_identifier' => $destination->maskedIdentifier(), 'verification_status' => $destination->verification_status]], 201);
    }
}
