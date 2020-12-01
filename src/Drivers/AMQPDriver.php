<?php

namespace ArtisanCloud\UBT\Drivers;
use ArtisanCloud\UBT\Utils;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\AmqpHandler;
use Monolog\Logger;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Psr\Log\LogLevel;

class AMQPDriver implements Driver
{

    public $streamHandler;

    /**
     * AMQPDriver constructor.
     * @param Logger $logger
     * @param LineFormatter $formatter
     * @param int $LOG_LEVEL
     */
    public function __construct(Logger $logger, LineFormatter $formatter, int $LOG_LEVEL)
    {
        $exchangeName = env('UBT_AMQP_EXCHANGE', 'exchange-ubt-logs');
        $queueName = env('UBT_AMQP_QUEUE', 'queue-ubt-logs');

        $url = parse_url(getenv('UBT_AMQP_URL'));
        $connection = new AMQPStreamConnection($url['host'], $url['port'], $url['user'], $url['pass'], substr($url['path'], 1));
        $channel = $connection->channel();

        $channel->exchange_declare($exchangeName, 'direct', false, true, false); //声明初始化交换机
        $channel->queue_declare($queueName, false, true, false, false); // 声明队列

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
        // 将exchange routerKey全部绑定到queue，logstash只会从queue采集
        foreach ($msgTypeArr as $msgType) {
            $channel->queue_bind($queueName, $exchangeName, $msgType);
        }

        $this->streamHandler = new AmqpHandler($channel, $exchangeName, $LOG_LEVEL);
        $this->streamHandler->setFormatter($formatter);
        $logger->pushHandler($this->streamHandler);
    }
}