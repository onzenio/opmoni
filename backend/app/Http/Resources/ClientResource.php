<?php

namespace App\Http\Resources;

use App\Models\Client;
use App\Services\DeadlineState;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Client */
class ClientResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getKey(),
            'person_type' => $this->person_type?->value,
            'tax_id' => $this->tax_id,
            'name' => $this->name,
            'trade_name' => $this->trade_name,
            'status' => $this->status?->value,
            'tax_regime' => $this->tax_regime?->value,
            'registration_status' => $this->registration_status,
            'registration_status_date' => $this->registration_status_date?->toDateString(),
            'opened_at' => $this->opened_at?->toDateString(),
            'company_size' => $this->company_size,
            'legal_nature' => $this->legal_nature,
            'primary_activity' => [
                'code' => $this->primary_activity_code,
                'description' => $this->primary_activity_description,
            ],
            'address' => [
                'street_type' => $this->street_type,
                'street' => $this->street,
                'number' => $this->address_number,
                'complement' => $this->address_complement,
                'district' => $this->district,
                'postal_code' => $this->postal_code,
                'city' => $this->city,
                'state' => $this->state,
            ],
            'email' => $this->email,
            'phone' => $this->phone,
            'certificate' => $this->whenLoaded(
                'currentCertificate',
                fn () => $this->currentCertificate === null ? null : new ClientCertificateResource($this->currentCertificate)
            ),
            'certificate_status' => resolve(DeadlineState::class)->for($this->currentCertificate?->valid_until)->value,
            'ecac_power_of_attorney' => $this->whenLoaded(
                'ecacPowerOfAttorney',
                fn () => $this->ecacPowerOfAttorney === null ? null : new ClientEcacPowerOfAttorneyResource($this->ecacPowerOfAttorney)
            ),
            'ecac_power_of_attorney_status' => resolve(DeadlineState::class)->for($this->ecacPowerOfAttorney?->expires_at)->value,
            'source_updated_at' => $this->source_updated_at?->toISOString(),
            'looked_up_at' => $this->looked_up_at?->toISOString(),
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->map(fn ($tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
                'color' => $tag->color,
            ])->values()),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
