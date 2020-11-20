<?php

namespace ArtisanCloud\UBT;
use Exception;
use Logger;
use Throwable;

class UBT
{
    protected static $channel = "log4php";
    protected static $logger;
    protected $baseParams = [];

    public function __construct()
    {
        if (!self::$logger) {
            self::$logger = Logger::getLogger(self::$channel);
            Logger::configure(array(
                'rootLogger' => array(
                    'level' => env("UBT_LOG_LEVEL", "INFO"),
                    'appenders' => array('default'),
                ),
                'loggers' => array(
                    'dev' => array(
                        'level' => 'DEBUG',
                        'appenders' => array('default'),
                    ),
                ),
                'appenders' => array(
                    'default' => array(
                        'class' => 'LoggerAppenderRollingFile',
                        'layout' => array(
                            'class' => 'LoggerLayoutPattern',
                            'params' => [
                                'conversionPattern' => "%date %-5level %msg%n",
                            ]
                        ),
                        'params' => [
                            'file' => storage_path().'/ubt-log/ubt.log',
                            'maxFileSize' => '10MB',
                            'maxBackupIndex' => 10,
                        ],
                    ),
                ),
            ));
            $this->baseParams = [
                'APP_ENV' => env('APP_ENV')
            ];
        }
    }

    static function config($config) {
        Logger::configure($config);
        return Logger::getRootLogger();
    }

    function setBaseParams($params = []) {
        $this->baseParams = array_merge();
    }

    function debug($msg, $json = []) {
        $this->base('debug', $msg, $json);
    }

    function info($msg, $json = []) {
        $this->base('info', $msg, $json);
    }

    function notice($msg, $json = []) {
        $this->base('notice', $msg, $json);
    }

    function warn($msg, $json = []) {
        $this->base('warn', $msg, $json);
    }

    function error($msg, $json = []) {
        $this->base('error', $msg, $json);
    }

    function critical($msg, $json = []) {
        $this->base('critical', $msg, $json);
    }

    function alert($msg, $json = []) {
        $this->base('alert', $msg, $json);
    }

    function emerg($msg, $json = []) {
        $this->base('emerg', $msg, $json);
    }

    /**
     * 发送Error
     * @param Throwable $e
     */
    function sendError(\Throwable $e) {
        $this->error([
            'error.msg' => $e->getMessage(),
            'error.code' => $e->getCode(),
            'error.stacks' => $e->getTraceAsString(),
            'error.file' => $e->getFile(),
            'error.line' => $e->getLine()
        ]);
    }

    private function base($logLevel, $msg, $json = []) {

        // 如果msg不是字符串，那么只会接受一个msg，
        if (gettype($msg) !== 'string') {
            try {
                $formatData = json_encode(array_merge($this->baseParams, $msg));
            } catch (Exception $e) {
                if (Utils::isNoEnvProduction()) {
                    throw $e;
                }
                return;
            }
        } else {
            $formatData = json_encode(array_merge($this->baseParams, [
                "msg" => $msg,
                "logLevel" => strtoupper($logLevel)
            ], $json));
        }

        switch ($logLevel) {
            case 'info':
            case 'debug':
            case 'error':
            case 'warn':
                self::$logger->{$logLevel}($formatData);
                break;
            default:
                self::$logger->error($formatData);
        }
    }
}