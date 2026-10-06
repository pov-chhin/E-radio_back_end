<?php

namespace App\Http\Controllers;

use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use Illuminate\Http\Client\Response as HttpResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MinioMediaController extends Controller
{
    public function __invoke(Request $request, string $path): StreamedResponse
    {
        abort_unless(config('services.minio.console_url'), 404);
        abort_if(str_contains($path, '..'), 400, 'Invalid media path.');

        $upstream = $this->download($path, $request->header('Range'));

        if ($upstream->status() === 401) {
            Cache::forget('minio-console-token');
            $upstream = $this->download($path, $request->header('Range'));
        }

        abort_if($upstream->failed(), $upstream->status(), 'Media could not be read from storage.');

        $body = $upstream->toPsrResponse()->getBody();
        $headers = collect(['Content-Type', 'Content-Length', 'Content-Range', 'Accept-Ranges', 'ETag', 'Last-Modified'])
            ->mapWithKeys(fn (string $name) => filled($upstream->header($name)) ? [$name => $upstream->header($name)] : [])
            ->all();

        return response()->stream(function () use ($body): void {
            while (! $body->eof()) {
                echo $body->read(64 * 1024);
                flush();
            }
        }, $upstream->status(), $headers);
    }

    private function download(string $path, ?string $range): HttpResponse
    {
        $consoleUrl = rtrim((string) config('services.minio.console_url'), '/');
        $host = parse_url($consoleUrl, PHP_URL_HOST);

        abort_unless(is_string($host) && $host !== '', 500, 'The MinIO console URL is invalid.');

        return Http::withCookies(['token' => $this->token($consoleUrl)], $host)
            ->withHeaders(array_filter(['Range' => $range]))
            ->withOptions(['handler' => $this->curlHandler(), 'stream' => true])
            ->connectTimeout(5)
            ->timeout(300)
            ->get($consoleUrl.'/api/v1/buckets/'.rawurlencode((string) config('filesystems.disks.media_minio.bucket')).'/objects/download', [
                'prefix' => $path,
            ]);
    }

    private function token(string $consoleUrl): string
    {
        return Cache::remember('minio-console-token', now()->addMinutes(10), function () use ($consoleUrl): string {
            $response = Http::asJson()
                ->withOptions(['handler' => $this->curlHandler()])
                ->connectTimeout(5)
                ->timeout(15)
                ->post($consoleUrl.'/api/v1/login', [
                    'accessKey' => config('services.minio.root_user'),
                    'secretKey' => config('services.minio.root_password'),
                ]);

            abort_if($response->failed(), 502, 'Could not authenticate with MinIO.');

            $token = $response->cookies()->getCookieByName('token')?->getValue();
            abort_unless(filled($token), 502, 'MinIO did not return a session token.');

            return $token;
        });
    }

    private function curlHandler(): HandlerStack
    {
        return HandlerStack::create(new CurlHandler);
    }
}
