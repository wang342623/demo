<?php

namespace App\Lib;

/**
 * 自己封装的rabbitMQ类
 */
class RabbitMq
{
    private $connection = null;
    private $channel = null;
    private $queue = null;
    private $route_key = null;
    private $exchange = null;

    public function __construct(string $connection, string $que_name = '', int $prefetch_count = 1)
    {
        $config = config("rabbitmq.{$connection}");
        if (empty($config['queue_name']) && !empty($que_name)) {
            $config['queue_name'] = $que_name;
        }
        $connection_config = $config['connection'];
        $this->connection = new \AMQPConnection($connection_config);
        if (!$this->connection->connect()) {
            throw new Exception('AMQPConnection连接失败');
        }
        $this->channel = new \AMQPChannel($this->connection); //创建通道
        $this->channel->setPrefetchCount($prefetch_count); //指定预读数量
        //创建交换机对象
        $this->exchange = new \AMQPExchange($this->channel);
        $this->exchange->setName($config['exchange'] ?? '');
        if (empty($config['queue_name'])) {
            return;
        }
        //routeKey默认和队列名一致
        $this->route_key = $this->queue_name = $config['queue_name'] ?? '';
        $this->queue = new \AMQPQueue($this->channel);
        $this->queue->setName($config['queue_name']);
        if (isset($config['queue_arguments'])) {
            $this->queue->setArguments($config['queue_arguments']);
        }

        if (isset($config['declare']) && $config['declare']) {
            $this->queue->declareQueue(); //持久化会自动创建
        }
    }

    //发布消息
    public function publish($msg_body)
    {
        if (is_array($msg_body)) {
            $msg_body = json_encode($msg_body);
        }
        return $this->exchange->publish($msg_body, $this->route_key);
    }

    /**
     * 获取消息
     */
    public function get()
    {
        $message = $this->q->get(AMQP_AUTOACK);
        if ($message)
            return $message->getBody();
        return false;
    }

    /**
     * 阻塞获取消息
     */
    public function consume(
        callable $callback = null,
        $flags = AMQP_NOPARAM,
        $consumerTag = null
    ) {
        return $this->queue->consume($callback, $flags, $consumerTag);
    }
}
