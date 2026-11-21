<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResellerHierarchy extends Model
{
    protected $table = 'reseller_hierarchy';

    public $incrementing = false;

    protected $fillable = [
        'ancestor_reseller_id',
        'descendant_reseller_id',
        'depth',
    ];

    public function ancestor(): BelongsTo
    {
        return $this->belongsTo(Reseller::class, 'ancestor_reseller_id');
    }

    public function descendant(): BelongsTo
    {
        return $this->belongsTo(Reseller::class, 'descendant_reseller_id');
    }
}
