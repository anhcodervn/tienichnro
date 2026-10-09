<?php

namespace App\Features\NroNotification\Services;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class NotificationStreamSlotsService
{
    /** @return array<Lock> */
    public function acquire(Request $request): array
    {
        $locks = [];
        foreach ([['session:'.$request->session()->getId(), 2], ['ip:'.$request->ip(), 5]] as [$identity, $limit]) {
            $acquired = false;
            for ($slot = 0; $slot < $limit; $slot++) {
                $lock = Cache::lock('nro:stream:'.hash('sha256', $identity).':'.$slot, 40);
                if ($lock->get()) {
                    $locks[] = $lock;
                    $acquired = true;
                    break;
                }
            }
            if (! $acquired) {
                $this->release($locks);
                abort(429, 'Đã đạt giới hạn kết nối trực tiếp. Hãy đóng bớt tab rồi thử lại.');
            }
        }

        return $locks;
    }

    /** @param array<Lock> $locks */
    public function release(array $locks): void
    {
        foreach ($locks as $lock) {
            $lock->release();
        }
    }
}
