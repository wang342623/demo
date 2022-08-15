<?php

namespace App\Lib;

use Log;

/**
 * 调用saasApi接口类.
 */
class mTalkApi extends BasicApi
{
	public function __construct()
	{
		$url = 'https://' . config('custom.mtalk_host');
		parent::__construct($url);
	}
	private function sendMTalk(string $uri = '', array $post_data)
	{
		$time = time();
		$token = config('custom.53kf_token');
		$post_data = $post_data + ['time' => $time, 'sign' => md5("{$token}{$time}")];
		Log::info("sendMTalk,{$uri}," . var_export($post_data, true));
		return $this->send($uri, $post_data, [$this, 'response']);
	}
	public function response(string $res_str)
	{

		$res = json_decode($res_str, true);
		if (empty($res) || ($res['code'] ?? 0) != 200 || !isset($res['data'])) {
			return null;
		}
		return $res['data'];
	}
	//获取空号检测数据
	public function emptyNumber(string $start_date, string $end_date)
	{
		return $this->sendMTalk('/interface/deduction_statistics.php', [
			'start_time' => $start_date,
			'end_time' => $end_date,
			'type' => 'empty_monitor_statistics'
		]);
	}
}
