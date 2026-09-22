<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use Database\Factories\ClientCertificateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['account_id', 'client_id', 'subject', 'serial_number', 'valid_from', 'valid_until', 'original_filename', 'storage_path', 'sha256', 'replaced_at', 'removed_at'])]
class ClientCertificate extends Model
{
    /** @use HasFactory<ClientCertificateFactory> */
    use BelongsToAccount, HasFactory;

    protected function casts(): array
    {
        return [
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
            'replaced_at' => 'datetime',
            'removed_at' => 'datetime',
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
