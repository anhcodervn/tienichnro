<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NroEventReceipt extends Model
{
    protected $fillable = ['server_id', 'event_key', 'payload_hash', 'notify_id', 'status', 'content', 'occurred_at'];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected function casts(): array
    {
        return ['occurred_at' => 'immutable_datetime'];
    }
}
