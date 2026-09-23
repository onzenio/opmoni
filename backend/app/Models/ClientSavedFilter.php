<?php

namespace App\Models;

use App\Concerns\BelongsToAccount;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['account_id', 'user_id', 'name', 'q', 'filters'])]
class ClientSavedFilter extends Model
{
    use BelongsToAccount;

    protected static function booted(): void
    {
        static::addGlobalScope('owner', function (Builder $query): void {
            $userId = self::actingUserId();

            if ($userId === null) {
                return;
            }

            $query->where($query->getModel()->getTable().'.user_id', $userId);
        });

        static::creating(function (ClientSavedFilter $filter): void {
            $filter->user_id ??= self::actingUserId();
        });
    }

    private static function actingUserId(): ?int
    {
        $user = request()->user('sanctum') ?? request()->user();

        return $user?->getKey();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'filters' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
