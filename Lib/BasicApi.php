<?php

namespace App\Lib;

use GuzzleHttp\Client;
use Log;

class BasicApi
{
	protected $error_msg = [
		403 => '连续登录错误超过三次，系统将在120秒内限制登录',
		61460 => '账号已离职',
		61455 => '连续登录错误超过三次，系统将在120秒内限制登录',
		404 => '账号不存在',
		406 => '账号或密码有误',
		407 => '普通令牌失效',
		408 => '一次性令牌失效',
		413 => '获取调用调用失败',
		414 => '接口调用令牌失效',
		502 => '系统错误',
		510 => '账号停用',
		506 => '请扫码登录',
		507 => '账号违规，禁止登录',
	];
	protected $client;
	protected $basic_data = [];
	protected $base_url = null;
	protected $last_response = null;
	public function __construct($base_uri)
	{
		$this->base_url = $base_uri;
		$this->client = new Client([
			'base_uri' => $base_uri,
			'http_errors' => false,
			'headers' => [
				'Accept'     => 'application/json',
				'User-Agent' => 'kfadmin',
				'verify' => false
			]
		]);
	}
	/**
	 * 获取最后一次响应
	 */
	public function getLastRes()
	{
		return $this->last_response;
	}
	/**
	 * post发送数据
	 *
	 * @param string $uri
	 * @param array $post_data
	 * @param [type] $callback
	 * @return void
	 */
	public function send($uri = '', array $post_data = null, $callback = null)
	{
		if (is_array($uri)) {
			$post_data = $uri;
			$uri = '';
		}
		$post_data = array_merge($post_data, $this->basic_data);

		$this->last_response = $response = $this->client->request('POST', $uri, [
			'form_params' => $post_data,
			'http_errors' => false,
		]);
		//处理回调函数
		$call = function ($contents) use ($uri, $post_data, $callback) {
			if ($callback) {
				return $callback($contents, $uri, $post_data);
			}
			return $this->java_return($contents, $uri, $post_data); //默认认为是java服务（订单服务、账号服务等）的返回结果
		};
		$Status = $response->getStatusCode();
		if ($response->getStatusCode() < 200 || $response->getStatusCode() > 300) {
			echolog(static::class . ",{$this->base_url}{$uri},http status:{$Status} request data" . var_export($post_data, true) . " result " . var_export($response->getBody()->getContents(), true), 'error');
			return $call('');
		}
		$response_contents = $response->getBody()->getContents();
		if (empty($response_contents)) {
			echolog("{$this->base_url}{$uri},响应为空,post_data->" . var_export($post_data, true), 'error');
		}
		return $call($response_contents);
	}
	/**
	 * 获取错误码对应的错误消息
	 */
	public function getMsg($code)
	{
		if (isset($this->error_msg[$code])) {
			return $this->error_msg[$code];
		}
		return '未知错误';
	}
	/**
	 * 格式化java返回的数据
	 *
	 * @param string $json
	 * @return mixed
	 */
	protected function java_return(string $json)
	{
		$dejson = \json_decode($json, true);

		if (empty($dejson) || empty($dejson['server_response'])) {
			Log::error('java error-->' . $json);
			return null;
		}
		$dejson = $dejson['server_response'];
		$status_code = $dejson['status_code'];
		if (201 != $status_code) {
			if (!isset($this->error_msg[$status_code])) {
				if (isset($dejson['msg'])) {
					$this->error_msg[$status_code] = $dejson['msg'];
				}
				if (isset($dejson['details']) && isset($dejson['details']['msg'])) {
					$this->error_msg[$status_code] = $dejson['details']['msg'];
				}
			}
			Log::error('java error-->' . $json);
			return $status_code;
		}
		if (!empty($dejson['details'])) {
			return $dejson['details'];
		}

		return $dejson;
	}
	//不要写跟子类重复的方法，容易出错
};
