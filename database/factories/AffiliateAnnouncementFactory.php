<?php

namespace Database\Factories;

use App\Models\AffiliateAnnouncement;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AffiliateAnnouncement>
 */
class AffiliateAnnouncementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'admin_id' => null,
            'title' => fake()->sentence(6),
            'content' => [[
                'type' => 'paragraph',
                'children' => [['text' => fake()->paragraph()]],
            ]],
            'is_pinned' => false,
            'is_published' => true,
            'published_at' => now(),
        ];
    }

    public function pinned(): static
    {
        return $this->state(fn (): array => ['is_pinned' => true]);
    }

    public function draft(): static
    {
        return $this->state(fn (): array => ['is_published' => false, 'published_at' => null]);
    }
}
