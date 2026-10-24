<?php

use App\Models\LicenseProduct;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\PersonalAccessToken;

beforeEach(function (): void {
    Queue::fake();
    Storage::fake('local');
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->member = User::factory()->create(['role' => 'user', 'status' => 'active', 'password' => 'Account-password-123']);
    $this->tool = LicenseProduct::factory()->create(['name' => 'My mod', 'image_url' => '/storage/uploads/mod.webp']);
});

test('admin can read grant replace and revoke tools without affecting another account', function (): void {
    $second = LicenseProduct::factory()->create(['is_active' => false]);
    $other = User::factory()->create();
    $other->permittedTools()->attach($this->tool);
    $url = '/api/admin-api/users/'.$this->member->id.'/tools';
    $this->actingAs($this->admin)->getJson($url)->assertOk()->assertJsonCount(2, 'data.tools')->assertJsonPath('data.product_ids', []);
    $this->patchJson($url, ['product_ids' => [$this->tool->id, $second->id]])->assertOk()->assertJsonCount(2, 'data.product_ids');
    $this->patchJson($url, ['product_ids' => [$second->id]])->assertOk()->assertJsonPath('data.product_ids', [$second->id]);
    $this->patchJson($url, ['product_ids' => [$second->id]])->assertOk();
    $this->assertDatabaseCount('license_product_user', 2);
    $this->patchJson($url, ['product_ids' => []])->assertOk()->assertJsonPath('data.product_ids', []);
    expect($other->permittedTools()->sole()->id)->toBe($this->tool->id);
});

test('invalid permission updates never silently revoke existing access', function (string $invalid): void {
    $this->member->permittedTools()->attach($this->tool);
    $data = match ($invalid) {
        'missing' => [], 'null' => ['product_ids' => null], 'unknown' => ['product_ids' => [999999]],
        'duplicate' => ['product_ids' => [$this->tool->id, $this->tool->id]], default => ['product_ids' => ['bad']],
    };
    $this->actingAs($this->admin)->patchJson('/api/admin-api/users/'.$this->member->id.'/tools', $data)->assertUnprocessable();
    expect($this->member->permittedTools()->sole()->id)->toBe($this->tool->id);
})->with(['missing', 'null', 'unknown', 'duplicate', 'invalid']);

test('members cannot view or change admin tool grants and guests cannot access account endpoints', function (): void {
    $url = '/api/admin-api/users/'.$this->member->id.'/tools';
    $this->getJson('/api/account/tools')->assertUnauthorized();
    $this->getJson('/api/auth/session')->assertUnauthorized();
    $this->postJson('/api/auth/logout')->assertUnauthorized();
    $this->actingAs($this->member)->getJson($url)->assertForbidden();
    $this->patchJson($url, ['product_ids' => [$this->tool->id]])->assertForbidden();
    $this->assertDatabaseCount('license_product_user', 0);
});

test('website account logs in by username or email and returns a usable private bearer session', function (string $field): void {
    $this->member->permittedTools()->attach($this->tool);
    LicenseProduct::factory()->create(['name' => 'Unassigned secret tool']);
    $disabled = LicenseProduct::factory()->create(['is_active' => false]);
    $this->member->permittedTools()->attach($disabled);
    $token = $this->postJson('/api/auth/login', ['login' => $this->member->{$field}, 'password' => 'Account-password-123'])
        ->assertOk()->assertJsonPath('token_type', 'Bearer')->assertJsonPath('user.id', $this->member->id)
        ->assertHeader('Cache-Control', 'no-store, private')->json('access_token');
    expect(PersonalAccessToken::findToken($token)->tokenable_id)->toBe($this->member->id);
    auth()->forgetGuards();
    $this->withToken($token)->getJson('/api/auth/session')->assertOk()->assertJsonPath('user.id', $this->member->id);
    auth()->forgetGuards();
    $this->getJson('/api/account/tools')->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'My mod')->assertJsonPath('data.0.image_url', url('/storage/uploads/mod.webp'))
        ->assertJsonMissingPath('data.0.tool_zip_path')->assertJsonMissingPath('data.0.pivot')
        ->assertHeader('Cache-Control', 'no-store, private')->assertDontSee('Unassigned secret tool');
})->with(['username', 'email']);

