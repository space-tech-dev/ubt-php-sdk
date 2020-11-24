<?php

namespace ArtisanCloud\UBT\Middleware;

use Closure;
use Illuminate\Http\Request;
use ArtisanCloud\UBT\UBT;

class EsLog
{
    protected static $ubt;

    public function __construct()
    {
        self::$ubt = new UBT();
    }

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

            self::$ubt->info([
                'logType' => 'request',
                'request' => [
                    "url" => $request->url(),
                    "method" => $request->method(),
                    "path" => $request->path(),
                    "ip" => $request->getClientIp(),
                    "query" => json_encode($request->query()),
                    "requestId" => $requestId,
                    'api' => $apiMethod,
                    'postData' => json_encode($request->all()),
                ],
            ]);

            // 等待返回结果
            $response = $next($request);


            // 获取返回内容
            $data = $response->getContent();
            self::$ubt->info([
                'logType' => 'response',
                'request' => [
                    "method" => $request->method(),
                    "requestId" => $requestId,
                    'api' => $apiMethod,
                    'path' => $request->path(),
                ],
                'response' => [
                    'responseTime' => round(microtime(true) - $laravelStart, 2),
                ]
            ]);

            // response data暂时定义为debug类型，后面看日志量大小可以随时被关闭。
            self::$ubt->debug([
                'logType' => 'response:data',
                'request' => [
                    "requestId" => $requestId,
//                'api' => $apiMethod,
                ],
                'response' => [
                    'data' => $data
                ]
            ]);
            return $response;
        } catch (\Exception $e) {
            if (!isset($response)) {
                return $next($request);
            }
        }
    }
}
