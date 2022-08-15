<?php

namespace App\Lib;

/**
 * 安全平台相关接口
 */
class aqptApi extends BasicApi
{
	protected $client;
	public function __construct()
	{
		parent::__construct('http://' . config('custom.aqpt_host'));
	}
	//处理响应
	public function res(string &$response)
	{
		if (empty($response) || empty($json = json_decode($response, true)) || !($json['success'] ?? false)) {
			return null;
		}
		return $json['result'];
	}
	public function certIntfaceNums(string $start_time, string $end_time)
	{
		$uri = '/sendjkpt/getAuthFrequency/getAllAuth';
		$data = ['start_time' => $start_time, 'end_time' => $end_time];
		return $this->send($uri, $data, [$this, 'res']);
	}
};