test('wrong password and blocked accounts never receive API tokens', function (): void {
    $this->postJson('/api/auth/login', ['login' => $this->member->email, 'password' => 'wrong'])->assertUnprocessable();
    $this->member->update(['status' => 'banned']);
    $this->postJson('/api/auth/login', ['login' => $this->member->email, 'password' => 'Account-password-123'])->assertForbidden();
    $this->assertDatabaseCount('personal_access_tokens', 0);
});

test('granted downloads require live permission and return ZIP data with version and checksum', function (): void {
    $this->member->permittedTools()->attach($this->tool);
    $this->tool->forceFill(['latest_version' => '3.0.0', 'tool_zip_path' => 'license-products/full.zip', 'update_zip_path' => 'license-products/update.zip', 'update_zip_sha256' => hash('sha256', 'update'), 'update_zip_size' => 6])->save();
    Storage::disk('local')->put('license-products/full.zip', 'full');
    Storage::disk('local')->put('license-products/update.zip', 'update');
    $token = $this->member->createToken('launcher')->plainTextToken;
    $list = $this->withToken($token)->getJson('/api/account/tools')->assertOk()
        ->assertJsonPath('data.0.latest_version', '3.0.0')->assertJsonPath('data.0.downloads.update.sha256', hash('sha256', 'update'));
    foreach (['tool' => 'full', 'update' => 'update'] as $kind => $content) {
        auth()->forgetGuards();
        $this->get($list->json('data.0.downloads.'.$kind.'.url'), ['Accept' => 'application/json'])->assertOk()
            ->assertDownload($this->tool->product_code.'-'.$kind.'-3.0.0.zip')->assertStreamedContent($content);
    }
    $this->member->permittedTools()->detach($this->tool);
    auth()->forgetGuards();
    $this->getJson('/api/account/tools')->assertOk()->assertJsonPath('data', []);
    auth()->forgetGuards();
    $this->getJson($list->json('data.0.downloads.update.url'))->assertForbidden();
});

test('downloads reject another accounts tool disabled products missing archives and revoked account access', function (string $reason): void {
    $this->member->permittedTools()->attach($this->tool);
    $this->tool->forceFill(['tool_zip_path' => 'license-products/full.zip'])->save();
    if ($reason !== 'missing') {
        Storage::disk('local')->put('license-products/full.zip', 'full');
    }
    if ($reason === 'foreign') {
        $this->member->permittedTools()->detach();
        User::factory()->create()->permittedTools()->attach($this->tool);
    } elseif ($reason === 'disabled') {
        $this->tool->update(['is_active' => false]);
    }
    $token = $this->member->createToken('launcher')->plainTextToken;
    if ($reason === 'blocked') {
        $this->member->update(['status' => 'banned']);
        $this->withToken($token)->getJson('/api/auth/session')->assertForbidden();
        auth()->forgetGuards();
        $this->getJson('/api/account/tools')->assertForbidden();
        auth()->forgetGuards();
    }
    $response = $this->withToken($token)->getJson(route('api.account.tools.download', [$this->tool, 'tool']));
    if ($reason === 'missing') {
        $response->assertNotFound();
    } else {
        $response->assertForbidden();
    }
})->with(['foreign', 'disabled', 'missing', 'blocked']);

test('logout invalidates only the current bearer session and reuse is unauthorized', function (): void {
    $current = $this->member->createToken('current')->plainTextToken;
    $other = $this->member->createToken('other')->plainTextToken;
    $this->withToken($current)->postJson('/api/auth/logout')->assertOk()->assertJsonPath('status', true);
    expect(PersonalAccessToken::findToken($current))->toBeNull()->and(PersonalAccessToken::findToken($other))->not->toBeNull();
    auth()->forgetGuards();
    $this->withToken($current)->getJson('/api/auth/session')->assertUnauthorized();
    auth()->forgetGuards();
    $this->withToken($other)->getJson('/api/auth/session')->assertOk();
});

test('cookie authenticated website account can inspect and end its current session', function (): void {
    $this->withHeader('Origin', 'http://localhost')->actingAs($this->member, 'web')->getJson('/api/auth/session')->assertOk()->assertJsonPath('user.id', $this->member->id);
    $this->postJson('/api/auth/logout')->assertOk();
    $this->assertGuest('web');
});
