<?php

namespace App\Lib;

use Log;

/**
 * 调用saasApi接口类.
 */
class saasApi extends BasicApi
{
	private $kf_token;

	public function __construct()
	{
		$url = 'http://' . config('custom.saas_api_host');
		// Log::info('saasApi:' . $url);
		parent::__construct($url);
		$this->kf_token = config('custom.53kf_token');
		$this->basic_data = [
			'53kf_token' => $this->kf_token
		];
	}
	public function getBalance(int $company_id)
	{
		$data = [
			'cmd' => 'getBalance',
			'company_id' => $company_id
		];
		return $this->send('/order', $data);
	}

	/**
	 * 获取账号的开通信息.
	 */
	public function openInfo($company_id, $id6d)
	{
		$post_data = [
			'cmd' => 'query_account_all_open',
			'company_id' => $company_id,
			'id6d' => $id6d,
		];

		return $this->send('/order', $post_data);
	}

	/**
	 * 获取账号信息.
	 */
	public function accountInfo(string $account)
	{
		$post_data = [
			'cmd' => 'account_info',
			'account' => $account,
		];

		return $this->send('/user', $post_data);
	}
	/**
	 * 创建订单.
	 */
	public function create_order($order)
	{
		$order['order_type'] = 1;
		if (!isset($order['order_amount'])) {
			$order['order_amount'] = 1;
		}
		$order['cmd'] = 'generate_order';
		$order['order_remarks'] = $order['order_remarks'] ?? '账号管家点击开通';
		Log::info('创建订单' . \json_encode($order));
		$r = $this->send('/order', $order);
		Log::info('创建订单返回' . var_export($r, true));
		return $r;
	}
	/**
	 * 无页面支付
	 */
	public function renew_order($order)
	{
		if (!isset($order['order_type'])) {
			$order['order_type'] = 1;
		}
		if (!isset($order['order_amount'])) {
			$order['order_amount'] = 1;
		}
		$order['cmd'] = 'renew_order';
		// if (!isset($order['order_remarks'])) {
		// 	$order['order_remarks'] = '账号管家点击开通';
		// }

		Log::info('创建订单' . \json_encode($order));

		return $this->send('/order', $order);
	}

	public function modify_order($order)
	{
		$order['cmd'] = 'modify_order';
		return $this->send('/order', $order);
	}
	/**
	 * 获取订单信息.
	 */
	public function order($order_id)
	{
		$data = [
			'cmd' => 'get_order',
			'order_id' => $order_id,
		];

		return $this->send('/order', $data);
	}
	/**
	 * 修改到期时间,不产生订单
	 */
	public function modifyExpireTime(int $company_id, int $id6d, string $product_id, string $meal_key, string $expire_time)
	{
		$data = [
			'cmd' => 'modify_expire_time',
			'company_id' => $company_id,
			'id6d' => $id6d,
			'product_id' => $product_id,
			'meal_key' => $meal_key,
			'expire_time' => $expire_time,
			'is_push' => 1,
			'auto_renew' => 0, //不需要自动续费
		];
		Log::info($data);
		return $this->send('/order', $data);
	}
	/**
	 * 修改账号到期时间,不产生订单
	 */
	public function modifyExpireTimeAccount(int $company_id, string $order_id, string $account_type, string $product_id, string $meal_key, string $expire_time)
	{
		$data = [
			'cmd' => 'modify_expire_time',
			'company_id' => $company_id,
			'order_id' => $order_id,
			'account_type ' => $account_type,
			'product_id' => $product_id,
			'meal_key' => $meal_key,
			'expire_time' => $expire_time,
			'is_push' => 1,
			'auto_renew' => 0,
		];
		// Log::info($data);
		return $this->send('/order', $data);
	}
	public function orderList($meal_key, $start_time, $end_time)
	{
		$data = [
			'cmd' => 'get_order_list',
			'meal_key' => $meal_key,
			'settlement_time_start' => $start_time,
			'settlement_time_end' => $end_time,
		];
		return $this->send('/order', $data);
	}
}
