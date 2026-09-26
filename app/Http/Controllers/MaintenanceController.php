<?php

namespace App\Http\Controllers;

use App\Support\EditorContentRenderer;
use App\Support\SettingStore;
use Symfony\Component\HttpFoundation\Response;

class MaintenanceController extends Controller
{
    public function __invoke(SettingStore $settingStore, EditorContentRenderer $contentRenderer): Response
    {
        $settings = $settingStore->getMany([
            'site_active' => true,
            'site_name' => config('app.name', 'Nạp Carot'),
            'site_description' => '',
            'support_email' => '',
            'hotline' => '',
            'address' => '',
            'light_logo' => '',
            'dark_logo' => '',
            'favicon' => '',
            'system_status_title' => 'Bảo trì hệ thống',
            'system_status_excerpt' => 'Hệ thống đang tạm thời gián đoạn để nâng cấp dịch vụ. Vui lòng quay lại sau ít phút.',
            'system_status_content' => [],
            'system_updates_title' => 'Cập nhật gần đây',
            'system_updates_excerpt' => 'Đội ngũ kỹ thuật đang xử lý để hệ thống sớm hoạt động ổn định trở lại.',
            'system_updates_content' => [],
        ]);

        if ((bool) $settings['site_active']) {
            return redirect()->route('home');
        }

        return response()->view('pages.maintenance.index', [
            'systemSettings' => $settings,
            'pageMetaTitle' => ($settings['system_status_title'] ?: 'Bảo trì hệ thống').' | '.($settings['site_name'] ?: config('app.name', 'Nạp Carot')),
            'pageMetaDescription' => (string) ($settings['system_status_excerpt'] ?: $settings['site_description']),
            'maintenanceStatusHtml' => $contentRenderer->renderNodes(is_array($settings['system_status_content']) ? $settings['system_status_content'] : []),
            'maintenanceUpdatesHtml' => $contentRenderer->renderNodes(is_array($settings['system_updates_content']) ? $settings['system_updates_content'] : []),
        ], 503);
    }
}
