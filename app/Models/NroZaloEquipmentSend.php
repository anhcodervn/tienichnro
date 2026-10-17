<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NroZaloEquipmentSend extends Model
{
    protected $primaryKey = 'fingerprint';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['fingerprint', 'status', 'sent_at'];

    protected $attributes = ['status' => 'sending'];

    protected function casts(): array
    {
        return ['sent_at' => 'immutable_datetime'];
    }
}
