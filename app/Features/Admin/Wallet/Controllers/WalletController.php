<?php

namespace App\Features\Admin\Wallet\Controllers;

use App\Features\Admin\Wallet\Requests\AdjustWalletRequest;
use App\Features\Admin\Wallet\Requests\LedgerHistoryRequest;
use App\Features\Client\Wallet\Services\WalletService;
use App\Http\Controllers\Controller;
use App\Models\CaptchaTransaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class WalletController extends Controller
{
    public function history(LedgerHistoryRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $isToken = $filters['kind'] === 'token';
        $table = $isToken ? 'captcha_transactions' : 'wallet_transactions';
        $query = $isToken ? CaptchaTransaction::query() : WalletTransaction::query();
        if (! $isToken) {
            $query->join('wallets', 'wallets.id', '=', $table.'.wallet_id');
        }
        $userColumn = $isToken ? $table.'.user_id' : 'wallets.user_id';
        $query->leftJoin('users', 'users.id', '=', $userColumn)
            ->leftJoin('users as actors', 'actors.id', '=', $table.'.actor_id')
            ->select([
                $table.'.id', $userColumn.' as user_id', 'users.username', 'actors.username as actor_username',
                $table.'.direction', $table.'.amount', $table.'.balance_before', $table.'.balance_after',
                $table.'.description', $table.'.created_at',
            ]);
        if (! empty($filters['q'])) {
            $term = '%'.$filters['q'].'%';
            $query->where(function (Builder $query) use ($term, $table): void {
                $query->where('users.username', 'like', $term)->orWhere($table.'.description', 'like', $term);
            });
        }
        if (! empty($filters['direction'])) {
            $query->where($table.'.direction', $filters['direction']);
        }
        if (! empty($filters['from'])) {
            $query->where($table.'.created_at', '>=', $filters['from'].' 00:00:00');
        }
        if (! empty($filters['to'])) {
            $query->where($table.'.created_at', '<', Carbon::parse($filters['to'])->addDay()->startOfDay());
        }

        return response()->json($query->orderByDesc($table.'.id')->paginate(20))->header('Cache-Control', 'no-store, private');
    }

    public function index(): JsonResponse
    {
        return response()->json(['data' => Wallet::query()->join('users', 'users.id', '=', 'wallets.user_id')
            ->whereNull('users.deleted_at')->select(['wallets.*', 'users.username', 'users.full_name'])->latest('wallets.id')->get()]);
    }

    public function transactions(User $user, WalletService $service): JsonResponse
    {
        $wallet = $service->wallet($user);

        return response()->json(WalletTransaction::query()->where('wallet_id', $wallet->id)->latest('id')->paginate(20));
    }

    public function adjust(AdjustWalletRequest $request, User $user, WalletService $service): JsonResponse
    {
        $values = $request->validated();
        $transaction = $service->apply($user, $values['amount'], $values['direction'], 'admin:'.$values['request_id'], $values['description'], $request->user()->id);

        return response()->json(['data' => $transaction, 'balance' => $service->wallet($user)->balance]);
    }
}
