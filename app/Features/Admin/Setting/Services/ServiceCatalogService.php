<?php

namespace App\Features\Admin\Setting\Services;

use App\Models\ServiceOffering;
use App\Support\SafeNavigationUrl;
use Illuminate\Support\Collection;

class ServiceCatalogService
{
    /** @return Collection<int, array<string, mixed>> */
    public function all(): Collection
    {
        return ServiceOffering::query()->orderBy('sort_order')->orderBy('id')->get()
            ->map(fn (ServiceOffering $service): array => $this->values($service));
    }

    /** @return array<string, mixed>|null */
    public function find(string $code): ?array
    {
        $service = ServiceOffering::query()->where('code', $code)->first();

        return $service ? $this->values($service) : null;
    }

    /** @param array<string, mixed> $values */
    public function create(array $values): void
    {
        ServiceOffering::query()->create($values);
    }

    /** @param array<string, mixed> $values */
    public function update(string $code, array $values): void
    {
        unset($values['code']);
        ServiceOffering::query()->where('code', $code)->firstOrFail()->update($values);
    }

    public function delete(string $code): void
    {
        ServiceOffering::query()->where('code', $code)->firstOrFail()->delete();
    }

    /** @return array<string, mixed> */
    private function values(ServiceOffering $service): array
    {
        $url = filled($service->page_slug) ? '/'.$service->page_slug : (SafeNavigationUrl::passes($service->url) ? $service->url : null);

        return [
            ...$service->only(['code', 'name', 'icon_type', 'icon', 'image_url', 'is_enabled', 'maintenance_message', 'sort_order']),
            'description' => $service->description ?? '',
            'payload_fields' => $service->payload_fields ?? [],
            'page_slug' => $service->page_slug,
            'url' => $url,
            'is_available' => filled($url),
            'route' => null,
        ];
    }
}
