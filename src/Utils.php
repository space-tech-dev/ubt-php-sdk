<?php


namespace ArtisanCloud\UBT;


use Monolog\Logger;

class Utils
{
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
        // 如果msg不是字符串，那么只会接受一个msg，
        if (gettype($msg) !== 'string') {
            try {
                $formatData = json_encode(array_merge($baseParams, $msg));
            } catch (\Exception $e) {
                if (Utils::isNoEnvProduction()) {
                    throw $e;
                }
                return '';
            }
        } else {
            $formatData = json_encode(array_merge($baseParams, [
                "msg" => $msg,
            ], $json));
        }

        return $formatData;
    }
}