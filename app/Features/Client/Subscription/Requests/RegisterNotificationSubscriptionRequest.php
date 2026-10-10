<?php

namespace App\Features\Client\Subscription\Requests;

use App\Features\Client\Subscription\Services\ServicePayloadService;
use App\Models\NotificationSubscription;
use App\Models\ServicePackage;
use App\Rules\PublicWebhookUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RegisterNotificationSubscriptionRequest extends FormRequest
{
    /** @var list<array<string, mixed>> */
    private array $payloadFields = [];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if (is_array($this->input('characters'))) {
            $this->merge(['characters' => array_map(fn ($character) => is_array($character) ? [...$character, 'char_name' => is_string($character['char_name'] ?? null) ? trim($character['char_name']) : ($character['char_name'] ?? null)] : $character, $this->input('characters'))]);
        }
    }

    public function rules(): array
    {
        $subscription = $this->route('subscription');
        $packageId = $this->input('package_id');
        $package = is_scalar($packageId) ? ServicePackage::query()->with('service')->find($packageId) : null;
        $mode = $subscription instanceof NotificationSubscription ? $subscription->mode : $package?->notification_mode;
        $personal = $mode === 'personal';
        $this->payloadFields = $package?->service?->payload_fields ?? [];

        return [
            'package_id' => [$subscription ? 'prohibited' : 'required', 'integer', Rule::exists('service_packages', 'id')],
            'request_id' => [$subscription ? 'prohibited' : 'required', 'uuid'],
            'zalo_id' => [$personal ? 'required' : 'prohibited', 'string', 'max:128'],
            'characters' => [$personal ? 'required' : 'prohibited', 'array', 'min:1', 'max:10'],
            'characters.*' => ['array:char_name,char_server'],
            'characters.*.char_name' => ['required', 'string', 'max:100'],
            'characters.*.char_server' => ['required', 'integer', Rule::exists('servers', 'server_code')->where('is_active', true)],
            'notification_types' => [$personal ? 'required' : 'prohibited', 'array', 'min:1', 'max:100'],
            'notification_types.*' => ['required', 'string', 'distinct', Rule::exists('code_notifies', 'code')->whereNull('deleted_at')],
            'webhook_url' => [$mode === 'webhook' ? 'required' : 'prohibited', 'string', 'max:2000', new PublicWebhookUrl],
            ...($subscription ? ['service_payload' => ['prohibited']] : app(ServicePayloadService::class)->rules($this->payloadFields)),
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        $attributes = [];
        foreach ($this->payloadFields as $field) {
            $attributes['service_payload.'.$field['name']] = $field['label'];
        }

        return $attributes;
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $keys = [];
            foreach ((array) $this->input('characters', []) as $character) {
                if (! is_array($character)) {
                    continue;
                }
                $key = mb_strtolower((string) ($character['char_name'] ?? '')).'|'.($character['char_server'] ?? '');
                if (in_array($key, $keys, true)) {
                    $validator->errors()->add('characters', 'Không lặp nhân vật trên cùng server.');
                }
                $keys[] = $key;
            }
        }];
    }
}
