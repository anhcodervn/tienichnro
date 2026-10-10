<?php

namespace App\Features\Admin\License\Services;

use App\Features\License\Services\LicenseSessionService;
use App\Models\License;
use App\Models\LicensePlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LicenseAdminService
{
    public function __construct(private readonly LicenseSessionService $sessions) {}

    /** @param array<string, mixed> $data
     * @return list<array{id:int, key:string}> */
    public function issue(array $data, Request $request): array
    {
        return DB::transaction(function () use ($data, $request): array {
            $plan = LicensePlan::query()->with('product')->whereKey($data['plan_id'])->lockForUpdate()->firstOrFail();
            if (! $plan->is_active || ! $plan->product->is_active) {
                throw ValidationException::withMessages(['plan_id' => 'Sản phẩm hoặc gói đã tắt.']);
            }
            $result = [];
            for ($i = 0; $i < $data['quantity']; $i++) {
                $key = implode('-', str_split(strtoupper(bin2hex(random_bytes(16))), 4));
                $license = License::query()->create(['product_id' => $plan->product_id, 'plan_id' => $plan->id, 'user_id' => $data['user_id'] ?? null, 'key_hash' => LicenseSessionService::hashKey($key), 'key_prefix' => substr($key, 0, 9), 'status' => 'unused', 'duration_days' => $plan->duration_days, 'max_active_devices' => 1, 'transfer_cooldown' => $plan->transfer_cooldown]);
                $this->sessions->event($license, 'issued', $request);
                $result[] = ['id' => $license->id, 'key' => $key];
            }

            return $result;
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function manage(License $license, array $data, Request $request): License
    {
        return DB::transaction(function () use ($license, $data, $request): License {
            $license = License::query()->whereKey($license->id)->lockForUpdate()->firstOrFail();
            $action = $data['action'];
            if ($license->status === 'revoked') {
                throw ValidationException::withMessages(['action' => 'Key đã thu hồi vĩnh viễn.']);
            }
            if ($action === 'extend') {
                if (! $license->activated_at) {
                    $license->duration_days = $license->duration_days === null ? null : $license->duration_days + $data['days'];
                } elseif ($license->expires_at) {
                    $license->expires_at = ($license->expires_at->isFuture() ? $license->expires_at : now())->copy()->addDays($data['days']);
                }
                if ($license->status === 'expired') {
                    $license->status = $license->activated_at ? 'active' : 'unused';
                }
            } elseif ($action === 'resume') {
                if ($license->expires_at?->lte(now())) {
                    throw ValidationException::withMessages(['action' => 'Gia hạn key trước khi mở khóa.']);
                }
                $license->status = $license->activated_at ? 'active' : 'unused';
            } else {
                $this->sessions->revokeSessions($license, $data['reason']);
                $license->generation++;
                $license->devices()->update(['status' => 'inactive']);
                if ($action === 'suspend') {
                    $license->status = 'suspended';
                }
                if ($action === 'revoke') {
                    $license->status = 'revoked';
                }
                if ($action === 'reset-device') {
                    $license->current_device_uuid = null;
                    $license->last_transfer_at = now();
                }
                if ($action === 'transfer') {
                    if (! $license->devices()->where('device_uuid', $data['device_uuid'])->exists()) {
                        throw ValidationException::withMessages(['device_uuid' => 'Thiết bị đích chưa được xác minh.']);
                    }
                    $license->current_device_uuid = $data['device_uuid'];
                    $license->last_transfer_at = now();
                }
            }
            $license->save();
            $this->sessions->event($license, $action, $request, $data['reason']);

            return $license;
        }, 3);
    }
}
