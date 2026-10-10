<?php

namespace App\Features\Client\Subscription\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ServicePayloadService
{
    /** @param list<array<string, mixed>> $fields
     * @return array<string, array<mixed>>
     */
    public function rules(array $fields): array
    {
        $rules = ['service_payload' => ['sometimes', 'array', 'max:50', ...($fields === [] ? ['size:0'] : ['array:'.implode(',', array_column($fields, 'name'))])]];
        foreach ($fields as $field) {
            $type = match ($field['type']) {
                'number' => ['numeric', 'between:-1000000000000000,1000000000000000'],
                'boolean' => ['boolean'],
                'email' => ['string', 'email', 'max:254'],
                'select' => ['string', Rule::in(array_column($field['options'] ?? [], 'value'))],
                default => ['string', 'max:2000'],
            };
            $rules['service_payload.'.$field['name']] = [$field['required'] ? 'required' : 'nullable', ...$type];
        }

        return $rules;
    }

    /** @param list<array<string, mixed>> $fields
     * @return array<string, mixed>
     */
    public function validate(array $fields, mixed $payload): array
    {
        $attributes = [];
        foreach ($fields as $field) {
            $attributes['service_payload.'.$field['name']] = $field['label'];
        }

        return Validator::make(['service_payload' => $payload], $this->rules($fields), [], $attributes)->validate()['service_payload'] ?? [];
    }
}
