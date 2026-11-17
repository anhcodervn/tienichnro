<?php

namespace App\Features\Client\GameVerification\Services;

use App\Features\Client\GamePassword\Exceptions\GamePasswordFailure;
use App\Features\Client\GamePassword\Services\GamePasswordCaptchaService;
use DOMDocument;
use DOMXPath;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use Throwable;

class GameVerificationGatewayService
{
    private const BASE = 'https://forum.ngocrongonline.com';

    public function __construct(private readonly GamePasswordCaptchaService $solver) {}

    public function verify(string $username, string $password, int $server, callable $progress, array $captchaCredentials): string
    {
        if (blank($captchaCredentials['key'] ?? null) || blank($captchaCredentials['secret'] ?? null)) {
            throw new GamePasswordFailure('USER_SOLVER_NOT_CONFIGURED');
        }
        $cookies = new CookieJar;
        $deadline = microtime(true) + 240;
        try {
            if (! $this->login($cookies, $username, $password, $server, $deadline, $progress, $captchaCredentials)) {
                throw new GamePasswordFailure('LOGIN_FAILED');
            }
            $progress('login_success');
            $progress('profile');
            $html = $this->client($cookies, $deadline)->get(self::BASE.'/app/thong-tin-ca-nhan')->throw()->body();
            $progress('extract');
            $syntax = $this->extractSyntax($html, $username);
            if ($syntax === null) {
                throw new GamePasswordFailure('SYNTAX_MISSING');
            }

            return $syntax;
        } catch (Throwable $exception) {
            throw new GamePasswordFailure($exception instanceof GamePasswordFailure ? $exception->reason : 'REMOTE_UNAVAILABLE');
        } finally {
            $cookies->clear();
        }
    }

    private function extractSyntax(string $html, string $username): ?string
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $document = new DOMDocument;
            $document->loadHTML('<?xml encoding="UTF-8">'.($html ?: '<html></html>'));
            $xpath = new DOMXPath($document);
            $nodes = $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " text-2xl ") and contains(concat(" ", normalize-space(@class), " "), " font-mono ")]//span');
            foreach ($nodes as $node) {
                $otp = trim($node->textContent);
                if (preg_match('/^[A-Za-z0-9]{8}$/D', $otp)) {
                    return 'GO NRO-XT '.$username.' '.$otp;
                }
            }
            foreach ($xpath->query('//h2[contains(concat(" ", normalize-space(@class), " "), " font-mono ")]') as $node) {
                $text = preg_replace('/\s+/u', ' ', trim($node->textContent));
                if (preg_match('/^GO NRO-XT '.preg_quote($username, '/').' ([A-Za-z0-9]{8})$/iD', $text, $matches)) {
                    return 'GO NRO-XT '.$username.' '.$matches[1];
                }
            }

            return null;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function client(CookieJar $cookies, float $deadline): PendingRequest
    {
        if (microtime(true) >= $deadline) {
            throw new GamePasswordFailure('REMOTE_TIMEOUT');
        }

        return Http::timeout(max(1, min(15, $deadline - microtime(true))))->connectTimeout(5)->withUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/131.0.0.0 Safari/537.36')
            ->withHeaders(['Accept' => 'text/html,application/xhtml+xml', 'Accept-Language' => 'vi-VN,vi;q=0.9', 'Origin' => self::BASE])
            ->withOptions(['cookies' => $cookies, 'allow_redirects' => ['max' => 2, 'protocols' => ['https'],
                'on_redirect' => function (RequestInterface $request, ResponseInterface $response, UriInterface $uri): void {
                    if ($uri->getHost() !== 'forum.ngocrongonline.com') {
                        throw new GamePasswordFailure('REDIRECT_BLOCKED');
                    }
                }]]);
    }

    private function login(CookieJar $cookies, string $username, string $password, int $server, float $deadline, ?callable $progress = null, array $captchaCredentials = []): bool
    {
        $progress && $progress('login_page');
        $page = $this->client($cookies, $deadline)->get(self::BASE.'/app/dang-nhap')->throw()->body();
        $fields = $this->fields($page);
        $progress && $progress('login_captcha');
        $token = $this->captcha($fields['sitekey'], self::BASE.'/app/dang-nhap', $deadline, $captchaCredentials);
        $progress && $progress('login');
        $this->client($cookies, $deadline)->withoutRedirecting()->asForm()->withHeaders(['Referer' => self::BASE.'/app/dang-nhap'])->post(self::BASE.'/app/dang-nhap', [
            'ts' => $fields['ts'], 'keyLog' => $fields['keyLog'], 'user' => $username, 'pass' => $password, 'server' => $server, 'cf-turnstile-response' => $token,
        ])->throw();
        $forum = $this->client($cookies, $deadline)->get(self::BASE.'/app/dien-dan-0')->throw()->body();

        return str_contains($forum, '/app/dang-xuat');
    }

    private function fields(string $html): array
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $document = new DOMDocument;
            $document->loadHTML($html ?: '<html></html>');
            $xpath = new DOMXPath($document);
            $sitekey = $xpath->query('//*[@data-sitekey]')->item(0)?->getAttribute('data-sitekey');
            $ts = $xpath->query('//input[@name="ts"]')->item(0)?->getAttribute('value');
            $key = $xpath->query('//input[@name="keyLog"]')->item(0)?->getAttribute('value');
            if (blank($sitekey) || blank($ts) || blank($key)) {
                throw new GamePasswordFailure('FORM_CHANGED');
            }

            return ['sitekey' => $sitekey, 'ts' => $ts, 'keyLog' => $key];
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function captcha(string $sitekey, string $pageUrl, float $deadline, array $captchaCredentials): string
    {
        return $this->solver->solveWithUserCredentials($sitekey, $pageUrl, $deadline, $captchaCredentials);
    }
}
