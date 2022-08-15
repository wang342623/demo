<?php

namespace App\Lib;

use GuzzleHttp\Client;

class appidApi
{
	private static $token = '';
	public $appid = '';
	public $appsecret = '';
	private static $expire_time = 0;
	public function __construct()
	{
		$this->appid = config('custom.appid');
		$this->appsecret = config('custom.appsecret');
	}
	public function kftoken()
	{
		if (!empty(self::$token) && self::$expire_time > time()) { //之前的token还没到期
			return self::$token;
		}
		$client = new Client();
		$options = [
			'base_uri' => 'https://' . config('custom.appid_host'),
			'http_errors' => false,
			'timeout' => 60,
			'headers' => [
				'Accept'     => 'application/json',
				'User-Agent' => 'renew',
				'verify' => false,
				'timestamp' => time()
			],
			'json' => [
				'cmd' => '53kf_token',
				'appid' => $this->appid,
				'appsecret' => $this->appsecret
			]
		];
		$data = $this->res($client->post('/appid/manager/get/token', $options));
		if (empty($data)) {
			return $data;
		}
		self::$token = $data['53kf_token'];
		self::$expire_time = time() + $data['expires_in'] - 5; //token到期时间
		return self::$token;
	}
	private function res($response)
	{
		$response = $response->getBody()->getContents();

		if (empty($response) || empty($json = json_decode($response, true))) {
			return null;
		}
		if (($json['code'] ?? 0) != 200 || !isset($json['data'])) {
			return null;
		}
		return $json['data'];
	}
};
