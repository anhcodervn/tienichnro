<?php

namespace App\Features\Admin\Recharge\Controllers;

use App\Features\Admin\Recharge\Requests\UpdateRechargeConfigurationRequest;
use App\Features\Recharge\Services\RechargeConfigurationService;
use App\Features\Recharge\Services\RechargePollService;
use App\Http\Controllers\Controller;
use App\Models\WalletRechargeOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RechargeController extends Controller
{
    public function configuration(Request $request, RechargeConfigurationService $config): JsonResponse
    {
        $this->ensureAdmin($request);

        return response()->json(['data' => $config->snapshot()]);
    }

    public function configure(UpdateRechargeConfigurationRequest $request, RechargeConfigurationService $config): JsonResponse
    {
        return response()->json(['data' => $config->save($request->validated())]);
    }

    public function banks(UpdateRechargeConfigurationRequest $request, RechargeConfigurationService $config): JsonResponse
    {
        return response()->json(['data' => $config->banks($request->validated())]);
    }

    public function history(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'string', 'in:'.implode(',', array_keys(WalletRechargeOrder::STATUS_LABELS))], 'page' => ['nullable', 'integer', 'min:1']]);
        $orders = WalletRechargeOrder::query()->with('user:id,username')->select(['id', 'user_id', 'code', 'provider_code', 'bank_name', 'account_number', 'amount', 'status', 'last_error', 'created_at', 'paid_at', 'last_checked_at'])
            ->when(filled($filters['status'] ?? null), fn ($query) => $query->where('status', $filters['status']))
            ->when(filled($filters['q'] ?? null), fn ($query) => $query->where(fn ($query) => $query->where('code', 'like', '%'.$filters['q'].'%')->orWhereHas('user', fn ($user) => $user->where('username', 'like', '%'.$filters['q'].'%'))))
            ->latest('id')->paginate(20);

        return response()->json($orders);
    }

    public function check(Request $request, RechargePollService $poll): JsonResponse
    {
        $this->ensureAdmin($request);

        return response()->json(['data' => $poll->run()]);
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless($request->user()?->role === 'admin' && $request->user()?->status === 'active', 403);
    }
}
