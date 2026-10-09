<?php

namespace App\Features\Admin\Setting\Services;

use App\Models\Setting;
use App\Support\SettingStore;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ToolAvailabilityService
{
    public function __construct(private readonly SettingStore $settings) {}

    /** @return Collection<int, array<string, mixed>> */
    public function all(): Collection
    {
        $tools = collect(config('tools.items', []))->mapWithKeys(fn (array $tool, int $index): array => [($tool['code'] ?? $tool['route'] ?? 'tool_'.$index) => $tool]);
        foreach (Setting::query()->where('key', 'like', 'service.definition.%')->orderBy('id')->get() as $definition) {
            $values = $this->settings->decode($definition, []);
            $code = $values['code'] ?? null;
            if (! is_string($code)) {
                continue;
            }
            if ($values['is_deleted'] ?? false) {
                $tools->forget($code);

                continue;
            }
            $tools->put($code, array_replace($tools->get($code, ['code' => $code, 'name' => $code, 'icon' => 'bx-grid-alt', 'description' => '', 'route' => null, 'parameters' => []]), $values));
        }
        $tools = $tools->values();
        $defaults = [];
        foreach ($tools as $index => $tool) {
            $code = $tool['code'] ?? $tool['route'] ?? 'tool_'.$index;
            $defaults['tool_'.$code.'_enabled'] = true;
            $defaults['tool_'.$code.'_maintenance_message'] = 'Dịch vụ đang bảo trì. Vui lòng quay lại sau.';
            $defaults['tool_'.$code.'_name'] = $tool['name'];
            $defaults['tool_'.$code.'_icon_type'] = 'icon';
            $defaults['tool_'.$code.'_icon'] = $tool['icon'];
            $defaults['tool_'.$code.'_image_url'] = null;
        }
        $values = $this->settings->getMany($defaults);

        return $tools->map(function (array $tool, int $index) use ($values): array {
            $code = $tool['code'] ?? $tool['route'] ?? 'tool_'.$index;

            return [...$tool, 'code' => $code,
                'sort_order' => (int) ($tool['sort_order'] ?? $index + 1),
                'name' => (string) $values['tool_'.$code.'_name'],
                'icon_type' => (string) $values['tool_'.$code.'_icon_type'],
                'icon' => (string) $values['tool_'.$code.'_icon'],
                'image_url' => $values['tool_'.$code.'_image_url'],
                'is_enabled' => (bool) $values['tool_'.$code.'_enabled'],
                'maintenance_message' => (string) $values['tool_'.$code.'_maintenance_message'],
                'is_available' => filled($tool['url'] ?? null) || filled($tool['route'] ?? null),
            ];
        })->sortBy('sort_order')->values();
    }

    /** @return array<string, mixed>|null */
    public function find(string $code): ?array
    {
        return $this->all()->firstWhere('code', $code);
    }

    public function codeExists(string $code): bool
    {
        return collect(config('tools.items', []))->contains('code', $code)
            || Setting::query()->where('key', 'service.definition.'.$code)->exists();
    }

    /** @param array<string, mixed> $values */
    public function create(array $values): void
    {
        DB::transaction(function () use ($values): void {
            $code = $values['code'];
            if ($this->codeExists($code)) {
                throw ValidationException::withMessages(['code' => 'Mã dịch vụ đã được sử dụng.']);
            }
            $definition = Setting::query()->firstOrCreate(['key' => 'service.definition.'.$code], [
                'type' => 'json', 'value' => json_encode(['code' => $code], JSON_THROW_ON_ERROR),
            ]);
            if (! $definition->wasRecentlyCreated) {
                throw ValidationException::withMessages(['code' => 'Mã dịch vụ đã được sử dụng.']);
            }
            $this->update($code, $values);
        });
    }

    /** @return array<string, mixed> */
    public function interaction(string $code): array
    {
        return $this->find($code) ?? ['code' => $code, 'is_enabled' => false, 'maintenance_message' => 'Dịch vụ đang tạm ngừng hoạt động. Vui lòng quay lại sau.'];
    }

    public function delete(string $code): void
    {
        DB::transaction(function () use ($code): void {
            abort_if($this->find($code) === null, 404);
            $this->lockDefinition($code);
            abort_if($this->find($code) === null, 404);
            $this->settings->putArray('service.definition.'.$code, ['code' => $code, 'is_deleted' => true]);
            $this->settings->putMany(['tool_'.$code.'_enabled' => false]);
        });
    }

    /** @param array<string, mixed> $values */
    public function update(string $code, array $values): void
    {
        DB::transaction(function () use ($code, $values): void {
            abort_if($this->find($code) === null, 404);
            $this->lockDefinition($code);
            $tool = $this->find($code);
            abort_if($tool === null, 404);
            $settings = [
                'tool_'.$code.'_enabled' => (bool) $values['is_enabled'],
                'tool_'.$code.'_maintenance_message' => $values['maintenance_message'],
            ];
            foreach (['name', 'icon_type', 'icon', 'image_url'] as $field) {
                if (array_key_exists($field, $values)) {
                    $settings['tool_'.$code.'_'.$field] = $values[$field];
                }
            }
            $this->settings->putMany($settings);
            if (array_key_exists('description', $values) || array_key_exists('url', $values) || array_key_exists('sort_order', $values)) {
                $definition = $this->settings->getArray('service.definition.'.$code, ['code' => $code]);
                foreach (['description', 'url', 'sort_order'] as $field) {
                    if (array_key_exists($field, $values)) {
                        $definition[$field] = match ($field) {
                            'description' => $values[$field] ?? '',
                            'sort_order' => (int) $values[$field],
                            default => $values[$field],
                        };
                    }
                }
                $this->settings->putArray('service.definition.'.$code, $definition);
            }
        });
    }

    private function lockDefinition(string $code): void
    {
        Setting::query()->firstOrCreate(['key' => 'service.definition.'.$code], [
            'type' => 'json', 'value' => json_encode(['code' => $code], JSON_THROW_ON_ERROR),
        ]);
        Setting::query()->where('key', 'service.definition.'.$code)->lockForUpdate()->firstOrFail();
    }
}
