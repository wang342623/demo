<?php

namespace App\Lib;

use Log;

/**
 * 调用saasApi接口类.
 */
class TalkApi extends BasicApi
{
	public function __construct()
	{
		parent::__construct(null);
	}
	private function sendTalk(string $uri = '', array $post_data)
	{
		$time = time();
		$post_data = $post_data + ['time' => $time];
		ksort($post_data);
		$post_data['key'] = md5(config("custom.talk_key") . implode(",", $post_data));
		// Log::info("sendMTalk,{$uri}," . var_export($post_data, true));
		try {
			return $this->send($uri, $post_data, [$this, 'response']);
		} catch (\Throwable $th) {
			Log::error($th->getMessage());
		}
		return null;
	}
	public function response(string $res_str)
	{

		$res = json_decode($res_str, true);
		if (empty($res) || ($res['code'] ?? 0) != 200 || !isset($res['data'])) {
			return null;
		}
		return $res['data'];
	}
	/**
	 * 获取追问线索使用次数
	 *
	 * @param string $alias
	 * @param [type] $task_time
	 * @return mixed
	 */
	public function getRTaskTimes(string $alias, $task_time)
	{
		$url = "https://{$alias}/new_settings/inkfapi/getRTaskTimes";
		return $this->sendTalk($url, ['task_time' => $task_time, 'alias' => $alias]);
	}
};
