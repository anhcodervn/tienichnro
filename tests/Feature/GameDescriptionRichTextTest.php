<?php

use App\Models\Game;
use App\Models\User;
use App\Support\RichTextSanitizer;

test('game description editor uploads embedded images before saving', function (): void {
    $source = file_get_contents(resource_path('js/pages/admin/topup/catalog/index.vue'));

    expect($source)
        ->toContain("import Editor from '@/components/shared/Editor/index.vue'")
        ->toContain("import { uploadEditorImagesInHtml } from '@/utils/editor-image-upload'")
        ->toContain('ref="descriptionEditor"')
        ->toContain('v-model="form.description"')
        ->toContain('format="html"')
        ->toContain('descriptionEditor.value?.flush()')
        ->toContain('uploadEditorImagesInHtml(latestDescription)')
        ->not->toContain('ref="descriptionEditor" v-model="form.description" format="html" :allow-images="false"');
});

test('game rich description is sanitized and rendered with image viewer', function (): void {
    $admin = User::factory()->create(['role' => 'admin']);
    $description = <<<'HTML'
<h2 style="color: #15803d" onclick="alert(1)">Hướng dẫn</h2>
<p>Nội dung <strong>nổi bật</strong>.</p>
<img src="/storage/editor/game.webp" alt="Ảnh game" onerror="alert(1)">
<img src="https://evil.example/image.webp" alt="Ảnh ngoài">
<script>alert('xss')</script>
HTML;

    $response = $this->actingAs($admin)->postJson('/api/admin-api/games', [
        'name' => 'Game Rich Text',
        'slug' => 'game-rich-text',
        'reward_label' => 'Xu',
        'description' => $description,
        'checkout_fields' => [[
            'key' => 'account_id',
            'label' => 'ID tài khoản',
            'placeholder' => 'Nhập ID',
            'required' => true,
            'regex' => '',
        ]],
        'status' => 'active',
        'sort_order' => 1,
    ])->assertCreated();

    $game = Game::query()->findOrFail($response->json('data.id'));

    expect($game->description)
        ->toContain('<h2 style="color: #15803d">Hướng dẫn</h2>')
        ->toContain('<img src="/storage/editor/game.webp" alt="Ảnh game">')
        ->not->toContain('onclick')
        ->not->toContain('onerror')
        ->not->toContain('evil.example')
        ->not->toContain('<script');

    $this->get(route('topup.game', ['game' => $game]))
        ->assertOk()
        ->assertSee('data-game-description="'.$game->id.'"', false)
        ->assertSee('data-client-image-viewer', false)
        ->assertSee('src="/storage/editor/game.webp"', false)
        ->assertSee('Hướng dẫn')
        ->assertDontSee('onclick=', false)
        ->assertDontSee('evil.example', false)
        ->assertDontSee('&lt;h2', false);
});

test('rich text sanitizer accepts only local image paths', function (): void {
    $sanitized = app(RichTextSanitizer::class)->sanitize(
        '<p style="text-align: center; background-image: url(javascript:alert(1))">Text</p>'.
        '<img src="/storage/editor/local.webp"><img src="data:image/png;base64,abc"><img src="//evil.test/x.webp">',
    );

    expect($sanitized)
        ->toContain('style="text-align: center"')
        ->toContain('src="/storage/editor/local.webp"')
        ->not->toContain('javascript')
        ->not->toContain('data:image')
        ->not->toContain('evil.test');
});
