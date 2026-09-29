<?php

namespace App\Features\Admin\GameService\Requests;

use App\Features\Topup\Support\RecipientFieldPattern;
use App\Models\Game;
use App\Models\GameServer;
use App\Models\GameService;
use App\Rules\ValidSeoPostContent;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreGameServiceRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! is_array($this->input('payload_fields'))) {
            return;
        }

        $this->merge([
            'payload_fields' => collect($this->input('payload_fields'))
                ->map(fn (mixed $field): mixed => is_array($field) ? [
                    ...$field,
                    'type' => $field['type'] ?? 'text',
                    'options' => is_array($field['options'] ?? null) ? $field['options'] : [],
                    'min' => $field['min'] ?? null,
                    'max' => $field['max'] ?? null,
                    'step' => $field['step'] ?? null,
                ] : $field)
                ->all(),
        ]);
    }

    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(RecipientFieldPattern $recipientFieldPattern): array
    {
        $service = $this->route('gameService');
        $serviceId = $service instanceof GameService ? $service->id : null;

        return [
            'game_id' => ['required', 'integer', Rule::exists(Game::class, 'id')],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique(GameService::class, 'slug')->where('game_id', $this->integer('game_id'))->ignore($serviceId)],
            'code' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique(GameService::class, 'code')->where('game_id', $this->integer('game_id'))->ignore($serviceId)],
            'description' => ['nullable', 'string', 'max:5000'],
            'background_image' => ['required', 'string', 'max:2048'],
            'server_ids' => ['required', 'array', 'min:1'],
            'server_ids.*' => ['required', 'integer', 'distinct', Rule::exists(GameServer::class, 'id')],
            'payload_fields' => ['required', 'array', 'min:1', 'max:10'],
            'payload_fields.*' => ['required', 'array:key,label,placeholder,required,regex,type,options,min,max,step'],
            'payload_fields.*.key' => ['required', 'string', 'max:40', 'regex:/^[a-z][a-z0-9_]*$/', 'distinct'],
            'payload_fields.*.label' => ['required', 'string', 'max:80'],
            'payload_fields.*.placeholder' => ['nullable', 'string', 'max:120'],
            'payload_fields.*.required' => ['required', 'boolean'],
            'payload_fields.*.type' => ['required', Rule::in(['text', 'number', 'password', 'select'])],
            'payload_fields.*.options' => ['present', 'array', 'max:50', 'prohibited_unless:payload_fields.*.type,select'],
            'payload_fields.*.options.*' => ['required', 'array:value,text'],
            'payload_fields.*.options.*.value' => ['required', 'string', 'max:191'],
            'payload_fields.*.options.*.text' => ['required', 'string', 'max:120'],
            'payload_fields.*.min' => ['nullable', 'numeric', 'prohibited_unless:payload_fields.*.type,number'],
            'payload_fields.*.max' => ['nullable', 'numeric', 'prohibited_unless:payload_fields.*.type,number'],
            'payload_fields.*.step' => ['nullable', 'numeric', 'gt:0', 'prohibited_unless:payload_fields.*.type,number'],
            'payload_fields.*.regex' => [
                'nullable', 'string', 'max:500',
                function (string $attribute, mixed $value, Closure $fail) use ($recipientFieldPattern): void {
                    if (is_string($value) && $value !== '' && ! $recipientFieldPattern->isValid($value)) {
                        $fail('Regex không hợp lệ. Hãy nhập nội dung pattern và không kèm dấu /.');
                    }
                },
            ],
            'seo_content' => ['nullable', 'array', new ValidSeoPostContent],
            'faqs' => ['nullable', 'array', 'max:20'],
            'faqs.*' => ['required', 'array:question,answer'],
            'faqs.*.question' => ['required', 'string', 'max:255'],
            'faqs.*.answer' => ['required', 'string', 'max:2000'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'sort_order' => ['required', 'integer', 'min:0'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $serverGameIds = GameServer::query()
                ->whereKey($this->input('server_ids', []))
                ->pluck('game_id')
                ->unique();

            if ($serverGameIds->count() !== 1 || $serverGameIds->first() !== $this->integer('game_id')) {
                $validator->errors()->add('server_ids', 'Tất cả máy chủ phải thuộc game đã chọn.');
            }

            foreach ($this->input('payload_fields', []) as $index => $field) {
                if (! is_array($field)) {
                    continue;
                }

                if (($field['type'] ?? null) === 'select' && empty($field['options'])) {
                    $validator->errors()->add("payload_fields.{$index}.options", 'Trường select phải có ít nhất một lựa chọn.');
                }

                if (($field['type'] ?? null) === 'number' && is_numeric($field['min'] ?? null) && is_numeric($field['max'] ?? null) && (float) $field['min'] > (float) $field['max']) {
                    $validator->errors()->add("payload_fields.{$index}.max", 'Giá trị tối đa phải lớn hơn hoặc bằng giá trị tối thiểu.');
                }
            }
        }];
    }
}
