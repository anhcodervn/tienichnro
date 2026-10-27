<?php

namespace App\Features\NroGameData\Controllers;

use App\Features\NroGameData\Requests\BrowseGameDataRequest;
use App\Features\NroGameData\Services\GameDataAssetService;
use App\Features\NroGameData\Services\GameDataBrowseService;
use App\Features\NroGameData\Services\GameDataCatalogService;
use App\Features\NroGameData\Services\GameEffectService;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameDataPageController extends Controller
{
    public function index(BrowseGameDataRequest $request, GameDataBrowseService $service): View
    {
        return view('pages.game-data.index', $service->browse($request->validated()));
    }

    public function show(Request $request, string $dataset, int $record, GameDataCatalogService $catalog, GameEffectService $effects, GameDataBrowseService $browse, GameDataAssetService $assets): View|JsonResponse
    {
        $entry = $catalog->query($dataset)->findOrFail($record);
        $data = [...$entry->source, 'icon' => $entry->icon, 'image' => $entry->image];
        $idColumn = $catalog->identityColumns($dataset);
        $effect = $effects->forRecord($dataset, (int) $entry->{$idColumn[array_key_last($idColumn)]}, $entry->image, $entry->icon);
        if ($effect) {
            $data['effect'] = $effect;
        }
        $preview = $assets->preview($dataset, $entry);
        $previewMedia = $browse->mediaReferences($preview['image'] ?? ($dataset === 'maps' ? $entry->image : $entry->icon), $preview['icon'] ?? ($dataset === 'maps' ? $entry->icon : $entry->image));
        $preview['image'] = $previewMedia[0] ?? null;
        $preview['fallback'] = $previewMedia[1] ?? null;
        if ($request->expectsJson()) {
            return response()->json(['data' => $data, 'preview' => $preview]);
        }

        $media = $browse->mediaReferences($entry->icon, $entry->image);

        return view('pages.game-data.show', ['preview' => $preview, 'data' => $data, 'dataset' => $dataset, 'effect' => $effect, 'name' => $entry->name ?: 'ID '.$record,
            'media' => $media[0] ?? null, 'mediaFallback' => $media[1] ?? null]);
    }
}
