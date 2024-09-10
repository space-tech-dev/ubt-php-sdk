<?php

namespace ArtisanCloud\UBT;

use ArtisanCloud\UBT\Drivers\AMQPDriver;
use ArtisanCloud\UBT\Drivers\FileDriver;
use ArtisanCloud\UBT\Drivers\RedisDriver;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Log;
use Throwable;
use Monolog\Logger;
use Monolog\Formatter\LineFormatter;

class UBT
{
    private static $logger;
    protected static $baseParams = [];
    /**
     * @var LineFormatter
     */
    private static $formatter;
    /**
     * @var int
     */
    private static $LOG_LEVEL;
    /**
     * @var array|Repository|Application|mixed
     */
    private static $config;

    public function __construct()
    {

    }

    private static function initLogger() {
        try {
            if (!self::$logger) {
                self::$config = Utils::getConfig();
                self::$baseParams = [
                    'appName' => self::$config['appName'],
                    'appVersion' => self::$config['appVersion'],
                    'serverHostname' => gethostname(),
                    "ubtVersion" => Config\Config::$ubtVersion
                ];

                self::$LOG_LEVEL = env('UBT_LOG_LEVEL', 'DEBUG');

                $dateFormat = "c";
                $output = "%datetime% %level_name% %message%\n";
                self::$formatter = new LineFormatter($output, $dateFormat);

                self::$logger = new Logger('logger');

                self::installDriver('file');
                // 发送到redis
                if (env('UBT_REDIS')) {
                    self::installDriver('redis');
                } else if (env('UBT_AMQP_URL')) {
                    self::installDriver('amqp');
                }
            }
        } catch (Throwable $exception) {
            Log::error('Error initializing UBT logger: ' . $exception->getMessage(), [
                'exception' => $exception
            ]);
        }
    }

    static function debug($msg, $json = [])
    {
        self::base('debug', $msg, $json);
    }

    static function info($msg, $json = [])
    {
        self::base('info', $msg, $json);
    }

    static function notice($msg, $json = [])
    {
        self::base('notice', $msg, $json);
    }

    static function warning($msg, $json = [])
    {
        self::base('warning', $msg, $json);
    }

    static function error($msg, $json = [])
    {
        self::base('error', $msg, $json);
    }

    static function critical($msg, $json = [])
    {
        self::base('critical', $msg, $json);
    }

    static function alert($msg, $json = [])
    {
        self::base('alert', $msg, $json);
    }

    static function emergency($msg, $json = [])
    {
        self::base('emergency', $msg, $json);
    }

    static function sendError(\Throwable $e)
    {
        self::error('', [
            'error.msg' => $e->getMessage(),
            'error.code' => $e->getCode(),
            'error.stacks' => $e->getTraceAsString(),
            'error.file' => $e->getFile(),
            'error.line' => $e->getLine()
        ]);
    }

    private static function base($logLevel, $msg, $json = [])
    {
        try {
            self::initLogger();
            $formatData = Utils::formatMsg(self::$baseParams, $msg, $json);
            self::$logger->{$logLevel}($formatData);
        } catch (\Throwable $e) {
            Log::error('Error in UBT logging: ' . $e->getMessage(), [
                'exception' => $e
            ]);
        }
    }

    private static function installDriver($driverName = 'file') {
        $logger = self::$logger;
        $formatter = self::$formatter;
        $LOG_LEVEL = self::$LOG_LEVEL;
        $logLevel = Utils::formatLogLevel($LOG_LEVEL);
        switch ($driverName) {
            case "redis":
                return new RedisDriver($logger, $formatter, $logLevel);
            case "amqp":
                return new AMQPDriver($logger, $formatter, $logLevel);
            default:
                return new FileDriver($logger, $formatter, $logLevel);
        }
    }
}