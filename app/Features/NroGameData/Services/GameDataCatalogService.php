<?php

namespace App\Features\NroGameData\Services;

use App\Models\NroGameDataRecord;
use App\Models\NroItem;
use App\Models\NroMap;
use App\Models\NroMob;
use App\Models\NroNpc;
use App\Models\NroOption;
use App\Models\NroSkill;
use Illuminate\Database\Eloquent\Builder;

class GameDataCatalogService
{
    public const DATASETS = [
        'maps' => [
            'model' => NroMap::class,
            'fields' => [
                'mapId' => ['column' => 'id_map', 'type' => 'id'],
                'mapName' => ['column' => 'name', 'type' => 'name'],
            ],
        ],
        'npcs' => [
            'model' => NroNpc::class,
            'fields' => [
                'npcTemplateId' => ['column' => 'id_npc', 'type' => 'id'],
                'name' => ['column' => 'name', 'type' => 'text_name'],
                'headId' => ['column' => 'head_id', 'type' => 'int'],
                'bodyId' => ['column' => 'body_id', 'type' => 'int'],
                'legId' => ['column' => 'leg_id', 'type' => 'int'],
                'menu' => ['column' => 'menu', 'type' => 'menu'],
            ],
        ],
        'mobs' => [
            'model' => NroMob::class,
            'fields' => [
                'mobTemplateId' => ['column' => 'id_mob', 'type' => 'id'],
                'type' => ['column' => 'type', 'type' => 'int'],
                'name' => ['column' => 'name', 'type' => 'name'],
                'hp' => ['column' => 'hp', 'type' => 'big'],
                'rangeMove' => ['column' => 'range_move', 'type' => 'int'],
                'speed' => ['column' => 'speed', 'type' => 'int'],
                'dartType' => ['column' => 'dart_type', 'type' => 'int'],
            ],
        ],
        'items' => [
            'model' => NroItem::class,
            'fields' => [
                'id' => ['column' => 'id_item', 'type' => 'id'],
                'type' => ['column' => 'type', 'type' => 'int'],
                'gender' => ['column' => 'gender', 'type' => 'int'],
                'name' => ['column' => 'name', 'type' => 'name'],
                'description' => ['column' => 'description', 'type' => 'text'],
                'level' => ['column' => 'level', 'type' => 'int'],
                'strRequire' => ['column' => 'str_require', 'type' => 'big'],
                'iconID' => ['column' => 'icon_id', 'type' => 'int'],
                'part' => ['column' => 'part', 'type' => 'int'],
                'isUpToUp' => ['column' => 'is_up_to_up', 'type' => 'bool'],
            ],
        ],
        'item-options' => [
            'model' => NroOption::class,
            'fields' => [
                'id' => ['column' => 'id_option', 'type' => 'id'],
                'name' => ['column' => 'name', 'type' => 'text_name'],
                'type' => ['column' => 'type', 'type' => 'int'],
                'color' => ['column' => 'color', 'type' => 'int'],
            ],
        ],
        'skill-options' => [
            'model' => NroOption::class,
            'fields' => [
                'id' => ['column' => 'id_option', 'type' => 'id'],
                'name' => ['column' => 'name', 'type' => 'text_name'],
            ],
        ],
        'classes-and-skills' => [
            'model' => NroSkill::class,
            'fields' => [
                'id' => ['column' => 'id_skill', 'type' => 'id'],
                'name' => ['column' => 'name', 'type' => 'name'],
                'maxPoint' => ['column' => 'max_point', 'type' => 'int'],
                'manaUseType' => ['column' => 'mana_use_type', 'type' => 'int'],
                'type' => ['column' => 'type', 'type' => 'int'],
                'iconId' => ['column' => 'icon_id', 'type' => 'int'],
                'damInfo' => ['column' => 'dam_info', 'type' => 'text'],
                'description' => ['column' => 'description', 'type' => 'text'],
                'levels' => ['column' => 'levels', 'type' => 'levels'],
            ],
        ],
    ];

    /** @return array<string, mixed> */
    public function definition(string $dataset): array
    {
        $definition = self::DATASETS[$dataset];
        $definition['fields'] += [
            'icon' => ['column' => 'icon', 'type' => 'asset'],
            'image' => ['column' => 'image', 'type' => 'asset'],
        ];

        return $definition;
    }

    public function query(string $dataset): Builder
    {
        $model = $this->definition($dataset)['model'];
        $query = $model::query();
        if (in_array($dataset, ['item-options', 'skill-options'], true)) {
            $query->where('kind', $dataset === 'item-options' ? 'item' : 'skill');
        }

        return $query;
    }

