<?php

namespace App\Features\NroGameData\Requests;

use App\Features\NroGameData\Services\GameDataCatalogService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreGameDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    protected function prepareForValidation(): void
    {
        $raw = json_decode($this->getContent(), true);
        if ($this->isJson() && is_array($raw) && array_key_exists('data', $raw)) {
            $this->merge(['data' => $raw['data']]);
        }
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $dataset = $this->route('dataset');
        $fields = app(GameDataCatalogService::class)->definition($dataset)['fields'];
        $rules = [
            'accountId' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'serverCode' => ['sometimes', 'nullable', 'integer', 'between:0,4294967295'],
            'version' => ['sometimes', 'nullable', 'integer', 'between:0,4294967295'],
            'updatedAt' => ['sometimes', 'nullable', 'date'],
            'data' => ['present', 'array', 'list', 'max:5000'],
        ];
        $prefix = 'data.*.';
        if ($dataset === 'classes-and-skills') {
            $rules['data'] = ['present', 'array', 'list', 'max:50'];
            $rules['data.*'] = ['required', 'array:classId,name,skills'];
            $rules['data.*.classId'] = ['required', 'integer', 'between:0,4294967295', 'distinct'];
            $rules['data.*.name'] = ['required', 'string', 'max:255'];
            $rules['data.*.skills'] = ['present', 'array', 'list', 'max:200'];
            $rules['data.*.skills.*'] = ['required', 'array:'.implode(',', array_keys($fields))];
            $prefix = 'data.*.skills.*.';
        } else {
            $rules['data.*'] = ['required', 'array:'.implode(',', array_keys($fields))];
        }
        foreach ($fields as $key => $field) {
            $rules[$prefix.$key] = match ($field['type']) {
                'id' => ['required', 'integer', 'between:0,4294967295'],
                'int' => ['required', 'integer', 'between:-2147483648,2147483647'],
                'big' => ['required', 'integer', 'min:0'],
                'bool' => ['required', 'boolean'],
                'name' => ['required', 'string', 'max:255'],
                'text_name' => ['present', 'string', 'max:255'],
                'text' => ['present', 'string', 'max:65535'],
                'menu' => ['present', 'array', 'list', 'max:100'],
                'levels' => ['present', 'array', 'list', 'max:100'],
            };
            if ($field['type'] === 'id' && $dataset !== 'classes-and-skills') {
                $rules[$prefix.$key][] = 'distinct';
            }
        }
        if ($dataset === 'npcs') {
            $rules['data.*.menu.*'] = ['array', 'list', 'max:100'];
            $rules['data.*.menu.*.*'] = ['present', 'string', 'max:4096'];
        }
        if ($dataset === 'classes-and-skills') {
            $levels = $prefix.'levels.*.';
            $keys = ['skillId', 'point', 'powRequire', 'manaUse', 'coolDown', 'dx', 'dy', 'maxFight', 'damage', 'price', 'moreInfo'];
            $rules[$prefix.'levels.*'] = ['required', 'array:'.implode(',', $keys)];
            foreach ($keys as $key) {
                $rules[$levels.$key] = $key === 'moreInfo'
                    ? ['present', 'string', 'max:65535']
                    : ['required', 'integer', 'min:0'];
            }
        }

        return $rules;
    }

    /** @return list<\Closure> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || $this->route('dataset') !== 'classes-and-skills') {
                return;
            }
            $total = 0;
            foreach ($this->input('data') as $index => $class) {
                $ids = array_map(fn (array $skill): int => (int) $skill['id'], $class['skills']);
                $total += count($ids);
                if (count($ids) !== count(array_unique($ids))) {
                    $validator->errors()->add("data.$index.skills", 'Duplicate skill IDs within a class.');
                }
                foreach ($class['skills'] as $skillIndex => $skill) {
                    $levelIds = array_map(fn (array $level): int => (int) $level['skillId'], $skill['levels']);
                    if (count($levelIds) !== count(array_unique($levelIds))) {
                        $validator->errors()->add("data.$index.skills.$skillIndex.levels", 'Duplicate skill level IDs.');
                    }
                }
            }
            if ($total > 5000) {
                $validator->errors()->add('data', 'A request may contain at most 5000 skills.');
            }
        }];
    }
}
