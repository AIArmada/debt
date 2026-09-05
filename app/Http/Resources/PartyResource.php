<?php

namespace App\Http\Resources;

use App\Models\Party;
use App\Models\PartyAddress;
use App\Models\PartyContact;
use App\Models\PartyContactRoute;
use App\Models\PartyPaymentDestination;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/** @mixin Party */
class PartyResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $profileRoles = $request->attributes->get('party_profile_roles');
        $profileId = (string) $this->profile_id;
        $canViewPrivate = is_array($profileRoles) && array_key_exists($profileId, $profileRoles)
            ? in_array($profileRoles[$profileId], ['owner', 'editor'], true)
            : ($this->relationLoaded('profile')
                && $this->profile !== null
                && Gate::forUser($request->user())->allows('manageParties', $this->profile));

        return [
            'id' => $this->getKey(),
            'profile_id' => $this->profile_id,
            'kind' => $this->kind,
            'kind_label' => $this->kindLabel(),
            'preferred_name' => $this->preferred_name,
            'legal_name' => $this->legal_name,
            'aliases' => $this->aliases,
            'status' => $this->status,
            'verification_status' => $this->verification_status,
            'contacts' => $canViewPrivate && $this->relationLoaded('contacts')
                ? $this->contacts->map(fn (PartyContact $contact): array => [
                    'id' => $contact->getKey(),
                    'type' => $contact->type,
                    'label' => $contact->label,
                    'value' => $contact->value,
                    'purpose' => $contact->purpose,
                    'is_primary' => $contact->is_primary,
                    'is_message_safe' => $contact->is_message_safe,
                ])->values()->all()
                : [],
            'addresses' => $canViewPrivate && $this->relationLoaded('addresses')
                ? $this->addresses->map(fn (PartyAddress $address): array => [
                    'id' => $address->getKey(),
                    'label' => $address->label,
                    'address_line_1' => $address->address_line_1,
                    'address_line_2' => $address->address_line_2,
                    'city' => $address->city,
                    'region' => $address->region,
                    'postal_code' => $address->postal_code,
                    'country_code' => $address->country_code,
                    'purpose' => $address->purpose,
                ])->values()->all()
                : [],
            'payment_destinations' => $canViewPrivate && $this->relationLoaded('paymentDestinations')
                ? $this->paymentDestinations->map(fn (PartyPaymentDestination $destination): array => [
                    'id' => $destination->getKey(),
                    'method' => $destination->method,
                    'label' => $destination->label,
                    'provider' => $destination->provider,
                    'currency' => $destination->currency,
                    'account_holder_name' => $destination->account_holder_name,
                    'masked_identifier' => $destination->maskedIdentifier(),
                    'reference_template' => $destination->reference_template,
                    'verification_status' => $destination->verification_status,
                ])->values()->all()
                : [],
            'contact_routes' => $canViewPrivate && $this->relationLoaded('contactRoutes')
                ? $this->contactRoutes->map(fn (PartyContactRoute $route): array => [
                    'id' => $route->getKey(),
                    'via_party_id' => $route->via_party_id,
                    'via_party_name' => $route->viaParty?->preferred_name,
                    'via_contact_id' => $route->via_contact_id,
                    'via_contact' => $route->viaContact?->value,
                    'relationship_type' => $route->relationship_type,
                    'purpose' => $route->purpose,
                    'priority' => $route->priority,
                    'is_primary' => $route->is_primary,
                    'instructions' => $route->instructions,
                ])->values()->all()
                : [],
        ];
    }
}
