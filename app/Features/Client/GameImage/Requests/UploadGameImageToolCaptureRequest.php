<?php

namespace App\Features\Client\GameImage\Requests;

class UploadGameImageToolCaptureRequest extends ClaimGameImageCaptureRequest
{
    public function rules(): array
    {
        return [...parent::rules(),
            'cName' => ['required', 'string', 'max:100', 'regex:/\A[\p{L}\p{N}_-]+\z/u'],
            'package' => ['required', 'file', 'mimes:zip', 'max:32768'],
        ];
    }
}
