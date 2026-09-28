<?php

use App\Features\Admin\Upload\Requests\ImportImageUploadRequest;
use App\Features\Admin\Upload\Requests\StoreImageUploadRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

uses(TestCase::class);

test('image upload request rejects non-image content', function (): void {
    $request = new StoreImageUploadRequest;
    $validator = Validator::make([
        'image' => 'not-an-image',
    ], $request->rules(), $request->messages(), $request->attributes());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('image'))->toBeTrue();
});

test('remote image import request only accepts http and https urls', function (string $url, bool $shouldFail): void {
    $request = new ImportImageUploadRequest;
    $validator = Validator::make(['url' => $url], $request->rules(), $request->messages(), $request->attributes());

    expect($validator->fails())->toBe($shouldFail);
})->with([
    'https image' => ['https://cdn.example.com/photo.png', false],
    'http image' => ['http://cdn.example.com/photo.png', false],
    'local path' => ['/storage/photo.png', true],
    'javascript' => ['javascript:alert(1)', true],
]);
