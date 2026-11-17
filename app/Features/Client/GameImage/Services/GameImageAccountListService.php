<?php

namespace App\Features\Client\GameImage\Services;

use App\Models\GameImageAccount;
use App\Models\GameImageCaptureLog;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;

class GameImageAccountListService
{
    /** @param array<string, mixed> $filters */
    public function paginate(User $user, array $filters): LengthAwarePaginator
    {
        $accountTable = (new GameImageAccount)->getTable();
        $log = new GameImageCaptureLog;

        return GameImageAccount::query()->where('user_id', $user->id)
            ->select(['id', 'username', 'cname', 'layout->document->cname as layout_cname', 'server', 'status', 'capture_version', 'error_code', 'error_message', 'charged_amount', 'refunded_at', 'preview_path', 'capture_directory', 'created_at', 'updated_at'])
            ->with(['captureLogs' => fn (HasMany $logs) => $logs
                ->select(['id', 'account_id', 'capture_version', 'message', 'occurred_at', 'created_at'])
                ->whereExists(GameImageAccount::query()->select('id')
                    ->whereColumn($accountTable.'.id', $log->qualifyColumn('account_id'))
                    ->whereColumn($accountTable.'.capture_version', $log->qualifyColumn('capture_version')))
                ->latest('id')->limit(100)])
            ->when(filled($filters['q'] ?? null), fn ($query) => $query->where(function ($query) use ($filters): void {
                $query->where('username', 'like', '%'.$filters['q'].'%')->orWhere('server', 'like', '%'.$filters['q'].'%');
            }))
            ->when(filled($filters['status'] ?? null), fn ($query) => $query->where('status', $filters['status']))
            ->latest('id')->paginate(20, ['*'], 'page', (int) ($filters['page'] ?? 1))
            ->withPath(route('tools.game-image'))->appends(Arr::except($filters, ['page']));
    }
}
