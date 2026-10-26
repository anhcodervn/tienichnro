<?php

namespace App\Features\NroGameData\Services;

use App\Models\NroGameDataRecord;
use Illuminate\Support\Facades\Cache;

class GameDataReadCacheService
{
    public function __construct(private readonly GameDataCatalogService $catalog) {}

    /** @param array<string, mixed> $filters
     * @return array{data: list<array<string, mixed>>, meta: array{limit: int, has_more: bool, next_after_id: ?int}}
     */
    public function index(string $dataset, array $filters): array
    {
        $key = $this->key($dataset);
        $records = Cache::get($key);
        if ($records === null) {
            $records = Cache::lock($key.':lock', 30)->block(10, fn (): array => Cache::rememberForever(
                $key, fn (): array => $this->catalog->query($dataset)->orderBy('id')->get()->toArray(),
            ));
        }
        $identity = $this->catalog->identityColumns($dataset);
        $rows = collect($records)->where('id', '>', (int) ($filters['after_id'] ?? 0));
        if (isset($filters['ids'])) {
            $rows = $rows->whereIn($identity[array_key_last($identity)], $filters['ids']);
        }
        if (isset($filters['class_id'])) {
            $rows = $rows->where('class_id', (int) $filters['class_id']);
        }
        $limit = (int) ($filters['limit'] ?? 200);
        $hasMore = $rows->count() > $limit;
        $rows = $rows->take($limit)->values();
        $nextAfterId = $hasMore ? $rows->last()['id'] : null;
        if (($filters['view'] ?? 'records') === 'hashes') {
            $rows = $rows->map(fn (array $row): array => array_intersect_key($row, array_flip(['id', ...$identity, 'content_hash', 'updated_at'])));
        }

        return ['data' => $rows->all(), 'meta' => [
            'limit' => $limit, 'has_more' => $hasMore, 'next_after_id' => $nextAfterId,
        ]];
    }

    public function invalidate(NroGameDataRecord $record): void
    {
        foreach (GameDataCatalogService::DATASETS as $dataset => $definition) {
            if (! $record instanceof $definition['model']) {
                continue;
            }
            if ($record->getAttribute('kind') !== null) {
                $kind = $dataset === 'item-options' ? 'item' : 'skill';
                if ($record->kind !== $kind && $record->getRawOriginal('kind') !== $kind) {
                    continue;
                }
            }
            $key = $this->key($dataset);
            $record->getConnection()->afterCommit(static function () use ($key): void {
                Cache::lock($key.':lock', 30)->block(10, fn (): bool => Cache::forget($key));
            });
        }
    }

    private function key(string $dataset): string
    {
        return 'nro:game-data:records:v1:'.$dataset;
    }
}
