<?php

namespace App\Features\NroGameData\Services;

use App\Models\NroGameDataRecord;
use Illuminate\Database\Eloquent\Builder;

class GameDataBrowseService
{
    public const CATEGORIES = [
        'items' => ['name' => 'Vật phẩm', 'icon' => 'bx-package', 'description' => 'Trang bị, vật phẩm và các chỉ số trong game.'],
        'maps' => ['name' => 'Bản đồ', 'icon' => 'bx-map-alt', 'description' => 'Tra cứu tên và mã các bản đồ Ngọc Rồng Online.'],
        'npcs' => ['name' => 'NPC', 'icon' => 'bx-user-voice', 'description' => 'Nhân vật, tạo hình và các lựa chọn hội thoại.'],
        'mobs' => ['name' => 'Quái vật', 'icon' => 'bx-bug', 'description' => 'Danh sách quái vật cùng chỉ số HP và di chuyển.'],
        'classes-and-skills' => ['name' => 'Kỹ năng', 'icon' => 'bx-bolt-circle', 'description' => 'Kỹ năng theo từng hệ và thông số của mỗi cấp.'],
        'item-options' => ['name' => 'Option vật phẩm', 'icon' => 'bx-slider-alt', 'description' => 'Các thuộc tính và hiệu ứng của vật phẩm.'],
        'skill-options' => ['name' => 'Option kỹ năng', 'icon' => 'bx-adjust', 'description' => 'Các thuộc tính và hiệu ứng của kỹ năng.'],
    ];

    public function __construct(private readonly GameDataCatalogService $catalog, private readonly GameEffectService $effects) {}

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function browse(array $filters): array
    {
        $dataset = $filters['type'] ?? 'items';
        $columns = array_column($this->catalog->definition($dataset)['fields'], 'column');
        $identity = $this->catalog->identityColumns($dataset);
        $idColumn = $identity[array_key_last($identity)];
        $columns = array_values(array_diff(array_unique(['id', ...$columns, ...$identity, 'icon', 'image', 'updated_at', ...($dataset === 'classes-and-skills' ? ['class_name'] : [])]), ['menu', 'levels']));
        $query = $this->catalog->query($dataset)->select($columns);
        $search = trim($filters['q'] ?? '');
        if ($search !== '') {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
            $gameId = filter_var($search, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 4294967295]]);
            $query->where(function (Builder $query) use ($pattern, $gameId, $idColumn): void {
                $query->whereRaw("name LIKE ? ESCAPE '!'", [$pattern]);
                if ($gameId !== false) {
                    $query->orWhere($idColumn, $gameId);
                }
            });
        }
        if ($dataset === 'items' && isset($filters['gender'])) {
            $query->where('gender', $filters['gender']);
        }
        if ($dataset === 'classes-and-skills') {
            $query->orderBy('class_id');
        }
        $records = $query->orderBy($idColumn)->paginate(24)->appends($filters);
        $cards = $records->getCollection()->map(fn (NroGameDataRecord $record): array => $this->card($dataset, $record, $idColumn));
        $categories = [];
        foreach (self::CATEGORIES as $key => $category) {
            $categories[$key] = [...$category, 'count' => $this->catalog->query($key)->count()];
        }

        return [
            'dataset' => $dataset, 'category' => self::CATEGORIES[$dataset], 'categories' => $categories,
            'records' => $records, 'cards' => $cards, 'filters' => $filters,
            'totalEntries' => array_sum(array_column($categories, 'count')),
        ];
    }

    /** @return array<string, mixed> */
    private function card(string $dataset, NroGameDataRecord $record, string $idColumn): array
    {
        $badge = self::CATEGORIES[$dataset]['name'];
        $tone = 'slate';
        if ($dataset === 'items') {
            [$badge, $tone] = match ($record->gender) {
                0 => ['Trái đất', 'emerald'], 1 => ['Namek', 'cyan'], 2 => ['Xayda', 'amber'], default => ['Dùng chung', 'slate'],
            };
        } elseif ($dataset === 'classes-and-skills') {
            $badge = $record->class_name;
            $tone = 'violet';
        }
        $stats = match ($dataset) {
            'items' => [
                'Loại' => match ($record->type) {
                    0 => 'Áo [0]', 1 => 'Quần [1]', default => 'Loại '.$record->type
                },
                'Sức mạnh' => $this->compactNumber($record->str_require),
            ],
            'mobs' => ['HP' => $this->compactNumber($record->hp), 'Tốc độ' => $record->speed],
            'npcs' => ['Mã đầu' => $record->head_id, 'Mã thân' => $record->body_id],
            'classes-and-skills' => ['Điểm tối đa' => $record->max_point, 'Loại' => $record->type],
            'item-options' => ['Loại' => $record->type, 'Màu' => $record->color],
            default => ['Mã game' => $record->{$idColumn}],
        };

        $effect = $this->effects->forRecord($dataset, (int) $record->{$idColumn});

        return [
            'name' => $record->name !== '' ? $record->name : 'Chưa có tên', 'game_id' => $record->{$idColumn},
            'description' => $record->description ?? '', 'icon' => self::CATEGORIES[$dataset]['icon'],
            'effect' => $effect, 'media' => $effect ? null : ($this->mediaUrl($record->icon) ?? $this->mediaUrl($record->image)),
            'badge' => $badge, 'tone' => $tone, 'stats' => $stats,
            'url' => route('nro.game-data.show', ['dataset' => $dataset, 'record' => $record->id]),
        ];
    }

    private function compactNumber(int $number): string
    {
        foreach ([1000000000 => 'B', 1000000 => 'M', 1000 => 'K'] as $unit => $suffix) {
            if ($number >= $unit) {
                return rtrim(rtrim(number_format($number / $unit, 1, '.', ''), '0'), '.').$suffix;
            }
        }

        return (string) $number;
    }

    private function mediaUrl(?string $value): ?string
    {
        if ($value === null || preg_match('/[\x00-\x20\\\\]/', $value)) {
            return null;
        }
        if (str_starts_with($value, '/') && ! str_starts_with($value, '//')) {
            return $value;
        }
        if (filter_var($value, FILTER_VALIDATE_URL) && in_array(strtolower(parse_url($value, PHP_URL_SCHEME) ?? ''), ['http', 'https'], true)) {
            return $value;
        }

        return null;
    }
}
