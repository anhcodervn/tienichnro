<?php

use App\Features\License\Services\LicenseFailure;
use App\Features\License\Services\LicenseSessionService;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$data = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
config(['database.connections.license_test' => $data['connection'], 'license.cache_store' => 'array']);
DB::setDefaultConnection('license_test');
$body = json_encode($data['payload'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$request = Request::create($data['path'], 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], $body);
$request->setUserResolver(fn () => User::query()->find($data['owner_id']));
$payload = $data['payload'];
$message = implode("\n", ['POST', $data['path'], hash('sha256', $body), (string) $payload['timestamp'], $payload['nonce'], $payload['session_id'] ?? '']);
$key = openssl_pkey_get_private(file_get_contents(__DIR__.'/license-device-test.pem'));
openssl_sign($message, $signature, $key, OPENSSL_ALGO_SHA256);
$request->headers->set('X-License-Signature', base64_encode($signature));
if (isset($data['token'])) {
    $request->headers->set('Authorization', 'Bearer '.$data['token']);
}
echo "READY\n";
flush();
try {
    $service = $app->make(LicenseSessionService::class);
    if ($data['path'] === '/api/v1/licenses/heartbeat') {
        $result = $service->sessionRequest($request, 'heartbeat');
    } else {
        $result = $service->activate($request, $data['path'] === '/api/v1/licenses/transfer');
    }
    echo json_encode(['success' => true, 'session_id' => $result['session_id']])."\n";
} catch (LicenseFailure $exception) {
    echo json_encode(['success' => false, 'code' => $exception->errorCode])."\n";
} catch (Throwable $exception) {
    fwrite(STDERR, get_class($exception).': '.$exception->getMessage());
    exit(1);
}
