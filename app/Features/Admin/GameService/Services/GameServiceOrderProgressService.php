<?php

namespace App\Features\Admin\GameService\Services;

use App\Models\GameServiceOrder;
use App\Models\GameServiceOrderMessage;
use App\Models\GameServiceOrderProgress;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class GameServiceOrderProgressService
{
    /** @return array<int, array<string, mixed>> */
    public function timeline(User $actor, GameServiceOrder $order): array
    {
        $this->assertCanView($actor, $order);

        return $order->progressUpdates()
            ->with('author:id,username,full_name,role')
            ->oldest('id')
            ->get()
            ->map(fn (GameServiceOrderProgress $progress): array => $this->progressPayload($progress))
            ->all();
    }

    public function storeProgress(
        User $actor,
        GameServiceOrder $order,
        string $description,
        ?UploadedFile $image,
    ): GameServiceOrderProgress {
        $this->assertCanUpdate($actor, $order);
        $imagePath = $image ? $this->storeImage($order, $image, GameServiceOrderProgress::TYPE_PROGRESS) : null;

        try {
            return DB::transaction(function () use ($actor, $order, $description, $imagePath): GameServiceOrderProgress {
                $lockedOrder = GameServiceOrder::query()->lockForUpdate()->findOrFail($order->id);
                $this->assertCanUpdate($actor, $lockedOrder);

                $progress = $lockedOrder->progressUpdates()->create([
                    'user_id' => $actor->id,
                    'type' => GameServiceOrderProgress::TYPE_PROGRESS,
                    'description' => trim($description),
                    'image_path' => $imagePath,
                ]);

                $lockedOrder->messages()->create([
                    'sender_id' => $actor->id,
                    'sender_role' => $actor->role === User::ROLE_ADMIN
                        ? GameServiceOrderMessage::ROLE_ADMIN
                        : GameServiceOrderMessage::ROLE_COLLABORATOR,
                    'game_service_order_progress_id' => $progress->id,
                    'message' => $progress->description,
                ]);

                return $progress;
            }, 3);
        } catch (Throwable $exception) {
            if ($imagePath) {
                Storage::disk('local')->delete($imagePath);
            }

            throw $exception;
        }
    }

    public function complete(
        User $actor,
        GameServiceOrder $order,
        string $description,
        UploadedFile $image,
    ): GameServiceOrder {
        $this->assertCanUpdate($actor, $order);
        $imagePath = $this->storeImage($order, $image, GameServiceOrderProgress::TYPE_COMPLETION);

        try {
            return DB::transaction(function () use ($actor, $order, $description, $imagePath): GameServiceOrder {
                $lockedOrder = GameServiceOrder::query()->lockForUpdate()->findOrFail($order->id);
                $this->assertCanUpdate($actor, $lockedOrder);

                $lockedOrder->progressUpdates()->create([
                    'user_id' => $actor->id,
                    'type' => GameServiceOrderProgress::TYPE_COMPLETION,
                    'description' => trim($description),
                    'image_path' => $imagePath,
                ]);
                $lockedOrder->forceFill(['status' => 'review'])->save();

                return $lockedOrder->refresh();
            }, 3);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($imagePath);

            throw $exception;
        }
    }

    public function image(
        User $actor,
        GameServiceOrder $order,
        GameServiceOrderProgress $progress,
    ): StreamedResponse {
        abort_unless($progress->game_service_order_id === $order->id, 404);
        $this->assertCanView($actor, $order);
        abort_unless(filled($progress->image_path) && Storage::disk('local')->exists($progress->image_path), 404);

        return Storage::disk('local')->response(
            $progress->image_path,
            basename($progress->image_path),
            ['Cache-Control' => 'private, no-store'],
            'inline',
        );
    }

    /** @return array<string, mixed> */
    public function progressPayload(GameServiceOrderProgress $progress): array
    {
        $progress->loadMissing(['order:id,code', 'author:id,username,full_name,role']);

        return [
            'id' => (int) $progress->id,
            'type' => (string) $progress->type,
            'description' => (string) $progress->description,
            'image_url' => $this->imageUrl($progress),
            'author' => $progress->author ? [
                'id' => (int) $progress->author->id,
                'name' => $progress->author->name,
                'role' => (string) $progress->author->role,
            ] : null,
            'created_at' => $progress->created_at?->toISOString(),
        ];
    }

    public function imageUrl(GameServiceOrderProgress $progress): ?string
    {
        if (blank($progress->image_path)) {
            return null;
        }

        $progress->loadMissing('order:id,code');

        return route('account.game-service-orders.progress.image', [
            'gameServiceOrder' => $progress->order,
            'gameServiceOrderProgress' => $progress,
        ]);
    }

    private function assertCanView(User $actor, GameServiceOrder $order): void
    {
        $isAssignedCollaborator = $order->collaborator_id === $actor->id
            && $actor->canAccessCollaboratorDashboard();

        abort_unless(
            $actor->role === User::ROLE_ADMIN
                || $order->user_id === $actor->id
                || $isAssignedCollaborator,
            403,
        );
    }

    private function assertCanUpdate(User $actor, GameServiceOrder $order): void
    {
        abort_unless(
            $order->collaborator_id === $actor->id && $actor->canAccessCollaboratorDashboard(),
            403,
        );
        abort_unless($order->status === 'processing', 422, 'Chỉ có thể cập nhật đơn đang làm.');
    }

    private function storeImage(GameServiceOrder $order, UploadedFile $image, string $type): string
    {
        $extension = strtolower($image->extension() ?: 'jpg');
        $directory = "game-service-orders/{$order->code}/{$type}";
        $path = $image->storeAs($directory, Str::uuid().'.'.$extension, 'local');

        throw_if($path === false, \RuntimeException::class, 'Không thể lưu ảnh xác minh.');

        return $path;
    }
}
