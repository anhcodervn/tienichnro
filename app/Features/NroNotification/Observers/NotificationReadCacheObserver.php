<?php

namespace App\Features\NroNotification\Observers;

use App\Features\NroNotification\Services\NotificationReadCacheService;
use App\Models\Notify;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Model;

class NotificationReadCacheObserver
{
    public function __construct(private readonly NotificationReadCacheService $cache) {}

    public function saved(Model $model): void
    {
        $this->invalidateAfterCommit($model);
    }

    public function deleted(Model $model): void
    {
        $this->invalidateAfterCommit($model);
    }

    public function restored(Model $model): void
    {
        $this->invalidateAfterCommit($model);
    }

    private function invalidateAfterCommit(Model $model): void
    {
        if ($model instanceof Setting) {
            if ($model->key !== 'service.definition.game_notifications' && ! str_starts_with($model->key, 'tool_game_notifications_')) {
                return;
            }
            $scopes = ['availability'];
        } else {
            $scopes = $model instanceof Notify ? ['data'] : ['data', 'catalog'];
        }
        $model->getConnection()->afterCommit(function () use ($scopes): void {
            foreach ($scopes as $scope) {
                $this->cache->invalidate($scope);
            }
        });
    }
}
