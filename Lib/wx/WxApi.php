<?php

namespace Wx;

use GuzzleHttp\Client;

//微信公众号和小程序服务端api
class WxApi
{
	private $client;
	private $appid;
	private $secret;
	private static $token = null;
	private static $instance = null;
	public function __construct(array $config, $redis)
	{
		$this->client = new Client([
			'base_uri' => $config['host'],
			'http_errors' => false,
			'headers' => [
				'Accept'     => 'application/json',
				'User-Agent' => 'kfadmin',
				'verify' => false
			]
		]);
		$this->appid = $config['appid'];
		$this->secret = $config['secret'];
		$this->redis = $redis;
	}
	public function accessToken()
	{
		$redis_key = "kfw.wechat.token.{{$this->appid}}";
        \Illuminate\Support\Facades\Log::info('redis_key==='.var_export($redis_key,true));
        $time = time();
		if (empty(self::$token)) {
			self::$token = $this->redis->hgetall($redis_key);
		}
		if (!empty(self::$token) && self::$token['expires_time'] > $time) {
			return self::$token['access_token'];
		}
		$response = $this->client->get('/cgi-bin/token', [
			'query' => [
				'grant_type' => 'client_credential',
				'appid' => $this->appid,
				'secret' => $this->secret,
			]
		]);
		$response = json_decode($response->getBody()->getContents(), true);
        \Illuminate\Support\Facades\Log::info('$response2==='.var_export($response,true));
        if (empty($response) || isset($response['errcode'])) { //出错了
			return null;
		}
		$t = $response;
		$t['expires_time'] = $t['expires_in'] + $time - 3; //3s即将快到期时需要重新获取
		self::$token = $t;
		$this->redis->hmset($redis_key, $t);
		$this->redis->EXPIREAT($redis_key, $t['expires_time']);
		return $t['access_token'];
	}
	private function postSend(string $url, array $json_params, string $key = null)
	{
		$token = $this->accessToken();
        \Illuminate\Support\Facades\Log::info('$token==='.var_export($token,true));

        $response = $this->client->post($url, [
			'query' => [
				'access_token' => $token
			],
			'json' => $json_params
		]);
		return $this->handelRes($response, $key);
	}
	/**
	 * 处理响应内容
	 *
	 * @param [type] $response
	 * @param string|null $key
	 * @return mixed
	 */
	private function handelRes($response, string $key = null)
	{
		$response = json_decode($response->getBody()->getContents(), true);
        \Illuminate\Support\Facades\Log::info('repsonse==='.var_export($response,true));
		$errcode = $response['errcode'] ?? 0;
		if ($errcode != 0) {
			return null;
		}
		if (empty($key)) {
			return $response;
		}
		return $response[$key] ?? null;
	}
	/**
	 * 发送get请求
	 *
	 * @param string $url 请求路径
	 * @param array $params get参数
	 * @param string $key 响应内容的key
	 * @return mixed
	 */
	private function getSend(string $url, array $params, string $key = null)
	{
		$params['access_token'] = $this->accessToken();
		return $this->handelRes($this->client->post($url, ['query' => $params]), $key);
	}
	/**
	 * 发送模版消息
	 *
	 * @param string $open_id
	 * @param string $template_id
	 * @param array $data
	 * @param string|null $url
	 * @param array|null $miniprogram
	 * @return integer
	 */
	public function messageTemSend(string $open_id, string $template_id, array $data, string $url = null, array $miniprogram = null): int
	{
		$json_params = [
			'touser' => $open_id,
			'template_id' => $template_id,
			'data' => $data
		];
		if (!empty($url)) {
			$json_params['url'] = $url;
		}
		if (!empty($miniprogram)) {
			$json_params['miniprogram'] = $miniprogram;
		}
		return intval($this->postSend('/cgi-bin/message/template/send', $json_params, 'msgid'));
	}
	/**
	 * 充值通知
	 *
	 * @param string $open_id 微信openID
	 * @param string $account 账号
	 * @param float $amount 金额
	 * @param integer $time 充值时间戳
	 * @return integer 消息ID
	 */
	public function rechargeReminder(string $open_id, string $account, float $amount, int $time): int
	{
		$data = [
			'first' => ['value' => '您好，您已充值成功！'],
			'keyword1' => ['value' => $account],
			'keyword2' => ['value' => $amount.'元'],
			'keyword3' => ['value' => date('Y年m月d日 H:i:s', $time)],
			'remark' => ['value' => '感谢您的使用!']
		];
		return $this->messageTemSend($open_id, 'JtdWm4jNbGRDqm1voA1lkR-3c-kWgt4YLds52hqIxS0', $data);
	}
	/**
	 * 续费余额不足通知
	 *
	 * @param string $open_id
	 * @param string $account
	 * @param float $amount
	 * @return integer 消息ID
	 */
	public function renewReminder(string $open_id, string $account, float $amount, float $balance): int
	{
		$data = [
			'first' => ['value' => "尊敬的客户，您上个月使用快服产品共计{$amount}元，为保证正常续费，不影响下个月使用，请您及时充值。"],
			'keyword1' => ['value' => $account],
			'keyword2' => ['value' => $balance],
			'remark' => ['value' => '感谢您的使用!']
		];
		return $this->messageTemSend($open_id, 'cHvhqoMM6cfj1OPi4ycIG0sQ4L9cjG9MWaMlhm8YJi8', $data);
	}
	public function getUnionID(string $openid)
	{
		return $this->getSend('/cgi-bin/user/info', ['openid' => $openid], 'unionid');
	}

