<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteTheme extends Model
{
    protected $fillable = [
        'tenant_id',
        'name',
        'template',
        'color_primary',
        'color_secondary',
        'color_accent',
        'logo_path',
        'logo_dark_path',
        'favicon_path',
        'font_family',
        'font_pair',
        'radius_preset',
        'shadow_preset',
        'banner_image',
        'banner_text',
        'custom_css',
        'published_config',
        'draft_config',
        'published_at',
    ];

    protected $casts = [
        'published_config' => 'array',
        'draft_config' => 'array',
        'published_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function hasStagedChanges(): bool
    {
        return $this->draft_config !== null && $this->draft_config !== [];
    }
}
