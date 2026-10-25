<?php

namespace App\Features\NroGameData\Controllers;

use App\Features\NroGameData\Requests\BrowseGameDataRequest;
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

    public function show(Request $request, string $dataset, int $record, GameDataCatalogService $catalog, GameEffectService $effects): View|JsonResponse
    {
        $entry = $catalog->query($dataset)->findOrFail($record);
        $data = [...$entry->source, 'icon' => $entry->icon, 'image' => $entry->image];
        $idColumn = $catalog->identityColumns($dataset);
        $effect = $effects->forRecord($dataset, (int) $entry->{$idColumn[array_key_last($idColumn)]});
        if ($effect) {
            $data['effect'] = $effect;
        }
        if ($request->expectsJson()) {
            return response()->json(['data' => $data]);
        }

        return view('pages.game-data.show', ['data' => $data, 'dataset' => $dataset, 'effect' => $effect, 'name' => $entry->name ?: 'ID '.$record]);
    }
}
