<?php

namespace App\Models;

use Database\Factories\WalletRechargeOrderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletRechargeOrder extends Model
{
    /** @use HasFactory<WalletRechargeOrderFactory> */
    use HasFactory;

    public const STATUS_LABELS = ['creating' => 'Đang tạo lệnh', 'pending' => 'Chờ chuyển khoản', 'processing' => 'Đang kiểm tra', 'paid' => 'Đã cộng tiền', 'expired' => 'Hết hạn', 'failed' => 'Không thành công', 'cancelled' => 'Đã huỷ', 'creation_failed' => 'Chưa tạo được lệnh'];

    protected $fillable = ['user_id', 'request_id', 'code', 'bank_id', 'amount'];

    protected $hidden = ['api_key', 'api_secret'];

    protected function casts(): array
    {
        return ['user_id' => 'integer', 'bank_id' => 'integer', 'amount' => 'integer', 'api_key' => 'encrypted', 'api_secret' => 'encrypted', 'expires_at' => 'immutable_datetime', 'last_checked_at' => 'immutable_datetime', 'paid_at' => 'immutable_datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function paymentQrUrl(): ?string
    {
        $bank = $this->bank_code ?: $this->bank_name;
        if (! in_array($this->status, ['pending', 'processing'], true) || ! $this->expires_at?->isFuture()
            || ! preg_match('/\A[A-Za-z0-9]{1,40}\z/', (string) $bank)
            || ! preg_match('/\A[A-Za-z0-9]{1,19}\z/', (string) $this->account_number)) {
            return null;
        }

        return 'https://img.vietqr.io/image/'.rawurlencode($bank).'-'.rawurlencode($this->account_number).'-compact2.png?'.http_build_query([
            'amount' => $this->amount, 'addInfo' => $this->transfer_content, 'accountName' => $this->account_name,
        ], '', '&', PHP_QUERY_RFC3986);
    }
}
