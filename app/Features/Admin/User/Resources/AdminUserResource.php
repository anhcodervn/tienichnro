<?php

namespace App\Features\Admin\User\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class AdminUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role,
            'status' => $this->status === 'banned' ? 'blocked' : $this->status,
            'created_at' => $this->created_at?->toISOString(),
            'last_login_at' => $this->last_login_at?->toISOString(),
        ];
    }
}
