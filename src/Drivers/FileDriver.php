<?php


namespace ArtisanCloud\UBT\Drivers;


use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;

class FileDriver implements Driver
{

    public function __construct(Logger $logger, LineFormatter $formatter, int $LOG_LEVEL)
    {
        $streamHandler = new RotatingFileHandler(storage_path() . '/logs/ubt-redis.log', 7, $LOG_LEVEL);
        $streamHandler->setFormatter($formatter);
        $logger->pushHandler($streamHandler);
    }
}