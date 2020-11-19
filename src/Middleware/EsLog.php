<?php

namespace SpaceCycle\Middleware;

use Closure;
use Illuminate\Http\Request;
use SpaceCycle\UBT;

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
        $requestId = $request->header('requestId', uniqid());
        $apiMethod = $request->header('method');
        self::$ubt->debug([
            'request' => [
                "url" => $request->url(),
                "method" => $request->method(),
                "path" => $request->path(),
                "ip" => $request->ip(),
                "query" => $request->query(),
                "requestId" => $requestId,
                'api' => $apiMethod,
            ],
        ]);
        $response = $next($request);

        $data = $response->getContent();
//        if ($request->route()) {
//            $path = $request->route()->uri();
//        }
        self::$ubt->debug([
            'request' => [
                "method" => $request->method(),
                "requestId" => $requestId,
                'api' => $apiMethod,
            ],
            'response' => [
                'responseTime' => round(microtime(true) - LARAVEL_START, 2),
                'path' => $request->path(),
                'data' => $data,
            ]
        ]);
        return $response;
    }
}
