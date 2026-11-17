<?php

namespace App\Features\Client\GameImage\Requests;

use App\Models\GameImageAccount;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateGameImageLayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        $account = $this->route('account');

        return $this->user()?->status === 'active' && $account instanceof GameImageAccount && $account->user_id === $this->user()->id;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $groupIds = array_map(fn (int $index): string => 'featured-'.$index, range(1, 10));
        $textIds = array_map(fn (int $index): string => 'text-'.$index, range(1, 10));
        $shapeIds = array_map(fn (int $index): string => 'shape-'.$index, range(1, 10));
        $imageIds = array_map(fn (int $index): string => 'image-'.$index, range(1, 3));
        $positionKeys = ['panel-quest', 'panel-inventory', 'equipment', 'bag', 'box', 'box-84', 'panel-skills', 'panel-tools', 'panel-pet', 'npc-4-magic-tree', 'npc-50-egg', 'panel-amulets', 'panel-radar', 'featured', ...$groupIds, ...$textIds, ...$shapeIds, ...$imageIds];
        $rules = [
            'preview' => ['sometimes', 'string', 'max:12000000', function (string $attribute, mixed $value, Closure $fail): void {
                $bytes = str_starts_with($value, 'data:image/png;base64,') ? base64_decode(substr($value, 22), true) : false;
                $size = $bytes === false ? false : @getimagesizefromstring($bytes);
                if ($bytes === false || strlen($bytes) > 8388608 || ! $size || ($size['mime'] ?? '') !== 'image/png' || $size[0] * $size[1] > 32000000) {
                    $fail('Ảnh ghép phải là PNG, tối đa 8 MB và 32 triệu điểm ảnh.');
                }
            }],
            'layout' => ['required', 'array:item_ids,show_details,positions,groups,texts,layers,order,document,shapes,images'],
            'layout.item_ids' => ['present', 'array', 'list', 'max:85'],
            'layout.item_ids.*' => ['integer', 'distinct', Rule::in(range(1, 85))],
            'layout.show_details' => ['required', 'boolean'],
            'layout.positions' => ['present', 'array:'.implode(',', $positionKeys)],
            'layout.groups' => ['sometimes', 'array', 'list', 'max:10'],
            'layout.groups.*' => ['array:id,name,item_ids,show_details,columns', 'required_array_keys:id,item_ids,show_details,columns'],
            'layout.groups.*.id' => ['required', 'string', 'distinct', Rule::in($groupIds)],
            'layout.groups.*.name' => ['sometimes', 'nullable', 'string', 'max:60'],
            'layout.groups.*.item_ids' => ['present', 'array', 'list', 'max:85'],
            'layout.groups.*.item_ids.*' => ['integer', Rule::in(range(1, 85))],
            'layout.groups.*.show_details' => ['required', 'boolean'],
            'layout.groups.*.columns' => ['required', 'integer', 'between:1,6'],
            'layout.texts' => ['sometimes', 'array', 'list', 'max:10'],
            'layout.texts.*' => ['array:id,content,font_size,color,bold,width', 'required_array_keys:id,content,font_size,color,bold,width'],
            'layout.texts.*.id' => ['required', 'string', 'distinct', Rule::in($textIds)],
            'layout.texts.*.content' => ['present', 'nullable', 'string', 'max:1000'],
            'layout.texts.*.font_size' => ['required', 'integer', 'between:12,72'],
            'layout.texts.*.color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'layout.texts.*.bold' => ['required', 'boolean'],
            'layout.texts.*.width' => ['required', 'integer', 'between:120,1000'],
            'layout.document' => ['sometimes', 'array:cname,background,width,height', 'required_array_keys:cname,background'],
            'layout.document.width' => ['required_with:layout.document.height', 'integer', 'between:64,6000'],
            'layout.document.height' => ['required_with:layout.document.width', 'integer', 'between:64,6000'],
            'layout.document.cname' => ['nullable', 'string', 'max:100'],
            'layout.document.background' => ['required_with:layout.document', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'layout.order' => ['sometimes', 'array', 'list', 'max:50'],
            'layout.order.*' => ['string', 'distinct', Rule::in($positionKeys)],
            'layout.layers' => ['sometimes', 'array:'.implode(',', $positionKeys)],
            'layout.shapes' => ['sometimes', 'array', 'list', 'max:10'],
            'layout.shapes.*' => ['array:id,kind,width,height,fill,stroke,stroke_width', 'required_array_keys:id,kind,width,height,fill,stroke,stroke_width'],
            'layout.shapes.*.id' => ['required', 'distinct', Rule::in($shapeIds)],
            'layout.shapes.*.kind' => ['required', Rule::in(['rectangle', 'ellipse'])],
            'layout.shapes.*.width' => ['required', 'integer', 'between:20,2000'],
            'layout.shapes.*.height' => ['required', 'integer', 'between:20,2000'],
            'layout.shapes.*.fill' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'layout.shapes.*.stroke' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'layout.shapes.*.stroke_width' => ['required', 'integer', 'between:0,12'],
            'layout.images' => ['sometimes', 'array', 'list', 'max:3'],
            'layout.images.*' => ['array:id,name,src,width,height', 'required_array_keys:id,name,src,width,height'],
            'layout.images.*.id' => ['required', 'distinct', Rule::in($imageIds)],
            'layout.images.*.name' => ['required', 'string', 'max:100'],
            'layout.images.*.width' => ['required', 'integer', 'between:1,1600'],
            'layout.images.*.height' => ['required', 'integer', 'between:1,1600'],
            'layout.images.*.src' => ['bail', 'required', 'string', 'max:1400000', $this->imageRule()],
        ];
        foreach ($positionKeys as $key) {
            $rules['layout.positions.'.$key] = ['sometimes', 'array:x,y', 'required_array_keys:x,y'];
            $rules['layout.positions.'.$key.'.x'] = ['required_with:layout.positions.'.$key, 'integer', 'between:0,6000'];
            $rules['layout.positions.'.$key.'.y'] = ['required_with:layout.positions.'.$key, 'integer', 'between:48,6000'];
            $rules['layout.layers.'.$key] = ['sometimes', 'array:hidden,locked,opacity,scale,scale_x,scale_y,rotation,name'];
            $rules['layout.layers.'.$key.'.hidden'] = ['sometimes', 'boolean'];
            $rules['layout.layers.'.$key.'.locked'] = ['sometimes', 'boolean'];
            $rules['layout.layers.'.$key.'.opacity'] = ['sometimes', 'numeric', 'between:0,1'];
            $rules['layout.layers.'.$key.'.scale'] = ['sometimes', 'numeric', 'between:0.2,3'];
            $rules['layout.layers.'.$key.'.scale_x'] = ['sometimes', 'numeric', 'between:0.2,3'];
            $rules['layout.layers.'.$key.'.scale_y'] = ['sometimes', 'numeric', 'between:0.2,3'];
            $rules['layout.layers.'.$key.'.rotation'] = ['sometimes', 'numeric', 'between:-180,180'];
            $rules['layout.layers.'.$key.'.name'] = ['sometimes', 'nullable', 'string', 'max:60'];
        }

        return $rules;
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            if ((int) $this->input('layout.document.width', 0) * (int) $this->input('layout.document.height', 0) > 32000000) {
                $validator->errors()->add('layout.document.width', 'Canvas t?i ?a 32 tri?u ?i?m ?nh.');
            }
            foreach ($this->input('layout.groups', []) as $index => $group) {
                if (count($group['item_ids']) !== count(array_unique($group['item_ids'], SORT_REGULAR))) {
                    $validator->errors()->add('layout.groups.'.$index.'.item_ids', 'ID item trong mỗi khối không được trùng.');
                }
            }
        }];
    }

    /** @return array<string, mixed> */
    public function layout(): array
    {
        $layout = [
            'item_ids' => array_map(intval(...), $this->validated('layout.item_ids')),
            'show_details' => $this->boolean('layout.show_details'),
            'positions' => array_map(fn (array $position): array => ['x' => (int) $position['x'], 'y' => (int) $position['y']], $this->validated('layout.positions')),
        ];
        if ($this->has('layout.groups')) {
            $layout['groups'] = array_map(fn (array $group): array => [
                'id' => $group['id'],
                ...(array_key_exists('name', $group) ? ['name' => trim((string) $group['name'])] : []),
                'item_ids' => array_map(intval(...), $group['item_ids']),
                'show_details' => filter_var($group['show_details'], FILTER_VALIDATE_BOOLEAN),
                'columns' => (int) $group['columns'],
            ], $this->validated('layout.groups'));
        }

        if ($this->has('layout.texts')) {
            $layout['texts'] = array_map(fn (array $text): array => [
                'id' => $text['id'], 'content' => (string) $text['content'], 'font_size' => (int) $text['font_size'],
                'color' => $text['color'], 'bold' => filter_var($text['bold'], FILTER_VALIDATE_BOOLEAN), 'width' => (int) $text['width'],
            ], $this->validated('layout.texts'));
        }
        foreach (['order', 'shapes', 'images', 'document', 'layers'] as $field) {
            if ($this->has('layout.'.$field)) {
                $layout[$field] = $this->validated('layout.'.$field);
            }
        }
        foreach ($layout['layers'] ?? [] as $key => $layer) {
            foreach (['hidden', 'locked'] as $field) {
                if (array_key_exists($field, $layer)) {
                    $layout['layers'][$key][$field] = filter_var($layer[$field], FILTER_VALIDATE_BOOLEAN);
                }
            }
            foreach (['opacity', 'scale', 'scale_x', 'scale_y', 'rotation'] as $field) {
                if (array_key_exists($field, $layer)) {
                    $layout['layers'][$key][$field] = (float) $layer[$field];
                }
            }
        }
        foreach (['width', 'height'] as $field) {
            if (isset($layout['document'][$field])) {
                $layout['document'][$field] = (int) $layout['document'][$field];
            }
        }
        foreach (['shapes' => ['width', 'height', 'stroke_width'], 'images' => ['width', 'height']] as $collection => $fields) {
            foreach ($layout[$collection] ?? [] as $index => $item) {
                foreach ($fields as $field) {
                    $layout[$collection][$index][$field] = (int) $item[$field];
                }
            }
        }

        return $layout;
    }

    private function imageRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! preg_match('/\Adata:image\/(png|jpeg|webp);base64,([A-Za-z0-9+\/]+={0,2})\z/', $value, $matches)) {
                $fail('Ảnh phải là PNG, JPEG hoặc WebP.');

                return;
            }
            $bytes = base64_decode($matches[2], true);
            $size = $bytes === false ? false : @getimagesizefromstring($bytes);
            if ($bytes === false || strlen($bytes) > 1048576 || ! $size || $size[0] > 4096 || $size[1] > 4096 || ($size['mime'] ?? '') !== 'image/'.$matches[1]) {
                $fail('Ảnh không hợp lệ hoặc vượt quá 1 MB / 4096 px.');
            }
        };
    }
}
