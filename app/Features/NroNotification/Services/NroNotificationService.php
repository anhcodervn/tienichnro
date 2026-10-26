<?php

namespace App\Features\NroNotification\Services;

use App\Features\NroNotification\Jobs\SendZaloEquipmentNotification;
use App\Features\NroNotification\Resources\NotifyResource;
use App\Models\Boss;
use App\Models\CodeNotify;
use App\Models\Notify;
use App\Models\NroEventReceipt;
use App\Models\NroServer;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class NroNotificationService
{
    private const UPLOAD_DELAY_SECONDS = 10;

    public function __construct(private readonly BossMessageParserService $parser, private readonly NotificationTypeClassifierService $classifier, private readonly NotificationReadCacheService $readCache) {}

    /** @param array<string, mixed> $payload
     * @return array{status: string, duplicate: bool, notify: Notify|null}
     */
    public function ingest(array $payload): array
    {
        if (isset($payload['server_code'])) {
            $server = NroServer::query()->where('server_code', $payload['server_code'])->where('is_active', true)->first();
            if (! $server || (isset($payload['server_id']) && (int) $payload['server_id'] !== $server->id)) {
                throw ValidationException::withMessages(['server_code' => 'Mã server không hợp lệ hoặc không khớp với server_id.']);
            }
            $payload['server_id'] = $server->id;
        }
        unset($payload['server_code']);
        $payload['server_id'] = (int) $payload['server_id'];
        if (isset($payload['code_id'])) {
            $requestedCode = CodeNotify::query()->findOrFail($payload['code_id'])->code;
            if (filled($payload['code'] ?? null) && $payload['code'] !== $requestedCode) {
                throw ValidationException::withMessages(['code_id' => 'code_id và code không cùng loại thông báo.']);
            }
            $payload['code'] = $requestedCode;
            unset($payload['code_id']);
        }
        $time = CarbonImmutable::parse($payload['occurred_at'])->setTimezone(config('app.timezone'));
        $payload['occurred_at'] = $time->format('Y-m-d H:i:s.u');
        $time = $time->subSeconds(self::UPLOAD_DELAY_SECONDS);
        $parsed = $this->parser->parse($payload['content']);
        if ($parsed) {
            $payload['code'] = 'BOSS';
        }
        $eventId = $payload['event_id'] ?? null;
        unset($payload['event_id']);
        ksort($payload);
        $zalo = app(ZaloBotNotificationService::class);
        $equipment = $zalo->parse($payload['content']);
        $payloadHash = $equipment !== null
            ? $zalo->fingerprint($equipment, $payload['server_id'])
            : hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $eventKey = $eventId !== null ? hash('sha256', 'event:'.$eventId) : hash('sha256', 'message:'.$payloadHash);

        return DB::transaction(function () use ($payload, $time, $eventKey, $payloadHash, $equipment): array {
            NroServer::query()->where('is_active', true)->lockForUpdate()->findOrFail($payload['server_id']);
            $receipt = NroEventReceipt::query()->where('server_id', $payload['server_id'])->where('event_key', $eventKey)->first();
            if ($receipt) {
                if (! hash_equals($receipt->payload_hash, $payloadHash)) {
                    throw ValidationException::withMessages(['event_id' => 'event_id đã được dùng cho nội dung khác trên server này.']);
                }

                return ['status' => $receipt->status, 'duplicate' => true, 'notify' => $receipt->notify_id ? Notify::query()->with(['boss', 'server', 'code'])->find($receipt->notify_id) : null];
            }

            $sameMessage = NroEventReceipt::query()->where('server_id', $payload['server_id'])->where('payload_hash', $payloadHash)->first();
            if ($sameMessage) {
                NroEventReceipt::query()->create([
                    'server_id' => $payload['server_id'], 'event_key' => $eventKey, 'payload_hash' => $payloadHash,
                    'notify_id' => $sameMessage->notify_id, 'status' => $sameMessage->status, 'content' => $payload['content'], 'occurred_at' => $time,
                ]);

                return ['status' => $sameMessage->status, 'duplicate' => true, 'notify' => $sameMessage->notify_id ? Notify::query()->with(['boss', 'server', 'code'])->find($sameMessage->notify_id) : null];
            }

            $parsed = $this->parser->parse($payload['content']);
            $code = $parsed ? 'BOSS' : ($this->classifier->match($payload['content'])?->code ?? $payload['code'] ?? CodeNotify::query()->where('system_key', 'OTHER')->value('code'));
            $notify = null;
            $status = 'created';
            if ($parsed) {
                $bossQuery = Boss::query();
                if ($parsed['kind'] === 'spawn') {
                    $bossQuery->where('is_active', true);
                } else {
                    $bossQuery->withTrashed();
                }
                if (filled($payload['boss_code'] ?? null)) {
                    $requestedCode = NotificationTypeClassifierService::normalizeText($payload['boss_code']);
                    $matchingIds = (clone $bossQuery)->get(['id', 'code'])->filter(fn (Boss $boss): bool => NotificationTypeClassifierService::normalizeText($boss->code) === $requestedCode)->modelKeys();
                    $bossQuery->whereKey($matchingIds);
                } else {
                    $name = NotificationTypeClassifierService::normalizeText($parsed['boss_name']);
                    $candidates = (clone $bossQuery)->get(['id', 'name', 'game_names']);
                    $exactMatches = $candidates->filter(fn (Boss $boss): bool => in_array($name, array_map(NotificationTypeClassifierService::normalizeText(...), $boss->game_names ?: [$boss->name]), true));
                    $matchingIds = ($exactMatches->isNotEmpty() ? $exactMatches : $candidates->filter(fn (Boss $boss): bool => $this->matchesBossPattern($name, $boss->game_names ?: [$boss->name])))->modelKeys();
                    $bossQuery->whereKey($matchingIds);
                }
                $bosses = $bossQuery->lockForUpdate()->get();
                if ($bosses->count() !== 1) {
                    [$notify, $status] = $this->globalBoss($payload, $parsed, $time);
                } else {
                    $boss = $bosses->first();
                    if ($parsed['kind'] === 'spawn') {
                        $bossCode = $this->classifier->match($payload['content']) ?? CodeNotify::query()->where('system_key', 'BOSS')->lockForUpdate()->first();
                        if ($bossCode === null) {
                            throw ValidationException::withMessages(['content' => 'Chưa có loại thông báo Boss đang hoạt động phù hợp.']);
                        }
                        if (mb_strlen($parsed['boss_name']) > 100 || mb_strlen($parsed['map_name']) > 150 || mb_strlen($parsed['zone_name'] ?? '') > 100 || $parsed['zone'] > 65535) {
                            throw ValidationException::withMessages(['content' => 'Tên boss, map hoặc khu vượt quá giới hạn cho phép.']);
                        }
                        $notify = Notify::query()->create([
                            'server_id' => $payload['server_id'], 'code_id' => $bossCode->id, 'boss_id' => $boss->id, 'is_boss' => true,
                            'boss_name' => $parsed['boss_name'], 'content' => $payload['content'], 'map_name' => $parsed['map_name'], 'zone' => $parsed['zone'], 'zone_name' => $parsed['zone_name'],
                            'map_id' => $payload['map_id'] ?? null, 'time_start' => $time, 'metadata' => $payload['metadata'] ?? null,
                        ]);
                    } else {
                        if (mb_strlen($parsed['killed_by']) > 100) {
                            throw ValidationException::withMessages(['content' => 'Tên người tiêu diệt vượt quá 100 ký tự.']);
                        }
                        $living = Notify::query()->living()->where('server_id', $payload['server_id'])->where('boss_id', $boss->id)
                            ->where('time_start', '<=', $time->format('Y-m-d H:i:s.u'));
                        $name = NotificationTypeClassifierService::normalizeText($parsed['boss_name']);
                        $matchingIds = (clone $living)->get(['id', 'boss_name', 'content'])
                            ->filter(fn (Notify $notify): bool => NotificationTypeClassifierService::normalizeText($notify->boss_name ?? $this->parser->parse($notify->content)['boss_name'] ?? $boss->name) === $name)->modelKeys();
                        $notify = $living->whereKey($matchingIds)
                            ->orderByDesc('time_start')->orderByDesc('id')->lockForUpdate()->first();
                        if ($notify) {
                            $notify->update([
                                'death_time' => $time, 'char_name' => $parsed['killed_by'], 'killed_by' => $parsed['killed_by'], 'death_content' => $payload['content'],
                                'respawn_at' => $boss->respawn_seconds !== null ? $time->addSeconds($boss->respawn_seconds) : null,
                            ]);
                            $status = 'updated';
                        } else {
                            $status = 'ignored_no_living_boss';
                            Log::warning('NRO boss death has no matching living lifecycle', ['server_id' => $payload['server_id'], 'boss_id' => $boss->id, 'occurred_at' => $time->toIso8601String()]);
                        }
                    }
                }
            } elseif (preg_match('/(?<![\p{L}\p{N}_])boss(?![\p{L}\p{N}_])/u', NotificationTypeClassifierService::normalizeText($payload['content']))) {
                [$notify, $status] = $this->globalBoss($payload, null, $time);
            } else {
                $notifyCode = $code !== null ? CodeNotify::query()->where('code', $code)->lockForUpdate()->first() : null;
                if (in_array($code, ['BOSS', 'BOSS_APPEAR', 'BOSS_DIE'], true) || $notifyCode?->system_key === 'BOSS' || filled($payload['boss_code'] ?? null)) {
                    throw ValidationException::withMessages(['content' => 'Không nhận diện được nội dung Boss xuất hiện hoặc bị tiêu diệt.']);
                }
                if ($notifyCode === null) {
                    throw ValidationException::withMessages(['content' => 'Không có loại thông báo phù hợp. Hãy cấu hình keyword hoặc gửi code của một loại đang hoạt động.']);
                }
                $notify = Notify::query()->create([
                    'server_id' => $payload['server_id'], 'code_id' => $notifyCode->id, 'content' => $payload['content'],
                    'char_name' => $payload['char_name'] ?? null, 'map_name' => $payload['map_name'] ?? null,
                    'map_id' => $payload['map_id'] ?? null, 'zone' => $payload['zone'] ?? null,
                    'time_start' => $time, 'expires_at' => $payload['expires_at'] ?? null, 'metadata' => $payload['metadata'] ?? null,
                ]);
            }
            NroEventReceipt::query()->create([
                'server_id' => $payload['server_id'], 'event_key' => $eventKey, 'payload_hash' => $payloadHash,
                'notify_id' => $notify?->id, 'status' => $status, 'content' => $payload['content'], 'occurred_at' => $time,
            ]);

            $notify?->load(['boss', 'server', 'code']);
            if ($notify !== null && $equipment !== null && config('services.zalo_bot.enabled')) {
                SendZaloEquipmentNotification::dispatch($equipment, $notify->server->server_code, $notify->server->name)->afterCommit();
            }
            $deliveryKey = hash('sha256', $payload['server_id'].':'.$eventKey);
            app(NotificationWebhookService::class)->capture($deliveryKey, [
                'event_id' => $deliveryKey, 'event' => 'game.notification', 'status' => $status,
                'server_code' => NroServer::query()->whereKey($payload['server_id'])->value('server_code'),
                'code' => $notify?->code?->code ?? $code,
                'content' => $payload['content'], 'occurred_at' => $time->toIso8601String(),
                'notification' => $notify ? (new NotifyResource($notify))->resolve() : null,
            ]);

            return ['status' => $status, 'duplicate' => false, 'notify' => $notify];
        }, 3);
    }

    /** @param list<string> $aliases */
    private function matchesBossPattern(string $name, array $aliases): bool
    {
        foreach ($aliases as $alias) {
            $alias = NotificationTypeClassifierService::normalizeText($alias);
            if (! str_contains($alias, '[x]')) {
                continue;
            }
            $pattern = str_replace('\\[x\\]', '[0-9]+', preg_quote($alias, '~'));
            if (preg_match('~\\A'.$pattern.'\\z~u', $name) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>|null  $parsed
     * @return array{Notify, string}
     */
    private function globalBoss(array $payload, ?array $parsed, CarbonImmutable $time): array
    {
        if (mb_strlen($parsed['boss_name'] ?? '') > 100 || mb_strlen($parsed['map_name'] ?? '') > 150 || mb_strlen($parsed['zone_name'] ?? '') > 100 || ($parsed['zone'] ?? 0) > 65535 || mb_strlen($parsed['killed_by'] ?? '') > 100) {
            throw ValidationException::withMessages(['content' => 'Thông tin Boss vượt quá giới hạn cho phép.']);
        }
        if (($parsed['kind'] ?? null) === 'death') {
            $name = NotificationTypeClassifierService::normalizeText($parsed['boss_name']);
            $living = Notify::query()->living()->whereNull('boss_id')->where('server_id', $payload['server_id'])->where('time_start', '<=', $time->format('Y-m-d H:i:s.u'));
            $ids = (clone $living)->get(['id', 'boss_name'])->filter(fn (Notify $notify): bool => NotificationTypeClassifierService::normalizeText($notify->boss_name) === $name)->modelKeys();
            $notify = $living->whereKey($ids)->orderByDesc('time_start')->orderByDesc('id')->lockForUpdate()->first();
            if ($notify) {
                $notify->update(['death_time' => $time, 'death_content' => $payload['content'], 'char_name' => $parsed['killed_by'], 'killed_by' => $parsed['killed_by'], 'respawn_at' => null]);

                return [$notify, 'updated'];
            }
        }
        $type = CodeNotify::query()->where('system_key', 'BOSS')->lockForUpdate()->first() ?? $this->classifier->match($payload['content']);
        if ($type === null) {
            throw ValidationException::withMessages(['content' => 'Chưa có loại thông báo Boss đang hoạt động phù hợp.']);
        }
        $death = ($parsed['kind'] ?? null) === 'death';
        $notify = Notify::query()->create([
            'server_id' => $payload['server_id'], 'code_id' => $type->id, 'is_boss' => true, 'boss_id' => null,
            'boss_name' => $parsed['boss_name'] ?? null, 'content' => $payload['content'], 'time_start' => $time,
            'map_name' => $parsed['map_name'] ?? $payload['map_name'] ?? null, 'map_id' => $payload['map_id'] ?? null,
            'zone' => $parsed !== null ? ($parsed['zone'] ?? null) : ($payload['zone'] ?? null), 'zone_name' => $parsed['zone_name'] ?? null,
            'char_name' => $parsed['killed_by'] ?? $payload['char_name'] ?? null, 'killed_by' => $parsed['killed_by'] ?? null,
            'death_time' => $death ? $time : null, 'death_content' => $death ? $payload['content'] : null,
            'metadata' => $payload['metadata'] ?? null,
        ]);

        return [$notify, 'created'];
    }

    /** @param array<string, mixed> $filters */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $paginationFilters = Arr::except($filters, ['_preview_since', '_preview_cutoff']);
        $limit = max(1, min(100, (int) ($filters['per_page'] ?? $filters['limit'] ?? 20)));
        $page = max(1, (int) ($filters['page'] ?? Paginator::resolveCurrentPage()));
        $filters = array_filter(Arr::only($filters, ['server_id', 'server_code', 'boss_id', 'code', 'state', 'q', '_preview_since']), fn ($value): bool => $value !== null && $value !== '');
        foreach (['server_id', 'server_code', 'boss_id'] as $field) {
            if (isset($filters[$field])) {
                $filters[$field] = (int) $filters[$field];
            }
        }
        $since = isset($filters['_preview_since']) ? CarbonImmutable::parse($filters['_preview_since']) : null;
        unset($filters['_preview_since']);
        if (($filters['state'] ?? '') === 'respawning') {
            $rows = $this->readCache->remember('data', ['kind' => 'respawning', ...$filters], fn () => $this->orderedQuery($filters)->get());
            $rows = $rows->filter(fn (Notify $notify): bool => $notify->respawn_at?->gt(now()) && ($since === null || $notify->time_start->gte($since)))->values();
            if ($since !== null) {
                $rows = $rows->take(30);
                $limit = min($limit, 10);
            }

            return (new \Illuminate\Pagination\LengthAwarePaginator($rows->slice(($page - 1) * $limit, $limit)->values(), $rows->count(), $limit, $page,
                ['path' => Paginator::resolveCurrentPath()]))->appends($paginationFilters);
        }
        if ($since !== null) {
            $rows = $this->readCache->remember('data', ['audience' => 'guest', ...$filters], fn () => $this->orderedQuery($filters)->limit(30)->get());
            $rows = $rows->filter(fn (Notify $notify): bool => $notify->time_start->gte($since))->values();
            $limit = min($limit, 10);

            return new \Illuminate\Pagination\LengthAwarePaginator($rows->slice(($page - 1) * $limit, $limit)->values(), $rows->count(), $limit, $page,
                ['path' => Paginator::resolveCurrentPath()]);
        }

        $data = $this->readCache->remember('data', ['audience' => 'member', 'limit' => $limit, 'page' => $page, ...$filters], function () use ($filters, $limit, $page): array {
            $query = $this->orderedQuery($filters);
            $rows = $query->paginate($limit, ['*'], 'page', $page);

            return ['items' => $rows->getCollection(), 'total' => $rows->total()];
        });

        return (new \Illuminate\Pagination\LengthAwarePaginator($data['items'], $data['total'], $limit, $page, ['path' => Paginator::resolveCurrentPath()]))->appends($paginationFilters);
    }

    /** @param array<string, mixed> $filters */
    private function orderedQuery(array $filters): Builder
    {
        return $this->filteredQuery($filters)
            ->when(($filters['state'] ?? '') === 'respawning', fn (Builder $query): Builder => $query->orderBy('respawn_at'))
            ->orderByDesc('time_start')->orderByDesc('id');
    }

    /** @param array<string, mixed> $filters
     * @return Collection<int, Notify>
     */
    public function latest(array $filters): Collection
    {
        return $this->filteredQuery($filters)->orderByDesc('time_start')->orderByDesc('id')
            ->limit($filters['limit'] ?? $filters['per_page'] ?? 100)->get();
    }

    /** @param array<string, mixed> $filters */
    private function filteredQuery(array $filters): Builder
    {
        return Notify::query()->with(['boss', 'server', 'code'])
            ->when(isset($filters['_preview_since']), fn (Builder $query): Builder => $query
                ->where('time_start', '>=', $filters['_preview_since']))
            ->when($filters['server_id'] ?? null, fn (Builder $query, int $id): Builder => $query->where('server_id', $id))
            ->when(isset($filters['server_code']), fn (Builder $query): Builder => $query->whereHas('server', fn (Builder $servers): Builder => $servers->where('server_code', $filters['server_code'])))
            ->when($filters['boss_id'] ?? null, fn (Builder $query, int $id): Builder => $query->where('boss_id', $id))
            ->when($filters['code'] ?? null, fn (Builder $query, string $code): Builder => $query->whereHas('code', fn (Builder $codes): Builder => $codes->where('code', $code)))
            ->when(isset($filters['q']) && $filters['q'] !== '', function (Builder $query) use ($filters): void {
                $keyword = $filters['q'];
                $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $keyword).'%';
                $query->where(function (Builder $matches) use ($pattern): void {
                    $matches->whereRaw("content LIKE ? ESCAPE '!'", [$pattern]);
                    foreach (['death_content', 'boss_name', 'map_name', 'zone_name', 'killed_by', 'char_name'] as $column) {
                        $matches->orWhereRaw($column." LIKE ? ESCAPE '!'", [$pattern]);
                    }
                    $matches->orWhereHas('boss', fn (Builder $bosses): Builder => $bosses->whereRaw("name LIKE ? ESCAPE '!'", [$pattern]));
                });
            })
            ->when(($filters['state'] ?? '') === 'living', fn (Builder $query): Builder => $query->living())
            ->when(($filters['state'] ?? '') === 'respawning', fn (Builder $query): Builder => $query->respawning())
            ->when(($filters['state'] ?? '') === 'history', fn (Builder $query): Builder => $query->bossHistory());
    }
}
