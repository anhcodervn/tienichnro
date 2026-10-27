<?php

namespace App\Features\NroGameData\Services;

use App\Features\NroGameData\Jobs\SyncGameDataChunk;
use App\Models\NroGameDataSync;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GameDataSyncService
{
    public function __construct(private readonly GameDataCatalogService $catalog) {}

    /** @param array<string, mixed> $payload */
    public function enqueue(string $dataset, array $payload): NroGameDataSync
    {
        $connection = (string) config('queue.default');
        $driver = config('queue.connections.'.$connection.'.driver');
        abort_unless(in_array($driver, ['database', 'redis', 'beanstalkd', 'sqs'], true), 503,
            'Game data synchronization requires an asynchronous queue connection.');
        $rows = $this->catalog->normalize($dataset, $payload['data']);
        $chunks = array_chunk($rows, 50);

        return DB::transaction(function () use ($dataset, $payload, $rows, $chunks, $connection): NroGameDataSync {
            $sync = NroGameDataSync::query()->create([
                'dataset' => $dataset, 'status' => $chunks === [] ? 'completed' : 'queued',
                'account_id' => $payload['accountId'] ?? null, 'server_code' => $payload['serverCode'] ?? null,
                'source_version' => $payload['version'] ?? null, 'source_updated_at' => $payload['updatedAt'] ?? null,
                'total' => count($rows), 'total_chunks' => count($chunks), 'completed_chunks' => [],
            ]);
            foreach ($chunks as $index => $chunk) {
                SyncGameDataChunk::dispatch($sync->id, $index, $chunk)->onConnection($connection)->onQueue('game-data')->afterCommit();
            }

            return $sync->refresh();
        });
    }

    /** @param list<array<string, mixed>> $rows */
    public function process(int $syncId, int $chunkIndex, array $rows): bool
    {
        return DB::transaction(function () use ($syncId, $chunkIndex, $rows): bool {
            $sync = NroGameDataSync::query()->lockForUpdate()->findOrFail($syncId);
            $done = $sync->completed_chunks ?? [];
            if (in_array($sync->status, ['completed', 'failed'], true) || in_array($chunkIndex, $done, true)) {
                return true;
            }
            if (NroGameDataSync::query()->where('dataset', $sync->dataset)->where('id', '<', $syncId)->whereIn('status', ['queued', 'processing'])->exists()) {
                return false;
            }
            $identityColumns = $this->catalog->identityColumns($sync->dataset);
            $idColumn = $identityColumns[array_key_last($identityColumns)];
            $existing = $this->catalog->query($sync->dataset)->whereIn($idColumn, array_column($rows, $idColumn))->lockForUpdate()->get()
                ->keyBy(fn ($record): string => $this->identityKey($record->getAttributes(), $identityColumns));
            $counts = ['created' => 0, 'updated' => 0, 'unchanged' => 0];
            $preservedAssets = 0;
            foreach ($rows as $row) {
                $record = $existing->get($this->identityKey($row, $identityColumns));
                foreach (['icon', 'image'] as $field) {
                    if ($record !== null && is_string($record->{$field}) && trim($record->{$field}) !== '' && ($row[$field] ?? null) !== $record->{$field}) {
                        $preservedAssets++;
                    }
                }
                $row = $this->catalog->preserveExistingAssets($record, $row);
                if ($this->catalog->matchesRecord($record, $row)) {
                    $counts['unchanged']++;

                    continue;
                }
                $row += ['icon' => null, 'image' => null];
                $identity = array_intersect_key($row, array_flip($identityColumns));
                $this->catalog->query($sync->dataset)->updateOrCreate($identity, $row);
                $counts[$record === null ? 'created' : 'updated']++;
            }
            $done[] = $chunkIndex;
            $sync->fill([
                'completed_chunks' => $done, 'processed' => $sync->processed + count($rows),
                'created' => $sync->created + $counts['created'], 'updated' => $sync->updated + $counts['updated'],
                'unchanged' => $sync->unchanged + $counts['unchanged'],
                'status' => count($done) === $sync->total_chunks ? 'completed' : 'processing',
            ])->save();
            if ($preservedAssets > 0) {
                DB::afterCommit(fn () => Log::info('Game data retained existing image references.', [
                    'sync_id' => $syncId, 'dataset' => $sync->dataset, 'chunk' => $chunkIndex, 'preserved_asset_fields' => $preservedAssets,
                ]));
            }

            return true;
        }, 3);
    }

    /** @param array<string, mixed> $row
     * @param  list<string>  $columns
     */
    private function identityKey(array $row, array $columns): string
    {
        return implode(':', array_map(fn (string $column): string => (string) $row[$column], $columns));
    }
}
