<?php

namespace App\Features\Admin\User\Actions;

use App\Actions\RecordUserLogAction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UpdateUserToolsAction
{
    public function __construct(private readonly RecordUserLogAction $logs) {}

    /** @param list<int> $productIds */
    public function handle(User $user, array $productIds, Request $request): void
    {
        DB::transaction(function () use ($user, $productIds, $request): void {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $user->permittedTools()->sync($productIds);
            $this->logs->handle($user, 'admin_tool_permissions_updated', 'Admin #'.$request->user()->id.' cập nhật quyền tool: '.implode(', ', $productIds), $request);
        }, 3);
    }
}
