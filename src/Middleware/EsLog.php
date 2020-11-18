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
        self::$ubt->debug([
            "url" => $request->url(),
            "method" => $request->method(),
            "path" => $request->path(),
            "ip" => $request->ip(),
            "query" => $request->query(),
            "requestId" => $requestId,
        ]);
        $response = $next($request);
        $data = $response->getContent();
        $path = $request->path();
        if ($request->route()) {
            $path = $request->route()->uri();
        }
        $path = $request->method() . ':' . $path;
        self::$ubt->debug([
            'responseTime' => round(microtime(true) - LARAVEL_START, 2),
            'path' => $path,
            'data' => $data,
            "requestId" => $requestId,
        ]);
        return $response;
    }
}
