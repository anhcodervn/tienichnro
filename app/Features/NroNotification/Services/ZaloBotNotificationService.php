<?php

namespace App\Features\NroNotification\Services;

use App\Models\NroZaloEquipmentSend;
use App\Models\ZaloReceiveNotification;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

class ZaloBotNotificationService
{
    /** @return array{kind: string, char_name: string, item: string, location: string|null}|null */
    public function parse(string $content): ?array
    {
        $text = NotificationTypeClassifierService::normalizeText($content);
        if (preg_match('/\A(?<char>\S+) vừa đánh quái may mắn nhận được (?<item>1 trang bị set kích hoạt(?: [^\r\n]+)?)\z/u', $text, $matches) === 1) {
            return ['kind' => 'set', 'char_name' => $matches['char'], 'item' => $matches['item'], 'location' => null];
        }
        if (preg_match('/\A(?<char>\S+) vừa nhặt được (?<item>(?:[^\r\n]+ )?thần linh) tại (?<location>.+)\z/u', $text, $matches) === 1) {
            return ['kind' => 'god', 'char_name' => $matches['char'], 'item' => $matches['item'], 'location' => $matches['location']];
        }

        return null;
    }

    /** @param array{kind: string, char_name: string, item: string, location: string|null} $equipment */
    public function fingerprint(array $equipment, int $serverIdentifier): string
    {
        return hash('sha256', json_encode([
            $serverIdentifier, $equipment['kind'],
            NotificationTypeClassifierService::normalizeText($equipment['char_name']),
            NotificationTypeClassifierService::normalizeText($equipment['item']),
            NotificationTypeClassifierService::normalizeText($equipment['location'] ?? ''),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    /** @param array{kind: string, char_name: string, item: string, location: string|null} $equipment */
    public function sendNotiZalo(array $equipment, int $serverCode, string $serverName): void
    {
        if (! config('services.zalo_bot.enabled')) {
            return;
        }
        $authorization = trim((string) config('services.zalo_bot.authorization'));
        $boxId = (string) config('services.zalo_bot.box_id');
        if ($authorization === '' || $boxId === '') {
            throw new \RuntimeException('Zalo bot authorization and box ID must be configured.');
        }
        $mentions = ZaloReceiveNotification::query()->where('char_server', $serverCode)
            ->whereRaw('LOWER(char_name) = ?', [$equipment['char_name']])->pluck('zalo_id')->unique()->values()->all();
        $title = $equipment['kind'] === 'set' ? 'SÉT KÍCH HOẠT' : 'ĐỒ THẦN LINH';
        $lines = ['🐉✨ [ '.$title.' ] ✨🐉'];
        if ($mentions !== []) {
            $lines[] = '🎉 Chúc mừng: '.implode(', ', $mentions);
        }
        $lines[] = '👑 Nhân vật: '.$equipment['char_name'];
        $lines[] = '🌍 Máy chủ: '.$serverName;
        $lines[] = $equipment['kind'] === 'set' ? '⚔️ Đánh quái may mắn nhận được:' : '⚔️ Trang bị nhặt được:';
        $item = $equipment['kind'] === 'set' ? preg_replace('/\A1 /u', '01 ', $equipment['item']) : $equipment['item'];
        $lines[] = '🎁🔥 '.mb_strtoupper($item, 'UTF-8').' 🔥🎁';
        if ($equipment['location'] !== null) {
            $lines[] = '📍 Vị trí: '.mb_convert_case($equipment['location'], MB_CASE_TITLE, 'UTF-8');
        }
        array_push($lines, '━━━━━━━━━━━━━━━━', '🍀✨ Nhân phẩm bùng nổ! 💎🎊');
        $fingerprint = $this->fingerprint($equipment, $serverCode);
        $claimed = NroZaloEquipmentSend::query()->insertOrIgnore([
            'fingerprint' => $fingerprint, 'status' => 'sending', 'created_at' => now(), 'updated_at' => now(),
        ]);
        if (! $claimed) {
            return;
        }
        try {
            Http::withHeaders(['Authorization' => $authorization])->acceptJson()->asJson()
                ->withOptions(['allow_redirects' => false])->connectTimeout(5)->timeout(15)
                ->post(config('services.zalo_bot.messages_url'), [
                    'accountId' => (int) config('services.zalo_bot.account_id'),
                    'recipientId' => $boxId,
                    ...($mentions !== [] ? ['mentionUserIds' => $mentions] : []),
                    'recipientType' => config('services.zalo_bot.recipient_type'),
                    'message' => implode("\n", $lines),
                ])->throw();
            NroZaloEquipmentSend::query()->whereKey($fingerprint)->update(['status' => 'sent', 'sent_at' => now()]);
        } catch (Throwable $exception) {
            NroZaloEquipmentSend::query()->whereKey($fingerprint)->update([
                'status' => $exception instanceof ConnectionException ? 'uncertain' : 'failed',
            ]);
            throw $exception;
        }
    }
}
