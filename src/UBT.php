<?php

namespace ArtisanCloud\UBT;

use Exception;
use Throwable;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RedisHandler;
use Predis\Client;

class UBT
{
    protected static $channel = "log4php";
    private static $logger;
    protected static $baseParams = [];

    public function __construct()
    {
        if (!self::$logger) {
            self::$baseParams = [
                'appName' => env('UBT_APP_NAME', env('APP_NAME', 'app')),
                'appVersion' => env('UBT_APP_VERSION', env('APP_VERSION', 'app')),
                'serverHostname' => gethostname(),
                'serverAddr' => $_SERVER['SERVER_ADDR'],
            ];

            $LOG_LEVEL = $this->formatLogLevel(env('UBT_LOG_LEVEL', 'DEBUG'));
            // the default date format is "Y-m-d\TH:i:sP"
            $dateFormat = "c";
            // the default output format is "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n"
            $output = "%datetime% %level_name% %message%\n";
            // finally, create a formatter
            $formatter = new LineFormatter($output, $dateFormat);

            self::$logger = $logger = new Logger('logger');

            // 发送到文件
            $streamHandler = new StreamHandler(storage_path() . '/logs/ubt-redis.log', $LOG_LEVEL);
            $streamHandler->setFormatter($formatter);
            $logger->pushHandler($streamHandler);

            // 发送到redis
            if (env('UBT_REDIS')) {
                $redisHandler = new RedisHandler(new Client(env('UBT_REDIS')), "logs", $LOG_LEVEL);
                $redisHandler->setFormatter($formatter);
                $logger->pushHandler($redisHandler);
            }
        }
    }

    static function setBaseParams($params = [])
    {
        self::$baseParams = array_merge(self::$baseParams, $params);
    }

    function debug($msg, $json = [])
    {
        $this->base('debug', $msg, $json);
    }

    function info($msg, $json = [])
    {
        $this->base('info', $msg, $json);
    }

    function notice($msg, $json = [])
    {
        $this->base('notice', $msg, $json);
    }

    function warning($msg, $json = [])
    {
        $this->base('warning', $msg, $json);
    }

    function error($msg, $json = [])
    {
        $this->base('error', $msg, $json);
    }

    function critical($msg, $json = [])
    {
        $this->base('critical', $msg, $json);
    }

    function alert($msg, $json = [])
    {
        $this->base('alert', $msg, $json);
    }

    function emergency($msg, $json = [])
    {
        $this->base('emergency', $msg, $json);
    }

    /**
     * 发送Error
     * @param Throwable $e
     */
    function sendError(\Throwable $e)
    {
        $this->error([
            'error.msg' => $e->getMessage(),
            'error.code' => $e->getCode(),
            'error.stacks' => $e->getTraceAsString(),
            'error.file' => $e->getFile(),
            'error.line' => $e->getLine()
        ]);
    }

    protected function formatLogLevel($logLevel = "debug") {
        switch (mb_strtolower($logLevel)) {
            case 'info':
                return Logger::INFO;
            case 'notice':
                return Logger::NOTICE;
            case 'waring':
                return Logger::WARNING;
            case 'error':
                return Logger::ERROR;
            case 'critical':
                return Logger::CRITICAL;
            case 'alert':
                return Logger::ALERT;
            default:
                return Logger::DEBUG;
        }
    }

    protected function formatMsg($msg, $json = [])
    {
        // 如果msg不是字符串，那么只会接受一个msg，
        if (gettype($msg) !== 'string') {
            try {
                $formatData = json_encode(array_merge(self::$baseParams, $msg));
            } catch (Exception $e) {
                if (Utils::isNoEnvProduction()) {
                    throw $e;
                }
                return '';
            }
        } else {
            $formatData = json_encode(array_merge(self::$baseParams, [
                "msg" => $msg,
//                "logLevel" => strtoupper($logLevel)
            ], $json));
        }

        return $formatData;
    }

    private function base($logLevel, $msg, $json = [])
    {
        try{
            $formatData = $this->formatMsg($msg, $json);
            self::$logger->{$logLevel}($formatData);
        } catch (Exception $exception) {}


    }

    function sendToRedis()
    {

    }
}