<?php

namespace App\Models;

use Database\Factories\OrderRecipientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderRecipient extends Model
{
    /** @use HasFactory<OrderRecipientFactory> */
    use HasFactory;

    protected $hidden = [
        'provider_request_id',
        'provider_reference',
        'provider_status',
        'provider_response',
    ];

    protected $fillable = [
        'order_id', 'position', 'recipient_data', 'quantity', 'status', 'provider_request_id',
        'provider_reference', 'provider_status', 'provider_response', 'status_check_attempts',
        'failure_reason', 'submitted_at', 'last_checked_at', 'completed_at', 'failed_at',
    ];

    protected $attributes = ['quantity' => 1, 'status' => 'pending'];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'recipient_data' => 'array',
            'quantity' => 'integer',
            'provider_response' => 'array',
            'status_check_attempts' => 'integer',
            'submitted_at' => 'datetime',
            'last_checked_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
