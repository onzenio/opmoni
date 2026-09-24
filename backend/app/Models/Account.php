<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['name', 'status', 'settings'])]
class Account extends Model
{
    /** @use HasFactory<Account> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'account_user')
            ->withPivot(['role', 'inviter_id'])
            ->withTimestamps();
    }

    public function accountUsers(): HasMany
    {
        return $this->hasMany(AccountUser::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function monitorings(): HasMany
    {
        return $this->hasMany(SerproMonitoring::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function processes(): HasMany
    {
        return $this->hasMany(Process::class);
    }
}