    /***
     * Notes:消费（下单）通知
     * openOrderReminder
     * @param string $open_id  通知用户id
     * @param string $order_id  订单编号
     * @param string $pay_time  支付时间
     * @param string $purchase_details  购物明细
     * @param string $pay_coupons  优惠金额
     * @param string $pay_money  实付金额
     * @return int
     */
    public function openOrderReminder(string $open_id, string $order_id, string $pay_time, string $purchase_details, string $pay_coupons, string $pay_money): int
    {
        $data = [
            'first' => ['value' => "亲爱的用户，您的订单付款成功，明细如下："],
            'keyword1' => ['value' => $order_id], #订单编号
            'keyword2' => ['value' => $pay_time], #支付时间
            'keyword3' => ['value' => $purchase_details], #购物明细
            'keyword4' => ['value' => sprintf("%.2f",$pay_coupons).'元'], #优惠金额
            'keyword5' => ['value' => $pay_money.'元'], #实付金额
            'remark' => ['value' => '感谢您的惠顾，欢迎下次光临！']
        ];
        return $this->messageTemSend($open_id, 'jrS5IBhoOpCWKwW0KXL70DLozoqcv4u1r8v6xreTqr8', $data);
    }

    /***
     * Notes:
     * renewSwitchReminder
     * @param string $open_id 通知用户id
     * @param string $account 操作账户
     * @param string $meal_name 套餐名称
     * @return int
     */
    public function renewSwitchReminder(string $open_id,string $account,string $meal_name){
        $data = [
            'first' => ['value' => "亲爱的用户，您的服务状态发生变更"],
            'keyword1' => ['value' => "套餐【{$meal_name}】开启续费开关"], #服务类型
            'keyword2' => ['value' => "账号{$account} 开启续费开关"], #服务状态
            'keyword3' => ['value' => date('Y-m-d H:i:s')], #服务时间
            'remark' => ['value' => '点击下方小程序管理续费开关！']
        ];
        $miniprogram=[
            'appid'=>config('custom.kf_wxmini_app_id'),
            'path'=>'pages/homePage/homePage'
        ];
        return $this->messageTemSend($open_id, 'V9kzoVrrG0B8ddlBP4HjFW-VLGXJM7T-dbaT6RxDCYo', $data,null,$miniprogram);
    }
}
