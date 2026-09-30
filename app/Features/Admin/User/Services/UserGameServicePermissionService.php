<?php

namespace App\Features\Admin\User\Services;

use App\Models\GameService;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class UserGameServicePermissionService
{
    /** @return array{services: array<int, array<string, mixed>>, selected_ids: array<int, int>} */
    public function catalog(User $user): array
    {
        $this->assertCollaborator($user);

        $selectedIds = $user->allowedGameServices()
            ->pluck('game_services.id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
        $selectedLookup = array_fill_keys($selectedIds, true);

        $services = GameService::query()
            ->with('game:id,name')
            ->orderBy('game_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'game_id', 'name', 'slug', 'code', 'status', 'sort_order'])
            ->map(fn (GameService $service): array => [
                'id' => (int) $service->id,
                'game_id' => (int) $service->game_id,
                'game_name' => (string) $service->game?->name,
                'name' => (string) $service->name,
                'slug' => (string) $service->slug,
                'code' => (string) $service->code,
                'status' => (string) $service->status,
                'is_allowed' => isset($selectedLookup[$service->id]),
            ])
            ->all();

        return [
            'services' => $services,
            'selected_ids' => $selectedIds,
        ];
    }

    /**
     * @param  array<int, int>  $gameServiceIds
     * @return array{services: array<int, array<string, mixed>>, selected_ids: array<int, int>}
     */
    public function sync(User $user, array $gameServiceIds): array
    {
        $this->assertCollaborator($user);
        $user->allowedGameServices()->sync($gameServiceIds);

        return $this->catalog($user->refresh());
    }

    private function assertCollaborator(User $user): void
    {
        if ($user->role !== User::ROLE_COLLABORATOR) {
            throw ValidationException::withMessages([
                'user' => 'Chỉ có thể phân quyền dịch vụ cho tài khoản CTV.',
            ]);
        }
    }
}
