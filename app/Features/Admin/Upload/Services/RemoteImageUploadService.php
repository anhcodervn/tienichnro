<?php

namespace App\Features\Admin\Upload\Services;

use GuzzleHttp\Psr7\UriResolver;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class RemoteImageUploadService
{
    private const MAX_BYTES = 10 * 1024 * 1024;

    private const MAX_REDIRECTS = 3;

    public function __construct(private ImageUploadService $imageUploadService) {}

    /** @return array{path: string, url: string, mime_type: string, extension: string, size: int} */
    public function store(string $url): array
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'remote-image-');

        if ($temporaryPath === false) {
            throw new RuntimeException('Không thể khởi tạo tệp tạm để tải ảnh.');
        }

        try {
            $finalUrl = $this->download($url, $temporaryPath);
            [$mimeType, $extension] = $this->inspectImage($temporaryPath);
            $originalName = $this->originalName($finalUrl, $extension);
            $uploadedFile = new UploadedFile($temporaryPath, $originalName, $mimeType, null, true);

            return $this->imageUploadService->store($uploadedFile, pathinfo($originalName, PATHINFO_FILENAME));
        } finally {
            @unlink($temporaryPath);
        }
    }

    private function download(string $url, string $temporaryPath): string
    {
        $currentUrl = $url;

        for ($redirect = 0; $redirect <= self::MAX_REDIRECTS; $redirect++) {
            $requestOptions = $this->safeRequestOptions($currentUrl);
            file_put_contents($temporaryPath, '');

            try {
                $response = Http::accept('image/jpeg,image/png,image/webp')
                    ->connectTimeout(3)
                    ->timeout(12)
                    ->withOptions(array_merge($requestOptions, [
                        'allow_redirects' => false,
                        'sink' => $temporaryPath,
                        'progress' => function (int $downloadTotal, int $downloadedBytes): void {
                            if ($downloadTotal > self::MAX_BYTES || $downloadedBytes > self::MAX_BYTES) {
                                throw new RuntimeException('Ảnh từ URL không được vượt quá 10 MB.');
                            }
                        },
                    ]))
                    ->get($currentUrl);
            } catch (RuntimeException $exception) {
                throw $exception;
            } catch (Throwable $exception) {
                throw new RuntimeException('Không thể tải ảnh từ URL đã cung cấp.', previous: $exception);
            }

            if ($response->redirect()) {
                $location = $response->header('Location');

                if (! is_string($location) || $location === '' || $redirect === self::MAX_REDIRECTS) {
                    throw new RuntimeException('URL ảnh chuyển hướng không hợp lệ.');
                }

                $currentUrl = $this->resolveRedirectUrl($currentUrl, $location);

                continue;
            }

            if (! $response->successful()) {
                throw new RuntimeException('Máy chủ ảnh từ chối yêu cầu tải về.');
            }

            $this->persistFakeResponseBody($response, $temporaryPath);
            $size = filesize($temporaryPath);

            if (! is_int($size) || $size <= 0) {
                throw new RuntimeException('URL không trả về nội dung ảnh.');
            }

            if ($size > self::MAX_BYTES) {
                throw new RuntimeException('Ảnh từ URL không được vượt quá 10 MB.');
            }

            return $currentUrl;
        }

        throw new RuntimeException('URL ảnh chuyển hướng quá nhiều lần.');
    }

    /** @return array<string, mixed> */
    private function safeRequestOptions(string $url): array
    {
        $parts = parse_url($url);
        $scheme = Str::lower((string) ($parts['scheme'] ?? ''));
        $host = Str::lower(rtrim((string) ($parts['host'] ?? ''), '.'));
        $port = $parts['port'] ?? null;

        if (! in_array($scheme, ['http', 'https'], true) || $host === '' || isset($parts['user']) || isset($parts['pass'])) {
            throw new RuntimeException('URL ảnh không hợp lệ.');
        }

        if ($port !== null && ! in_array($port, [80, 443], true)) {
            throw new RuntimeException('Cổng URL ảnh không được phép.');
        }

        if ($host === 'localhost' || Str::endsWith($host, ['.localhost', '.local'])) {
            throw new RuntimeException('URL ảnh không được trỏ tới mạng nội bộ.');
        }

        $addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : $this->resolveHostAddresses($host);

        if ($addresses === []) {
            throw new RuntimeException('Không thể phân giải tên miền của URL ảnh.');
        }

        foreach ($addresses as $address) {
            if (! filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new RuntimeException('URL ảnh không được trỏ tới mạng nội bộ.');
            }
        }

        if (! defined('CURLOPT_RESOLVE')) {
            return [];
        }

        $connectionPort = $port ?? ($scheme === 'https' ? 443 : 80);
        $pinnedAddress = Str::contains($addresses[0], ':') ? '['.$addresses[0].']' : $addresses[0];

        return ['curl' => [CURLOPT_RESOLVE => ["{$host}:{$connectionPort}:{$pinnedAddress}"]]];
    }

    /** @return list<string> */
    protected function resolveHostAddresses(string $host): array
    {
        $records = dns_get_record($host, DNS_A | DNS_AAAA);

        if ($records === false) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (array $record): ?string => $record['ip'] ?? $record['ipv6'] ?? null,
            $records,
        )));
    }

    /** @return array{0: string, 1: string} */
    private function inspectImage(string $path): array
    {
        $imageInfo = @getimagesize($path);
        $mimeType = is_array($imageInfo) ? ($imageInfo['mime'] ?? null) : null;
        $extension = match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw new RuntimeException('URL chỉ được trả về ảnh JPG, PNG hoặc WebP.'),
        };

        if (($imageInfo[0] ?? 0) > 8000 || ($imageInfo[1] ?? 0) > 8000) {
            throw new RuntimeException('Kích thước ảnh từ URL không được vượt quá 8000 x 8000 pixel.');
        }

        return [$mimeType, $extension];
    }

    private function originalName(string $url, string $extension): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $basename = is_string($path) ? pathinfo($path, PATHINFO_FILENAME) : '';
        $basename = Str::slug($basename) ?: 'remote-image';

        return Str::limit($basename, 100, '').'.'.$extension;
    }

    private function resolveRedirectUrl(string $currentUrl, string $location): string
    {
        try {
            return (string) UriResolver::resolve(Utils::uriFor($currentUrl), Utils::uriFor($location));
        } catch (Throwable $exception) {
            throw new RuntimeException('URL ảnh chuyển hướng không hợp lệ.', previous: $exception);
        }
    }

    private function persistFakeResponseBody(Response $response, string $temporaryPath): void
    {
        if (filesize($temporaryPath) === 0 && $response->body() !== '') {
            file_put_contents($temporaryPath, $response->body());
        }
    }
}
