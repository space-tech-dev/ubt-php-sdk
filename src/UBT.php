<?php

namespace SpaceCycle;
use Exception;
use Logger;

class UBT
{
    protected static $channel = "log4php";
    protected static $logger;
    protected static $baseParams = [];

    public function __construct()
    {
        if (!self::$logger) {
            self::$logger = Logger::getLogger(self::$channel);
            $configPath = dirname(__DIR__).'/ubt-config.properties';
            if (file_exists($configPath)) {
                self::configByFile($configPath);
            }
            self::$baseParams = [
                'APP_ENV' => env('APP_ENV')
            ];
        }
    }

    static function configByFile($configPath) {
        $file = fopen($configPath, 'rw');
        $data = fread($file, filesize($configPath));
        if (strpos($data, '${storagePath}') !== -1) {
            $file = fopen($configPath, 'w');
            $data = str_replace('${storagePath}', storage_path(), $data);
            fwrite($file, $data);
        }
        Logger::configure($configPath);
    }

    static function config($config) {
        Logger::configure($config);
    }

    /**
     * @param $msg
     * @param array $json
     */
    function debug($msg, $json = []) {
        self::base('debug', $msg, $json);
    }

    /**
     * @param $msg
     * @param array $json
     */
    function info($msg, $json = []) {
        self::base('info', $msg, $json);
    }

    /**
     * @param $msg
     * @param array $json
     */
    function error($msg, $json = []) {
        self::base('error', $msg, $json);
    }

    /**
     * 发送Error
     * @param Exception $e
     */
    function sendError(Exception $e) {
        $this->error([
            'error.msg' => $e->getMessage(),
            'error.code' => $e->getCode(),
            'error.stacks' => $e->getTraceAsString(),
            'error.file' => $e->getFile(),
            'error.line' => $e->getLine()
        ]);
    }

    private static function base($logLevel, $msg, $json = []) {

        $formatData = "";
        // 如果msg不是字符串，那么只会接受一个msg，
        if (gettype($msg) !== 'string') {
            try {
                $formatData = json_encode(array_merge(self::$baseParams, $msg));
            } catch (Exception $e) {
                if (Utils::isNoEnvProduction()) {
                    throw $e;
                }
            }
        } else {
            $formatData = $msg.' '.json_encode(array_merge(self::$baseParams, [
                "msg" => $msg
            ], $json));
        }

        switch ($logLevel) {
            case 'debug':
                self::$logger->debug($formatData);
                break;
            case 'error':
                self::$logger->error($formatData);
                break;
            default:
                self::$logger->info($formatData);
        }
    }
}