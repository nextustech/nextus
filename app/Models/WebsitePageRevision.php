<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsitePageRevision extends Model
{
    protected $fillable = [
        'tenant_id',
        'website_page_id',
        'revision',
        'title',
        'sections',
        'seo',
        'created_by',
    ];

    protected $casts = [
        'sections' => 'array',
        'seo' => 'array',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(WebsitePage::class, 'website_page_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
