<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Reseller extends Model
{
    use BelongsToTenant;

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    protected $fillable = [
        'public_id',
        'tenant_id',
        'type',
        'parent_reseller_id',
        'name',
        'legal_name',
        'owner_name',
        'gstin',
        'email',
        'phone',
        'pan',
        'business_address',
        'city',
        'invite_note',
        'bank_proof_path',
        'invited_by',
        'invited_at',
        'activated_at',
        'gstin_unregistered_declaration',
        'email_verified',
        'phone_verified',
        'aup_accepted_at',
        'dpdp_accepted_at',
        'kyc_status',
        'review_status',
        'kyc_submitted_at',
        'review_sla_due_at',
        'reviewed_at',
        'reviewer_id',
        'review_notes',
        'path',
        'child_count',
        'effective_status',
    ];

    protected function casts(): array
    {
        return [
            'gstin_unregistered_declaration' => 'boolean',
            'email_verified' => 'boolean',
            'phone_verified' => 'boolean',
            'aup_accepted_at' => 'datetime',
            'dpdp_accepted_at' => 'datetime',
            'kyc_submitted_at' => 'datetime',
            'review_sla_due_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'invited_at' => 'datetime',
            'activated_at' => 'datetime',
        ];
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Reseller::class, 'parent_reseller_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function ancestors(): BelongsToMany
    {
        return $this->belongsToMany(
            Reseller::class,
            'reseller_hierarchy',
            'descendant_reseller_id',
            'ancestor_reseller_id',
        )->withPivot('depth');
    }

    public function descendants(): BelongsToMany
    {
        return $this->belongsToMany(
            Reseller::class,
            'reseller_hierarchy',
            'ancestor_reseller_id',
            'descendant_reseller_id',
        )->withPivot('depth');
    }
}
