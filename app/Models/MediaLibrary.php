<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MediaLibrary extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'uploaded_by',
        'name',
        'path',
        'original_name',
        'mime_type',
        'size',
        'type',
        'is_public',
        'alt_text',
        'width',
        'height',
        'checksum',
    ];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
