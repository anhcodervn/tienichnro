<?php

namespace App\Models;

use Database\Factories\GlobalTopupPackageGameSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GlobalTopupPackageGameSetting extends Model
{
    /** @use HasFactory<GlobalTopupPackageGameSettingFactory> */
    use HasFactory;

    protected $fillable = [
        'denomination',
        'game_id',
        'provider_service_code',
        'receives',
    ];

    protected function casts(): array
    {
        return ['denomination' => 'integer', 'receives' => 'array'];
    }

    public function globalTopupPackage(): BelongsTo
    {
        return $this->belongsTo(GlobalTopupPackage::class, 'denomination', 'denomination');
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
