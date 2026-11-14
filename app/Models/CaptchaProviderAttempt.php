<?php

namespace App\Models;

use Database\Factories\CaptchaProviderAttemptFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CaptchaProviderAttempt extends Model
{
    /** @use HasFactory<CaptchaProviderAttemptFactory> */
    use HasFactory;

    protected $fillable = ['captcha_solve_id', 'captcha_provider_id', 'provider_name', 'driver', 'status', 'http_status', 'duration_ms', 'response'];

    protected $attributes = ['status' => 'processing'];

    protected function casts(): array
    {
        return ['response' => 'array', 'duration_ms' => 'integer', 'http_status' => 'integer'];
    }
}
