<?php

namespace App\Features\Client\GameService\Requests;

use App\Models\GameServiceOrder;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class CancelGameServiceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $order = $this->route('gameServiceOrder');

        return $user instanceof User
            && $order instanceof GameServiceOrder
            && $order->user_id === $user->id;
    }

    /** @return array<string, never> */
    public function rules(): array
    {
        return [];
    }
}
