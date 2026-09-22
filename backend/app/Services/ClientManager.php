<?php

namespace App\Services;

use App\Enums\ClientPersonType;
use App\Enums\TaxRegime;
use App\Models\Account;
use App\Models\Client;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClientManager
{
    /**
     * @var list<string>
     */
    private const OFFICIAL_FIELDS = [
        'name',
        'trade_name',
        'registration_status',
        'registration_status_date',
        'opened_at',
        'company_size',
        'legal_nature',
        'primary_activity_code',
        'primary_activity_description',
        'street_type',
        'street',
        'address_number',
        'address_complement',
        'district',
        'postal_code',
        'city',
        'state',
        'phone',
        'email',
    ];

    public function __construct(private CnpjWsLookup $lookup) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Account $account, array $data): Client
    {
        PlanLimits::assertCanCreate($account, 'clients');

        return DB::transaction(function () use ($account, $data): Client {
            $existing = Client::withoutGlobalScopes()
                ->withTrashed()
                ->where('account_id', $account->getKey())
                ->where('tax_id', $data['tax_id'])
                ->lockForUpdate()
                ->first();

            $attributes = $data['person_type'] === ClientPersonType::Company->value
                ? $this->companyAttributes($data)
                : $this->individualAttributes($data);

            if ($existing !== null && ! $existing->trashed()) {
                throw ValidationException::withMessages(['tax_id' => 'Este CPF/CNPJ já está cadastrado nesta carteira.']);
            }

            if ($existing !== null) {
                $existing->restore();
                $existing->fill($attributes)->save();

                return $existing->refresh();
            }

            return $account->clients()->create($attributes);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Client $client, array $data): Client
    {
        $allowed = $client->person_type === ClientPersonType::Company
            ? ['status', 'tax_regime', 'email', 'phone']
            : ['name', 'status', 'email', 'phone', 'street_type', 'street', 'address_number',
                'address_complement', 'district', 'postal_code', 'city', 'state'];

        $client->fill(Arr::only($data, $allowed))->save();

        return $client->refresh();
    }

    public function delete(Client $client): void
    {
        $client->delete();
    }

    /**
     * @return array{current: array<string, mixed>, incoming: array<string, mixed>, changes: array<string, array{from: mixed, to: mixed}>}
     */
    public function previewCompany(Client $client): array
    {
        $this->assertCompany($client);

        $incoming = $this->officialAttributes($this->lookup->lookup($client->tax_id));
        $current = $this->officialAttributes($client->attributesToArray());

        return [
            'current' => $current,
            'incoming' => $incoming,
            'changes' => $this->diff($current, $incoming),
        ];
    }

    public function refreshCompany(Client $client): Client
    {
        $this->assertCompany($client);

        $payload = $this->lookup->lookup($client->tax_id);

        return DB::transaction(function () use ($client, $payload): Client {
            $client->fill($this->officialAttributes($payload));
            $client->forceFill([
                'tax_regime' => $this->resolveCompanyRegime($payload, $client->tax_regime?->value),
                'source_updated_at' => $payload['source_updated_at'],
                'looked_up_at' => $payload['looked_up_at'],
            ])->save();

            return $client->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function companyAttributes(array $data): array
    {
        $payload = $this->lookup->lookup($data['tax_id']);

        $attributes = $this->officialAttributes($payload);

        if (is_string($data['email'] ?? null) && $data['email'] !== '') {
            $attributes['email'] = $data['email'];
        }

        if (is_string($data['phone'] ?? null) && $data['phone'] !== '') {
            $attributes['phone'] = $data['phone'];
        }

        return array_merge($attributes, [
            'person_type' => ClientPersonType::Company->value,
            'tax_id' => $payload['tax_id'],
            'status' => $data['status'],
            'tax_regime' => $this->resolveCompanyRegime($payload, $data['tax_regime'] ?? null),
            'source_updated_at' => $payload['source_updated_at'],
            'looked_up_at' => $payload['looked_up_at'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function individualAttributes(array $data): array
    {
        return [
            'person_type' => ClientPersonType::Individual->value,
            'tax_id' => $data['tax_id'],
            'name' => $data['name'],
            'trade_name' => null,
            'status' => $data['status'],
            'tax_regime' => TaxRegime::NotApplicable->value,
            'street_type' => $data['street_type'] ?? null,
            'street' => $data['street'] ?? null,
            'address_number' => $data['address_number'] ?? null,
            'address_complement' => $data['address_complement'] ?? null,
            'district' => $data['district'] ?? null,
            'postal_code' => $data['postal_code'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function officialAttributes(array $payload): array
    {
        $official = Arr::only($payload, self::OFFICIAL_FIELDS);

        foreach (['registration_status_date', 'opened_at'] as $field) {
            if (isset($official[$field]) && $official[$field] !== null && $official[$field] !== '') {
                $official[$field] = substr((string) $official[$field], 0, 10);
            } else {
                $official[$field] = $official[$field] ?? null;
            }
        }

        return $official;
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $incoming
     * @return array<string, array{from: mixed, to: mixed}>
     */
    private function diff(array $current, array $incoming): array
    {
        $changes = [];

        foreach ($incoming as $field => $to) {
            $from = $current[$field] ?? null;

            if ((string) ($from ?? '') !== (string) ($to ?? '')) {
                $changes[$field] = ['from' => $from, 'to' => $to];
            }
        }

        return $changes;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveCompanyRegime(array $payload, ?string $requested): string
    {
        if (($payload['mei'] ?? false) === true) {
            return TaxRegime::Mei->value;
        }

        if (($payload['simple_national'] ?? false) === true) {
            return TaxRegime::SimpleNational->value;
        }

        if (in_array($requested, [TaxRegime::PresumedProfit->value, TaxRegime::ActualProfit->value, TaxRegime::Other->value], true)) {
            return $requested;
        }

        throw ValidationException::withMessages(['tax_regime' => 'Regime tributário incompatível com os dados da Receita.']);
    }

    private function assertCompany(Client $client): void
    {
        if ($client->person_type !== ClientPersonType::Company) {
            throw ValidationException::withMessages(['tax_id' => 'A atualização via CNPJ está disponível apenas para empresas.']);
        }
    }
}
