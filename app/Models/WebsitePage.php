<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WebsitePage extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'theme_id',
        'system_key',
        'title',
        'slug',
        'page_type',
        'status',
        'parent_id',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'content',
        'sections',
        'seo',
        'published_revision_id',
        'published_slug',
        'published_at',
        'created_by',
        'updated_by',
        'position',
    ];

    protected $casts = [
        'sections' => 'array',
        'seo' => 'array',
        'published_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function theme(): BelongsTo
    {
        return $this->belongsTo(WebsiteTheme::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->where('status', 'published')->orderBy('position');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(WebsitePageRevision::class, 'website_page_id')->latest('revision');
    }

    public function publishedRevision(): BelongsTo
    {
        return $this->belongsTo(WebsitePageRevision::class, 'published_revision_id');
    }

    /** Singleton system pages: legal (privacy/terms) and the 404 page (10 §5). */
    public function isSystemPage(): bool
    {
        return $this->system_key !== null || in_array($this->page_type, ['legal', 'not_found'], true);
    }

    public function isLegal(): bool
    {
        return $this->page_type === 'legal';
    }

    /** Visitor-facing content: the last published revision, never the draft row. */
    public function publicSections(): ?array
    {
        return $this->publishedRevision?->sections;
    }

    public function hasUnpublishedChanges(): bool
    {
        return $this->status === 'draft' && $this->published_revision_id !== null;
    }
}
