<?php

namespace App\Features\Admin\License\Services;

use App\Features\License\Services\LicenseSessionService;
use App\Models\License;
use App\Models\LicensePlan;
use App\Models\LicenseProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class LicenseAdminService
{
    public function __construct(private readonly LicenseSessionService $sessions) {}

    public function deleteProduct(LicenseProduct $product): void
    {
        DB::transaction(function () use ($product): void {
            $product = LicenseProduct::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            if ($product->plans()->exists() || $product->licenses()->exists()) {
                throw ValidationException::withMessages(['product' => 'Sản phẩm đang có gói license hoặc key. Xóa các gói chưa sử dụng trước, hoặc tắt sản phẩm để ngừng cấp key.']);
            }
            $paths = array_filter([$product->tool_zip_path, $product->update_zip_path]);
            $product->delete();
            if ($paths !== []) {
                DB::afterCommit(fn () => rescue(fn () => Storage::disk('local')->delete($paths)));
            }
        }, 3);
    }

    public function deletePlan(LicensePlan $plan): void
    {
        DB::transaction(function () use ($plan): void {
            $plan = LicensePlan::query()->whereKey($plan->id)->lockForUpdate()->firstOrFail();
            if (License::query()->where('plan_id', $plan->id)->exists()) {
                throw ValidationException::withMessages(['plan' => 'Gói đã có license key. Tắt gói để ngừng cấp key mới.']);
            }
            $plan->delete();
        }, 3);
    }

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
                $license = License::query()->create(['product_id' => $plan->product_id, 'plan_id' => $plan->id, 'user_id' => $data['user_id'] ?? null, 'key_hash' => LicenseSessionService::hashKey($key), 'encrypted_key' => $key, 'key_prefix' => substr($key, 0, 9), 'status' => 'unused', 'duration_days' => $plan->duration_days, 'max_active_devices' => 1, 'transfer_cooldown' => $plan->transfer_cooldown]);
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
            if ($action === 'edit') {
                if (! $license->activated_at && $data['expires_at'] !== null) {
                    throw ValidationException::withMessages(['expires_at' => 'Key chưa kích hoạt chỉ được đặt thời hạn theo ngày.']);
                }
                if ($license->activated_at && $data['duration_days'] !== $license->duration_days) {
                    throw ValidationException::withMessages(['duration_days' => 'Key đã kích hoạt cần sửa ngày hết hạn.']);
                }
                if ($license->user_id !== ($data['user_id'] === null ? null : (int) $data['user_id']) || $license->expires_at?->toISOString() !== ($data['expires_at'] === null ? null : Carbon::parse($data['expires_at'])->toISOString())) {
                    $this->sessions->revokeSessions($license, $data['reason']);
                    $license->generation++;
                    $license->devices()->update(['status' => 'inactive']);
                }
                $license->fill(Arr::only($data, ['user_id', 'duration_days', 'expires_at', 'transfer_cooldown']));
                if ($license->status === 'expired' && (! $license->expires_at || $license->expires_at->isFuture())) {
                    $license->status = $license->activated_at ? 'active' : 'unused';
                }
            } elseif ($action === 'extend') {
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

    /** @return array{id:int, key:string} */
    public function rotate(License $license, string $reason, Request $request): array
    {
        return DB::transaction(function () use ($license, $reason, $request): array {
            $license = License::query()->whereKey($license->id)->lockForUpdate()->firstOrFail();
            if ($license->status === 'revoked') {
                throw ValidationException::withMessages(['action' => 'Key đã thu hồi vĩnh viễn.']);
            }
            $key = implode('-', str_split(strtoupper(bin2hex(random_bytes(16))), 4));
            $this->sessions->revokeSessions($license, $reason);
            $license->devices()->update(['status' => 'inactive']);
            $license->forceFill(['key_hash' => LicenseSessionService::hashKey($key), 'encrypted_key' => $key, 'key_prefix' => substr($key, 0, 9), 'generation' => $license->generation + 1, 'current_device_uuid' => null])->save();
            $this->sessions->event($license, 'rotate', $request, $reason);

            return ['id' => $license->id, 'key' => $key];
        }, 3);
    }

    public function delete(License $license, string $reason, Request $request): void
    {
        DB::transaction(function () use ($license, $reason, $request): void {
            $license = License::query()->whereKey($license->id)->lockForUpdate()->firstOrFail();
            $this->sessions->revokeSessions($license, $reason);
            $this->sessions->event($license, 'deleted', $request, $reason);
            $license->events()->update(['license_id' => null]);
            $license->delete();
        }, 3);
    }
}
