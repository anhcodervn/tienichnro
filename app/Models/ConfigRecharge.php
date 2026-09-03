<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfigRecharge extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'config_recharge';

    protected $fillable = [
        'tenant_id',
        'provider',
        'bank_name',
        'account_name',
        'account_number',
        'qr_template',
        'transfer_prefix',
        'api_base_url',
        'api_key',
        'api_secret',
        'webhook_secret',
        'api_bank_id',
        'is_active',
    ];

    protected $hidden = [
        'api_key',
        'api_secret',
        'webhook_secret',
    ];

    protected function casts(): array
    {
        return [
            'provider' => 'string',
            'api_base_url' => 'string',
            'api_key' => 'string',
            'api_secret' => 'encrypted',
            'webhook_secret' => 'encrypted',
            'api_bank_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
