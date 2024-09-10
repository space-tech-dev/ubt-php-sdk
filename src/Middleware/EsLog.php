<?php

namespace ArtisanCloud\UBT\Middleware;

use Closure;
use Html2Text\Html2Text;
use Illuminate\Http\Request;
use ArtisanCloud\UBT\UBT;
use Illuminate\Support\Facades\Log;

class EsLog
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            $requestId = $request->header('requestId', uniqid());
            $apiMethod = $request->header('method');
            $laravelStart = defined('LARAVEL_START') ? LARAVEL_START : microtime(true);

            $token = $this->extractToken($request);
            $decodedToken = $this->decodeToken($token);

            $reqInfo = [
                "url" => $request->url(),
                "method" => $request->method(),
                "path" => $request->path(),
                "ip" => $request->getClientIp(),
                "query" => json_encode($request->query()),
                "id" => $requestId,
                'api' => $apiMethod,
                'postData' => json_encode($request->all()),
                'ua' => $request->header('user-agent'),
                'decodedToken' => json_encode($decodedToken), // Add this line
            ];

            

            if (is_array($decodedToken) && isset($decodedToken['account']) && is_array($decodedToken['account'])) {
                if (isset($decodedToken['account']['personmobilephone'])) {
                    $reqInfo['mobile'] = $decodedToken['account']['personmobilephone'];
                }
                if (isset($decodedToken['account']['uuid'])) {
                    $reqInfo['accountUUID'] = $decodedToken['account']['uuid'];
                }
            }

            UBT::info('', [
                'logType' => 'request',
                'req' => $reqInfo,
            ]);

            $response = $next($request);

            try {
                $headers = $response->headers->all();
                $contentType = isset($headers) ? $headers['content-type'][0] : "unknown";
            } catch (\Throwable $e) {
                $contentType = "unknown content type, ".$e->getMessage();
            }

            try {
                $resMsg = [
                    'logType' => 'response',
                    'req' => [
                        "method" => $request->method(),
                        "id" => $requestId,
                        'api' => $apiMethod,
                        'path' => $request->path(),
                    ],
                    'res' => [
                        'contentType' => $contentType,
                        'responseTime' => ceil((microtime(true) - $laravelStart) * 1000),
                        'data' => '***'
                    ]
                ];

                // 将 mobile 和 accountUUID 添加到返回值中
                if (isset($reqInfo['mobile'])) {
                    $resMsg['req']['mobile'] = $reqInfo['mobile'];
                }
                if (isset($reqInfo['accountUUID'])) {
                    $resMsg['req']['accountUUID'] = $reqInfo['accountUUID'];
                }

                $originalData = $response->getOriginalContent();

                if (env("UBT_LOG_LEVEL", 'DEBUG') === "DEBUG") {
                    $resMsg['res']['data'] = $this->formatResData($response, $contentType);
                } else if ($response->status() !== 200) {
                    $resMsg['res']['data'] = $this->formatResData($response, $contentType);
                } else if (isset($originalData['meta']) && isset($originalData['meta']['return_code']) && $originalData['meta']['return_code'] !== 200) {
                    $resMsg['res']['data'] = $this->formatResData($response, $contentType);
                }
                UBT::info('', $resMsg);
            } catch (\Throwable $e) {}

            return $response;
        } catch (\Exception $e) {
            // 使用 Laravel 的日志系统记录错误
            Log::error('Error in EsLog middleware: ' . $e->getMessage(), [
                'exception' => $e,
                'request' => $request->all()
            ]);

            if (!isset($response)) {
                return $next($request);
            }
            return $response;
        }
    }

    private function formatResData($response, $contentType) {
        $data = $response->getContent();
        if (str_starts_with($contentType, 'text/html')) {
            $html = new Html2Text($data);
            $data = $html->getText();
        } else if (gettype($data) !== 'string') {
            try {
                $data = json_encode($data);
            } catch (\Throwable $e) {
                $data = strval($data)."get error: ".$e->getMessage();
            }
        }

        return $data;
    }

    private function extractToken(Request $request): ?string
    {
        $header = $request->header('Authorization');
        if (strpos($header, 'Bearer ') === 0) {
            return substr($header, 7);
        }
        return null;
    }

    private function decodeToken(?string $token): ?array
    {
        if (!$token) {
            return null;
        }

        try {
            $tokenParts = explode('.', $token);
            if (count($tokenParts) != 3) {
                return null;
            }
            $payload = $this->base64UrlDecode($tokenParts[1]);
            return json_decode($payload, true);
        } catch (\Exception $e) {
            // 记录错误或根据需要处理
            Log::error('Token decoding error: ' . $e->getMessage());
            return null;
        }
    }

    private function base64UrlDecode(string $input): string
    {
        $remainder = strlen($input) % 4;
        if ($remainder) {
            $padlen = 4 - $remainder;
            $input .= str_repeat('=', $padlen);
        }
        return base64_decode(strtr($input, '-_', '+/'));
    }
}
