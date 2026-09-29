<?php

namespace App\Features\Client\GameService\Requests;

use App\Features\Topup\Support\RecipientFieldPattern;
use App\Models\GameService;
use App\Models\GameServicePackage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreGameServiceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(RecipientFieldPattern $recipientFieldPattern): array
    {
        $service = $this->route('gameService');
        $fields = $service instanceof GameService && is_array($service->payload_fields) ? $service->payload_fields : [];
        $keys = collect($fields)->pluck('key')->filter()->all();
        $rules = [
            'package_id' => ['required', 'integer'],
            'server_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'email' => [Rule::excludeIf($this->user() !== null), Rule::requiredIf($this->user() === null), 'email:rfc', 'max:255'],
            'payload' => ['required', 'array:'.implode(',', $keys)],
            'note' => ['nullable', 'string', 'max:1000'],
        ];

        foreach ($fields as $field) {
            if (! is_array($field) || blank($field['key'] ?? null)) {
                continue;
            }

            $key = (string) $field['key'];
            $fieldRules = [(bool) ($field['required'] ?? false) ? 'required' : 'nullable'];

            if (($field['type'] ?? 'text') === 'number') {
                $fieldRules[] = 'numeric';
                if (is_numeric($field['min'] ?? null)) {
                    $fieldRules[] = 'min:'.$field['min'];
                }
                if (is_numeric($field['max'] ?? null)) {
                    $fieldRules[] = 'max:'.$field['max'];
                }
            } else {
                $fieldRules[] = 'string';
                $fieldRules[] = 'max:191';
            }

            if (($field['type'] ?? null) === 'select') {
                $fieldRules[] = Rule::in(collect($field['options'] ?? [])->pluck('value')->map(fn (mixed $value): string => (string) $value)->all());
            }

            if (filled($field['regex'] ?? null)) {
                $pattern = (string) $field['regex'];
                $fieldRules[] = function (string $attribute, mixed $value, \Closure $fail) use ($recipientFieldPattern, $pattern): void {
                    if (is_scalar($value) && ! $recipientFieldPattern->matches($pattern, (string) $value)) {
                        $fail('Thông tin đã nhập không đúng định dạng yêu cầu.');
                    }
                };
            }

            $rules["payload.{$key}"] = $fieldRules;
        }

        return $rules;
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $service = $this->route('gameService');

            if (! $service instanceof GameService) {
                return;
            }

            $package = GameServicePackage::query()
                ->whereKey($this->integer('package_id'))
                ->where('game_service_id', $service->id)
                ->active()
                ->with(['prices' => fn ($query) => $query->active()])
                ->first();

            if (! $package instanceof GameServicePackage || $package->prices->isEmpty()) {
                $validator->errors()->add('package_id', 'Gói dịch vụ không tồn tại hoặc đang tạm tắt.');

                return;
            }

            $serverExists = $service->servers()
                ->whereKey($this->integer('server_id'))
                ->where('game_servers.status', 'active')
                ->exists();

            if (! $serverExists) {
                $validator->errors()->add('server_id', 'Máy chủ không nhận dịch vụ này.');
            }

            $price = $package->prices->first();
            $quantity = $this->integer('quantity');
            $minimum = $price->quantity_enabled ? $price->min_quantity : 1;
            $maximum = $price->quantity_enabled ? $price->max_quantity : 1;

            if ($quantity < $minimum || $quantity > $maximum) {
                $validator->errors()->add('quantity', "Số lượng phải từ {$minimum} đến {$maximum}.");
            }
        }];
    }

    public function attributes(): array
    {
        $attributes = [
            'package_id' => 'gói dịch vụ',
            'server_id' => 'máy chủ',
            'quantity' => 'số lượng',
            'email' => 'email',
            'note' => 'ghi chú',
        ];
        $service = $this->route('gameService');

        if ($service instanceof GameService) {
            foreach ($service->payload_fields ?? [] as $field) {
                if (is_array($field) && filled($field['key'] ?? null)) {
                    $attributes['payload.'.(string) $field['key']] = (string) ($field['label'] ?? $field['key']);
                }
            }
        }

        return $attributes;
    }
}
