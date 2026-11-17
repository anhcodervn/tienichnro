<?php

namespace App\Features\Client\GameImage\Services;

use App\Models\GameImageAccount;
use App\Models\GameImageCaptureLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class GameImageCaptureLogService
{
    public function __construct(private readonly GameImageToolService $tool) {}

    /**
     * @param  list<array{client_log_id: string, level: string, step?: ?string, message: string, occurred_at?: ?string}>  $entries
     * @return array{inserted: int, duplicates: int}
     */
    public function append(GameImageAccount $account, int $version, string $claimToken, array $entries): array
    {
        return DB::transaction(function () use ($account, $version, $claimToken, $entries): array {
            $user = User::query()->lockForUpdate()->findOrFail($account->user_id);
            $locked = GameImageAccount::query()->lockForUpdate()->findOrFail($account->id);
            abort_unless($user->status === 'active', 409, 'Account owner is inactive.');
            $this->tool->assertClaim($locked, $version, $claimToken);
            abort_unless(in_array($locked->status, ['processing', 'success', 'error'], true), 409, 'Capture has not been claimed.');
            $ids = array_map(fn (array $entry): string => strtolower($entry['client_log_id']), $entries);
            $existing = GameImageCaptureLog::query()->where('account_id', $locked->id)->where('capture_version', $version)
                ->whereIn('client_log_id', $ids)->pluck('client_log_id')->all();
            $secrets = array_values(array_filter([$locked->password, $claimToken]));
            $rows = [];
            $now = now();
            foreach ($entries as $entry) {
                $clientId = strtolower($entry['client_log_id']);
                if (in_array($clientId, $existing, true)) {
                    continue;
                }
                $existing[] = $clientId;
                $rows[] = ['account_id' => $locked->id, 'capture_version' => $version, 'client_log_id' => $clientId,
                    'level' => $entry['level'], 'step' => isset($entry['step']) ? mb_substr(str_replace($secrets, '[REDACTED]', $entry['step']), 0, 100) : null,
                    'message' => mb_substr(str_replace($secrets, '[REDACTED]', $entry['message']), 0, 1000),
                    'occurred_at' => filled($entry['occurred_at'] ?? null) ? CarbonImmutable::parse($entry['occurred_at'])->setTimezone(config('app.timezone')) : null,
                    'created_at' => $now, 'updated_at' => $now];
            }
            if ($rows !== []) {
                GameImageCaptureLog::query()->insert($rows);
            }

            return ['inserted' => count($rows), 'duplicates' => count($entries) - count($rows)];
        }, 3);
    }
}
