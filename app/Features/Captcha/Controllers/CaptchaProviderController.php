<?php

namespace App\Features\Captcha\Controllers;

use App\Features\Captcha\Requests\SaveCaptchaProviderRequest;
use App\Features\Captcha\Services\CaptchaProviderConfigurationService;
use App\Http\Controllers\Controller;
use App\Models\CaptchaProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class CaptchaProviderController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(CaptchaProvider::query()->orderByDesc('is_enabled')->orderBy('name')->paginate(20))
            ->header('Cache-Control', 'no-store, private');
    }

    public function store(SaveCaptchaProviderRequest $request, CaptchaProviderConfigurationService $configuration): JsonResponse
    {
        $values = $request->safe()->except(['clear_api_key']);
        $values['code'] = (string) Str::uuid();

        return response()->json(['data' => $configuration->save($values)], 201)
            ->header('Cache-Control', 'no-store, private');
    }

    public function update(SaveCaptchaProviderRequest $request, CaptchaProvider $provider, CaptchaProviderConfigurationService $configuration): JsonResponse
    {
        $values = $request->safe()->except(['api_key', 'clear_api_key']);
        if ($request->filled('api_key')) {
            $values['api_key'] = $request->validated('api_key');
        } elseif ($request->boolean('clear_api_key')) {
            $values['api_key'] = null;
        }

        return response()->json(['data' => $configuration->save($values, $provider)])->header('Cache-Control', 'no-store, private');
    }

    public function destroy(CaptchaProvider $provider, CaptchaProviderConfigurationService $configuration): Response
    {
        $configuration->delete($provider);

        return response()->noContent();
    }
}
