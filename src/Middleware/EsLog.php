<?php

namespace ArtisanCloud\UBT\Middleware;

use Closure;
use Html2Text\Html2Text;
use Illuminate\Http\Request;
use ArtisanCloud\UBT\UBT;
use Sentry;

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
            // 记录requestId，和method。 requestId用于追踪同一个请求链路。
            $requestId = $request->header('requestId', uniqid());
            $apiMethod = $request->header('method');
            $laravelStart = defined('LARAVEL_START') ? LARAVEL_START : microtime(true);

            UBT::info('', [
                'logType' => 'request',
                'req' => [
                    "url" => $request->url(),
                    "method" => $request->method(),
                    "path" => $request->path(),
                    "ip" => $request->getClientIp(),
                    "query" => json_encode($request->query()),
                    "id" => $requestId,
                    'api' => $apiMethod,
                    'postData' => json_encode($request->all()),
                    'ua' => $request->header('user-agent')
                ],
            ]);

            // 等待返回结果
            $response = $next($request);

            // 尝试获取返回数据类型
            try {
                $headers = $response->headers->all();
                $contentType = isset($headers) ? $headers['content-type'][0] : "unknown";
            } catch (\Throwable $e) {
                $contentType = "unknown content type, ".$e->getMessage();
            }

            try {
                // 返回内容
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
                $originalData = $response->getOriginalContent();

                if (env("UBT_LOG_LEVEL", 'DEBUG') === "DEBUG") {
                    // 如果日志等级为DEBUG，那么将会输出尝试记录全部返回data。
                    $resMsg['res']['data'] = $this->formatResData($response, $contentType);
                } else if ($response->status() !== 200) {
                    // http code不等于200，记录下全部data。
                    $resMsg['res']['data'] = $this->formatResData($response, $contentType);
                } else if (isset($originalData['meta']) && isset($originalData['meta']['return_code']) && $originalData['meta']['return_code'] !== 200) {
                    // 如果返回了自定义的错误，那么也记录一下对应的值
                    $resMsg['res']['data'] = $this->formatResData($response, $contentType);
                }
                UBT::info('', $resMsg);
            } catch (\Throwable $e) {}

            return $response;
        } catch (\Exception $e) {
            Sentry\captureException($e);
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
}
