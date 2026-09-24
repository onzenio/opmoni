<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['name'])]
class Document extends Model
{
    /** @use HasFactory<Document> */
    use BelongsToAccount, HasFactory;

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
