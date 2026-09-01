<?php

namespace App\Features\Admin\Topup\Requests;

use App\Models\GlobalTopupPackage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class UpdateGlobalTopupPackageRequest extends StoreGlobalTopupPackageRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var GlobalTopupPackage|null $globalPackage */
        $globalPackage = $this->route('globalTopupPackage');

        return [
            ...parent::rules(),
            'code' => [
                'required', 'string', 'max:80', 'regex:/^[a-z0-9][a-z0-9_-]*$/',
                Rule::unique(GlobalTopupPackage::class, 'code')->ignore($globalPackage),
            ],
            'denomination' => [
                'required', 'integer', 'min:1', 'max:999999999999',
                Rule::unique(GlobalTopupPackage::class, 'denomination')->ignore($globalPackage),
            ],
        ];
    }
}
