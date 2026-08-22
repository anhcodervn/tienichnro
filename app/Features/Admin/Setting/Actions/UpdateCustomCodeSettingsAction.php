<?php

namespace App\Features\Admin\Setting\Actions;

use App\Models\AdminAuditLog;
use App\Models\Setting;
use App\Models\User;
use App\Support\SettingStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UpdateCustomCodeSettingsAction
{
    public function __construct(private readonly SettingStore $settingStore) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{custom_css: string, custom_css_enabled: bool, custom_js: string, custom_js_enabled: bool}
     */
    public function execute(User $admin, array $payload, Request $request): array
    {
        return DB::transaction(function () use ($admin, $payload, $request): array {
            $defaults = [
                'custom_css' => '',
                'custom_css_enabled' => false,
                'custom_js' => '',
                'custom_js_enabled' => false,
            ];
            $before = $this->settingStore->getMany($defaults);

            if ($payload !== []) {
                $this->settingStore->putMany($payload);
            }

            /** @var array{custom_css: string, custom_css_enabled: bool, custom_js: string, custom_js_enabled: bool} $after */
            $after = $this->settingStore->getMany($defaults);

            if ($before !== $after) {
                $subjectId = (int) (Setting::query()
                    ->whereIn('key', array_keys($payload))
                    ->oldest('id')
                    ->value('id') ?? 0);

                AdminAuditLog::query()->create([
                    'admin_id' => $admin->id,
                    'action' => 'custom_code_updated',
                    'subject_type' => Setting::class,
                    'subject_id' => $subjectId,
                    'old_values' => $this->auditSnapshot($before),
                    'new_values' => $this->auditSnapshot($after),
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            }

            return $after;
        });
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, bool|int|string>
     */
    private function auditSnapshot(array $settings): array
    {
        $customCss = (string) ($settings['custom_css'] ?? '');
        $customJs = (string) ($settings['custom_js'] ?? '');

        return [
            'custom_css_enabled' => (bool) ($settings['custom_css_enabled'] ?? false),
            'custom_css_length' => mb_strlen($customCss),
            'custom_css_sha256' => hash('sha256', $customCss),
            'custom_js_enabled' => (bool) ($settings['custom_js_enabled'] ?? false),
            'custom_js_length' => mb_strlen($customJs),
            'custom_js_sha256' => hash('sha256', $customJs),
        ];
    }
}
