<?php

namespace App\Models;

use Database\Factories\CaptchaSolveFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CaptchaSolve extends Model
{
    /** @use HasFactory<CaptchaSolveFactory> */
    use HasFactory;

    protected $fillable = ['user_id', 'request_id', 'image_hash', 'image_path', 'source', 'status', 'result', 'response', 'http_status', 'duration_ms', 'provider_name'];

    protected $attributes = ['status' => 'processing'];

    protected function casts(): array
    {
        return ['response' => 'array', 'http_status' => 'integer', 'duration_ms' => 'integer'];
    }

    /** @return HasMany<CaptchaProviderAttempt, $this> */
    public function providerAttempts(): HasMany
    {
        return $this->hasMany(CaptchaProviderAttempt::class)->orderBy('id');
    }
}
