<?php

use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\User;

test('guests must sign in before opening agency onboarding pages', function (string $path): void {
    $this->get("http://napcarot.com{$path}")->assertRedirect();
})->with([
    '/dai-ly/tao-website',
    '/dai-ly/ket-noi-api',
]);

test('main site users see both agency options and their onboarding content', function (): void {
    $mainTenant = Tenant::query()->where('is_main', true)->firstOrFail();
    $user = User::factory()->create(['tenant_id' => $mainTenant->id]);

    $this->actingAs($user)
        ->get('http://napcarot.com/')
        ->assertSuccessful()
        ->assertSeeText('Tạo website đại lý')
        ->assertSeeText('Kết nối API');

    $this->actingAs($user)
        ->get('http://napcarot.com/dai-ly/tao-website')
        ->assertSuccessful()
        ->assertSeeText('Kinh doanh bằng thương hiệu và domain của bạn')
        ->assertSee('/chat', false);

    $this->actingAs($user)
        ->get('http://napcarot.com/dai-ly/ket-noi-api')
        ->assertSuccessful()
        ->assertSeeText('Tích hợp nạp game vào website hoặc phần mềm của bạn')
        ->assertSee('/api/v1/orders', false)
        ->assertSeeText('cURL request')
        ->assertSeeText('Response 201 — tạo đơn thành công')
        ->assertSeeText('Response 200 — hoàn tất')
        ->assertSee('X-API-KEY: YOUR_API_KEY', false)
        ->assertSee('&quot;sale_price&quot;: 8500', false)
        ->assertDontSee('+  --header', false)
        ->assertSee('/tai-khoan/api-key', false)
        ->assertSeeText('Lỗi và HTTP status');
});

test('child site users can connect api but cannot request another managed website', function (): void {
    $tenant = Tenant::factory()->create(['name' => 'Child Shop']);
    TenantDomain::factory()->for($tenant)->create(['domain' => 'child-shop.test']);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($user)
        ->get('http://child-shop.test/')
        ->assertSuccessful()
        ->assertDontSeeText('Tạo website đại lý')
        ->assertSeeText('Kết nối API');

    $this->actingAs($user)
        ->get('http://child-shop.test/dai-ly/tao-website')
        ->assertNotFound();

    $this->actingAs($user)
        ->get('http://child-shop.test/dai-ly/ket-noi-api')
        ->assertSuccessful();
});

test('agency pages only use icons available in the bundled boxicons font', function (): void {
    $iconStyles = file_get_contents(public_path('assets/icon/boxicons/fonts/basic/boxicons.min.css'));
    $viewSources = collect([
        resource_path('views/client/agency/website.blade.php'),
        resource_path('views/client/agency/api.blade.php'),
        resource_path('views/components/client/api-documentation.blade.php'),
        resource_path('views/components/client/api-code-block.blade.php'),
    ])->map(fn (string $path): string => (string) file_get_contents($path))->implode("\n");

    preg_match_all('/\bbx-[a-z0-9-]+\b/', $viewSources, $matches);

    foreach (array_unique($matches[0]) as $icon) {
        expect($iconStyles)->toContain(".{$icon}:before");
    }

    foreach (['bx-store-alt', 'bx-code'] as $menuIcon) {
        expect($iconStyles)->toContain(".{$menuIcon}:before");
    }
});
