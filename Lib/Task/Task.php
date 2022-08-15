<?php

namespace App\Lib\Task;

use App\Lib\RabbitMq;
use Log;

trait  Task
{
	protected $rabbit;
	public function handleMsg(array $message)
	{
		$cmd = $message['cmd'];
		$m = \get_class_methods(static::class);
		if (!in_array($cmd, $m)) {
			echolog(static::class . " {$cmd} 方法不存在", 'error');
			return;
		}
		return $this->$cmd($message);
	}
	//设置mq对象
	public function setMq(RabbitMq $r)
	{
		$this->rabbit = $r;
	}
	/**初始化 */
	// abstract public function init();
	/**
	 * 获取任务类能接收的cmd
	 */
	public function getCmds()
	{
		if (empty($this->cmds)) {
			return get_class_methods($this);
		}
		return $this->cmds;
	}
	/**
	 * 仅支持快服网内部队列发生的错误进行重新转发
	 *
	 * @param array $msg
	 * @return void
	 */
	public function publishErrorMsg(array $msg, string $class = '', string $function_name = '')
	{
		$delay_rabbitmq = getSingleClass(RabbitMq::class, 'kfw_delay');
		$error_num = intval($msg['error_num'] ?? 0);
		if ($error_num > 9) { //超过重试次数
			return;
		}
		$msg['class'] = $class; //指定错误处理的类
		$msg['function'] = $function_name; //指定错误处理方法名
		$msg['error_num'] = $error_num + 1;
		return $delay_rabbitmq->publish(json_encode($msg));
	}
}
