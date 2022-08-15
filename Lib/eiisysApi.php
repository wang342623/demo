<?php

namespace App\Lib;

use GuzzleHttp\Client;

class eiisysApi
{
	private $appidapi;
	private $last_res;
	public function __construct()
	{
		$this->appidapi = new appidApi;
		$this->last_res = null;
	}
	/**
	 * 发送请求
	 *
	 * @param string $path 请求路径
	 * @param array|null $data 请求参数
	 * @param array $success_code 成功的code默认为[200]
	 * @return mixed
	 */
	private function send(string $path, array $data = null, array $success_code = null)
	{
		$token = $this->appidapi->kftoken();
		if (empty($token)) {
			return null;
		}
		$post_data = [
			'token' => $token,
			'appid' => $this->appidapi->appid
		];
		if (!empty($data)) {
			$post_data += $data;
		}
		$client = new Client();
		$url = 'https://' . config('custom.eiisysApi_host') . $path;
		$options = [
			'http_errors' => false,
			'timeout' => 60,
			'headers' => [
				'Accept'     => 'application/json',
				'User-Agent' => '53cloud',
				'verify' => false
			],
			'form_params' => $post_data
		];
		$response = $client->post($url, $options);
		$response = $response->getBody()->getContents();
		$this->last_res = $response;
		$json = json_decode($response, true);
		if (empty($json) || !isset($json['code'])) {
			echolog("eiisysApi出错了,请求URL:[ {$url} ],响应内容:[ {$response} ]", 'error');
			return null;
		}
		if (empty($success_code)) {
			$success_code = [200];
		}
		if (!in_array($json['code'], $success_code)) {
			return null;
		}
		return $json;
	}
	public function getLastRes()
	{
		return $this->last_res;
	}
	//发送短信
	public function sendSMS(string $mobile, int $template_id, array $args)
	{
		if (isDeve()) {
			return true; //开发环境直接返回成功，避免产生费用
		}
		$data = [
			'mobile' => $mobile,
			'template_id' => $template_id,
			'content' => implode('%%', $args)
		];
		return $this->send('/interface/sendSms', $data);
	}
	/**
	 * 53kf微信公众号通知
	 *
	 * @param int $company_id 接收者公司id
	 * @param float $balance 当前余额
	 * @param float $cost_num 上个月消费额
	 * @param string $account 主账号
	 * @return mixed
	 */
	public function sendWx(int $company_id, int $id6d, float $balance, float $cost_num, string $account)
	{
		$data = [
			'send_company_id' => $company_id,
			'send_id6d' => $id6d,
			'balance' => $balance, //账户余额
			'cost_num' => $cost_num, //上月消费
			'send_account' => $account, //微信通知显示账号
			'send_type' => 'balance_notice_id6d'
		];
		return $this->send('/interface/sendWxNotify', $data, [200, 210]); //210未绑定微信通知,视为成功
	}

	public function voiceNotice(
		array $phone_data,
		string $notice_info,
		int $max_call_count = 1,
		string $out_phone = null,
		string $task_start_date = null,
		string $task_end_date = null,
		string $task_start_time = null,
		string $task_end_time = null
	) {
		$data = [
			'phone_data' => json_encode($phone_data),
			'notice_info' => $notice_info,
			'max_call_count' => $max_call_count
		];
		$vals = ['out_phone', 'task_start_date', 'task_end_date', 'task_start_time', 'task_end_time'];
		foreach ($vals as $v) {
			if (!empty($$v)) {
				$data[$v] = $$v;
			}
		}
		return $this->send('/interface/voiceRobotNotice', $data);
	}
	/**
	 * 发送客户端通知
	 *
	 * @param integer $receive_company_id 接收通知的company_id
	 * @param integer $receive_id6d 接收通知的id6d
	 * @param string $receive_msg	通知内容
	 * @return mixed
	 */
	public function sendKfNotice(int $receive_company_id, int $receive_id6d, string $receive_msg)
	{
		return $this->send('/interface/sendKfNotice', getMethodArgs(func_get_args(), __CLASS__, __FUNCTION__));
	}
};
