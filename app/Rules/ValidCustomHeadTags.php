<?php

namespace App\Rules;

use App\Support\CustomHeadTags;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class ValidCustomHeadTags implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! is_string($value) || (new CustomHeadTags)->sanitize($value) === '') {
            $fail('Chỉ chấp nhận tối đa 20 thẻ meta có thuộc tính name hoặc property và content.');
        }
    }
}
