<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SavedPaymentOption extends Model
{
    protected $fillable = ['created_by', 'is_shared', 'label', 'provider', 'account_name', 'account_number', 'instructions', 'is_enabled', 'version'];

    protected function casts(): array
    {
        return ['is_shared' => 'boolean', 'is_enabled' => 'boolean', 'version' => 'integer'];
    }

    public function scopeAvailableTo(Builder $query, User $user): void
    {
        if (! $user->isAdmin()) {
            $query->whereRaw('1 = 0');

            return;
        }
        $query->where(fn (Builder $inner) => $inner->where('is_shared', true)->orWhere('created_by', $user->id));
    }

    public function canManage(User $user): bool
    {
        return $user->isAdmin() && ($this->is_shared ? $user->isSuperAdmin() : (int) $this->created_by === (int) $user->id);
    }

    public function selectionToken(): string
    {
        return 'option:'.$this->id.':'.$this->version;
    }

    public function eventDetails(): array
    {
        return ['provider' => $this->provider, 'account_name' => $this->account_name,
            'account_number' => $this->account_number, 'instructions' => $this->instructions, 'is_enabled' => true];
    }
}