    /** @return list<string> */
    public function identityColumns(string $dataset): array
    {
        return match ($dataset) {
            'item-options', 'skill-options' => ['kind', 'id_option'],
            'classes-and-skills' => ['class_id', 'id_skill'],
            default => [$this->definition($dataset)['fields'][array_key_first($this->definition($dataset)['fields'])]['column']],
        };
    }

    /** @param list<array<string, mixed>> $data
     * @return list<array<string, mixed>>
     */
    public function normalize(string $dataset, array $data): array
    {
        $rows = [];
        foreach ($data as $entry) {
            if ($dataset === 'classes-and-skills') {
                foreach ($entry['skills'] as $skill) {
                    $rows[] = $this->record($dataset, $skill, ['classId' => (int) $entry['classId'], 'className' => $entry['name']]);
                }
            } else {
                $rows[] = $this->record($dataset, $entry);
            }
        }

        return $rows;
    }

    /** @param list<array<string, mixed>> $data
     * @return list<array{identity: array<string, mixed>, content_hash: string, needs_update: bool}>
     */
    public function compare(string $dataset, array $data): array
    {
        $rows = $this->normalize($dataset, $data);
        $columns = $this->identityColumns($dataset);
        $idColumn = $columns[array_key_last($columns)];
        $key = fn (array $row): string => implode(':', array_map(fn (string $column): string => (string) $row[$column], $columns));
        $existing = $this->query($dataset)->whereIn($idColumn, array_column($rows, $idColumn))
            ->get([...$columns, 'content_hash', 'icon', 'image'])->keyBy(fn ($record): string => $key($record->getAttributes()));

        return array_map(function (array $row) use ($existing, $key, $columns): array {
            $record = $existing->get($key($row));
            $row = $this->preserveExistingAssets($record, $row);

            return [
                'identity' => array_intersect_key($row, array_flip($columns)),
                'content_hash' => $row['content_hash'],
                'needs_update' => ! $this->matchesRecord($record, $row),
            ];
        }, $rows);
    }

    /** @param array<string, mixed> $entry
     * @param  array<string, mixed>  $class
     * @return array<string, mixed>
     */
    private function record(string $dataset, array $entry, array $class = []): array
    {
        $source = $class;
        $row = [];
        foreach ($this->definition($dataset)['fields'] as $key => $field) {
            $value = $field['type'] === 'asset' ? ($entry[$key] ?? null) : $entry[$key];
            if ($field['type'] === 'asset' && is_string($value) && trim($value) === '') {
                $value = null;
            }
            if (in_array($field['type'], ['id', 'int', 'big'], true)) {
                $value = (int) $value;
            } elseif ($field['type'] === 'bool') {
                $value = (bool) $value;
            } elseif ($field['type'] === 'levels') {
                foreach ($value as &$level) {
                    foreach ($level as $attribute => &$levelValue) {
                        if ($attribute !== 'moreInfo') {
                            $levelValue = (int) $levelValue;
                        }
                    }
                    unset($levelValue);
                }
                unset($level);
                usort($value, fn (array $a, array $b): int => $a['skillId'] <=> $b['skillId']);
            }
            $row[$field['column']] = $source[$key] = $value;
        }
        if ($class !== []) {
            $row['class_id'] = $class['classId'];
            $row['class_name'] = $class['className'];
        }
        if (in_array($dataset, ['item-options', 'skill-options'], true)) {
            $row['kind'] = $dataset === 'item-options' ? 'item' : 'skill';
        }
        $source = $this->canonicalize($source);
        $row['source'] = $source;
        $row['content_hash'] = hash('sha256', json_encode($source, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $row;
    }

    /** @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function preserveExistingAssets(?NroGameDataRecord $record, array $row): array
    {
        foreach (['icon', 'image'] as $field) {
            if ($record !== null && $record->{$field} !== null
                && ($field === 'image' || (($row[$field] ?? null) === null && is_string($record->{$field}) && trim($record->{$field}) !== ''))) {
                $row[$field] = $record->{$field};
                $row['source'][$field] = $record->{$field};
            }
        }
        $row['source'] = $this->canonicalize($row['source']);
        $row['content_hash'] = hash('sha256', json_encode($row['source'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $row;
    }

    /** @param array<string, mixed> $row */
    public function matchesRecord(?NroGameDataRecord $record, array $row): bool
    {
        return $record !== null
            && hash_equals($record->content_hash, $row['content_hash'])
            && $record->icon === ($row['icon'] ?? null)
            && $record->image === ($row['image'] ?? null);
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map($this->canonicalize(...), $value);
    }
}
