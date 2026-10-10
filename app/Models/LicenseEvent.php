<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LicenseEvent extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'license_events';

    protected $fillable = ['license_id', 'actor_id', 'event', 'reason', 'device_uuid', 'ip', 'created_at'];

    protected $hidden = [];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class, 'license_id');
    }
}
