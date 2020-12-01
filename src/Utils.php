<?php


namespace ArtisanCloud\UBT;

use Monolog\Logger;

class Utils
{

    static function getConfig() {
        $config = config('ubt');
        if (!$config) {
            global $config;
            $config = [
                "appName" => env("APP_NAME", 'ubt-app-name'),
                "appVersion" => 'unknown',
                "logLevel" => env("UBT_LOG_LEVEL", 'debug'),
            ];
        }
        return $config;
    }

    static function isNoEnvProduction() {
        return
            env('APP_ENV') === 'dev' ||
            env('APP_ENV') === 'development' ||
            env('APP_ENV') === 'test' ||
            env('APP_ENV') === 'staging';
    }

    static function formatLogLevel($logLevel = "debug") {
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

    static function getClientIpAddress() {
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipAddresses = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim(end($ipAddresses));
        }
        else {
            return isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : '';
        }
    }

    static function formatMsg($baseParams, $msg, $json = [])
    {
        $formatData = json_encode(array_merge($baseParams, [
            "msg" => $msg,
        ], $json));

        return $formatData;
    }
}