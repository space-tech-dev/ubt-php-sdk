<?php


namespace ArtisanCloud\UBT\Drivers;


use ArtisanCloud\UBT\Utils;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RedisHandler;
use Monolog\Logger;
use Predis\Client;
use Psr\Log\LogLevel;

class RedisDriver implements Driver
{

    public function __construct(Logger $logger, LineFormatter $formatter, int $LOG_LEVEL)
    {
        $redisHandler = new RedisHandler(new Client(env('UBT_REDIS')), "ubt-logs", $LOG_LEVEL);
        $redisHandler->setFormatter($formatter);
        $logger->pushHandler($redisHandler);
    }
}