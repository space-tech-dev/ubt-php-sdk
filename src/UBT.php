<?php

namespace ArtisanCloud\UBT;

use Exception;
use Throwable;
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RedisHandler;
use Monolog\Handler\AmqpHandler;
use Predis\Client;
use PhpAmqpLib\Connection\AMQPStreamConnection;

class UBT
{
    protected static $channel = "log4php";
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

    public function __construct()
    {
        if (!self::$logger) {

            self::$baseParams = [
                'appName' => env('UBT_APP_NAME', env('APP_NAME', 'app')),
                'appVersion' => env('UBT_APP_VERSION', env('APP_VERSION', 'app')),
                'serverHostname' => gethostname(),
                'serverAddr' => Utils::getClientIpAddress(),
            ];

            self::$LOG_LEVEL = Utils::formatLogLevel(env('UBT_LOG_LEVEL', 'DEBUG'));

            // the default date format is "Y-m-d\TH:i:sP"
            $dateFormat = "c";
            // the default output format is "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n"
            $output = "%datetime% %level_name% %message%\n";
            // finally, create a formatter
            self::$formatter = new LineFormatter($output, $dateFormat);

            self::$logger = new Logger('logger');

            $this->initFileStream();

            // 发送到redis
            if (env('UBT_REDIS')) {
                $this->initRedisStream();
            } else if (env('UBT_AMQP_URL')) {
                $this->initAMQPStream();
            }

        }
    }

    /**
     * 存储到本地文件系统。这个只用来调试，不会发往es
     */
    protected function initFileStream() {
        // 发送到文件
        $streamHandler = new RotatingFileHandler(storage_path() . '/logs/ubt-redis.log', 7, self::$LOG_LEVEL);
        $streamHandler->setFormatter(self::$formatter);
        self::$logger->pushHandler($streamHandler);
    }

    /**
     * 存储到Redis
     */
    protected function initRedisStream() {
        $redisHandler = new RedisHandler(new Client(env('UBT_REDIS')), "ubt-logs", self::$LOG_LEVEL);
        $redisHandler->setFormatter(self::$formatter);
        self::$logger->pushHandler($redisHandler);
    }

    /**
     * 存储到RabbitMQ
     */
    protected function initAMQPStream() {
        $exchangeName = env('UBT_AMQP_EXCHANGE', 'exchange-ubt-logs');
        $queueName = env('UBT_AMQP_QUEUE', 'queue-ubt-logs');

        $url = parse_url(getenv('UBT_AMQP_URL'));
        $connection = new AMQPStreamConnection($url['host'], $url['port'], $url['user'], $url['pass'], substr($url['path'], 1));
        $channel = $connection->channel();

        $channel->exchange_declare($exchangeName, 'direct', false, true, false); //声明初始化交换机
        $channel->queue_declare($queueName, false, true, false, false);

        $msgTypeArr = [
            'debug.logger',
            'info.logger',
            'error.logger',
            'warning.logger',
            'notice.logger',
            'critical.logger',
            'alert.logger',
            'emergency.logger',
        ];
        foreach ($msgTypeArr as $msgType) {
            $channel->queue_bind($queueName, $exchangeName, $msgType);
        }

        $mqHandler = new AmqpHandler($channel, $exchangeName, self::$LOG_LEVEL);
        $mqHandler->setFormatter(self::$formatter);
        self::$logger->pushHandler($mqHandler);
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

    private function base($logLevel, $msg, $json = [])
    {
        try {
            $formatData = Utils::formatMsg(self::$baseParams, $msg, $json);
            self::$logger->{$logLevel}($formatData);
        } catch (\Throwable $e) {}
    }
}