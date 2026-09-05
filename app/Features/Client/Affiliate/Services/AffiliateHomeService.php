<?php

namespace App\Features\Client\Affiliate\Services;

use App\Features\Affiliate\Services\AffiliateProgramService;
use App\Models\AffiliateAnnouncement;
use App\Models\User;
use App\Support\EditorContentRenderer;

class AffiliateHomeService
{
    public function __construct(
        private readonly AffiliateProgramService $programService,
        private readonly EditorContentRenderer $contentRenderer,
    ) {}

    /** @return array<string, mixed> */
    public function data(User $user): array
    {
        $program = $this->programService->enabled();
        abort_unless($program !== null && $program->tenant_id === $user->tenant_id, 404);

        return [
            'announcements' => AffiliateAnnouncement::query()
                ->where('is_published', true)
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now())
                ->orderByDesc('is_pinned')
                ->latest('published_at')
                ->latest('id')
                ->limit(50)
                ->get(['id', 'title', 'content', 'is_pinned', 'published_at'])
                ->map(fn (AffiliateAnnouncement $announcement): array => [
                    'id' => $announcement->id,
                    'title' => $announcement->title,
                    'content_html' => $this->contentRenderer->renderNodes($announcement->content ?? [])->toHtml(),
                    'is_pinned' => $announcement->is_pinned,
                    'published_at' => $announcement->published_at?->toISOString(),
                ])
                ->all(),
        ];
    }
}
