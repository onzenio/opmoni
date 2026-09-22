<?php

namespace App\Concerns;

use App\Tenant\CurrentTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Scopes every query to the current tenant account and fills
 * `account_id` on creation from the same source.
 *
 * @mixin Model
 */
trait BelongsToAccount
{
    protected static function bootBelongsToAccount(): void
    {
        static::addGlobalScope('account', fn (Builder $query) => $query->when(
            resolve(CurrentTenant::class)->accountId,
            fn ($query, $id) => $query->where($query->getModel()->getTable().'.account_id', $id)
        ));

        static::creating(fn ($model) => $model->account_id ??= resolve(CurrentTenant::class)->accountId);
    }

    /**
     * Scope implicit route-model binding to the requester's account so
     * foreign ids resolve to null (404). Bindings run before the
     * `tenant` middleware, hence the fallback to the session user.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        $tenantId = $this->resolveBindingTenantId();

        if ($tenantId === null) {
            return parent::resolveRouteBinding($value, $field);
        }

        return $this->where($this->getTable().'.account_id', $tenantId)
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->first();
    }

    private function resolveBindingTenantId(): ?int
    {
        $current = resolve(CurrentTenant::class)->accountId;

        if ($current !== null) {
            return $current;
        }

        $user = request()->user('sanctum') ?? request()->user();

        return $user?->current_account_id;
    }
}
