<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use Database\Factories\ClientEcacPowerOfAttorneyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['account_id', 'client_id', 'starts_at', 'expires_at', 'notes'])]
class ClientEcacPowerOfAttorney extends Model
{
    /** @use HasFactory<ClientEcacPowerOfAttorneyFactory> */
    use BelongsToAccount, HasFactory;

    protected $table = 'client_ecac_powers_of_attorney';

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'expires_at' => 'date',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
