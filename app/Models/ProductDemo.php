<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductDemo extends Model
{
    use \Illuminate\Database\Eloquent\SoftDeletes;

    protected $fillable = [
        'product_id', 'title', 'access_type', 'live_url',
        'credentials_username', 'credentials_encrypted', 'video_url',
        'video_path', 'access_grant_hours', 'status',
        'environment_created_at', 'credentials_rotated_at', 'schedule_windows', 'position',
    ];

    protected function casts(): array
    {
        return [
            'credentials_encrypted' => 'encrypted',
            'schedule_windows' => 'array',
            'environment_created_at' => 'datetime',
            'credentials_rotated_at' => 'datetime',
        ];
    }
}
