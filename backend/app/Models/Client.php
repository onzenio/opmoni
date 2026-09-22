<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use App\Enums\ClientPersonType;
use App\Enums\ClientStatus;
use App\Enums\DeadlineStatus;
use App\Enums\TaxRegime;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'account_id',
    'person_type',
    'tax_id',
    'name',
    'trade_name',
    'status',
    'tax_regime',
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
    'email',
    'phone',
    'source_updated_at',
    'looked_up_at',
])]
class Client extends Model
{
    /** @use HasFactory<Client> */
    use BelongsToAccount, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'person_type' => ClientPersonType::class,
            'status' => ClientStatus::class,
            'tax_regime' => TaxRegime::class,
            'registration_status_date' => 'date',
            'opened_at' => 'date',
            'source_updated_at' => 'datetime',
            'looked_up_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function certificateHistory(): HasMany
    {
        return $this->hasMany(ClientCertificate::class)->latest('id');
    }

    public function currentCertificate(): HasOne
    {
        return $this->hasOne(ClientCertificate::class)
            ->whereNull('replaced_at')
            ->whereNull('removed_at')
            ->latestOfMany();
    }

    public function ecacPowerOfAttorney(): HasOne
    {
        return $this->hasOne(ClientEcacPowerOfAttorney::class);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when($term, function (Builder $query, string $term): Builder {
            $normalizedTaxId = preg_replace('/\D+/', '', $term);

            return $query->where(function (Builder $query) use ($term, $normalizedTaxId): Builder {
                $query
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('trade_name', 'like', "%{$term}%");

                if ($normalizedTaxId !== '') {
                    $query->orWhere('tax_id', 'like', "%{$normalizedTaxId}%");
                }

                return $query;
            });
        });
    }

    public function scopeWithStatus(Builder $query, ?string $status): Builder
    {
        return $query->when($status, fn (Builder $query, string $status): Builder => $query->where('status', $status));
    }

    public function scopeWithTaxRegime(Builder $query, ?string $regime): Builder
    {
        return $query->when($regime, fn (Builder $query, string $regime): Builder => $query->where('tax_regime', $regime));
    }

    public function scopeWithDeadlineStatus(Builder $query, ?string $status): Builder
    {
        if ($status === null) {
            return $query;
        }

        $today = now()->startOfDay();
        $limit = $today->copy()->addDays(30)->endOfDay();
        // Day-precision boundaries mirror DeadlineState (startOfDay today, +30d): whereDate/whereBetween/where(>, endOfDay) match expired/expiring/valid.
        $column = fn (Builder $relation, string $name): Builder => match ($status) {
            DeadlineStatus::Expired->value => $relation->whereDate($name, '<', $today),
            DeadlineStatus::Expiring->value => $relation->whereBetween($name, [$today, $limit]),
            DeadlineStatus::Valid->value => $relation->where($name, '>', $limit),
            default => $relation,
        };

        if ($status === DeadlineStatus::Missing->value) {
            return $query->where(fn (Builder $query): Builder => $query
                ->whereDoesntHave('currentCertificate')
                ->orWhereDoesntHave('ecacPowerOfAttorney'));
        }

        return $query->where(fn (Builder $query): Builder => $query
            ->whereHas('currentCertificate', fn (Builder $relation): Builder => $column($relation, 'valid_until'))
            ->orWhereHas('ecacPowerOfAttorney', fn (Builder $relation): Builder => $column($relation, 'expires_at')));
    }
}
