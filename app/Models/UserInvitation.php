<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserInvitation extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'type',
        'tenant_id',
        'sub_reseller_id',
        'email',
        'roles',
        'department_grants',
        'invited_by',
        'token_hash',
        'status',
        'expires_at',
        'accepted_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'roles' => 'array',
            'department_grants' => 'array',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function subReseller(): BelongsTo
    {
        return $this->belongsTo(Reseller::class, 'sub_reseller_id');
    }

    public function isSubResellerInvitation(): bool
    {
        return $this->type === 'sub_reseller';
    }
}
