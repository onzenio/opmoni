<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use App\Enums\ClientPersonType;
use App\Enums\ClientStatus;
use App\Enums\TaxRegime;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when($term, fn (Builder $query, string $term): Builder => $query->where(
            fn (Builder $query): Builder => $query
                ->where('name', 'like', "%{$term}%")
                ->orWhere('trade_name', 'like', "%{$term}%")
                ->orWhere('tax_id', 'like', '%'.preg_replace('/\D+/', '', $term).'%')
        ));
    }

    public function scopeWithStatus(Builder $query, ?string $status): Builder
    {
        return $query->when($status, fn (Builder $query, string $status): Builder => $query->where('status', $status));
    }

    public function scopeWithTaxRegime(Builder $query, ?string $regime): Builder
    {
        return $query->when($regime, fn (Builder $query, string $regime): Builder => $query->where('tax_regime', $regime));
    }
}
