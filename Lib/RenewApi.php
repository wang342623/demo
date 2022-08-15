<?php

namespace App\Lib;

class RenewApi extends BasicApi
{
	public function __construct()
	{
		parent::__construct('http://' . config('custom.renew_host') . '/api');
	}
	//处理响应
	public function res($response)
	{
		$json = json_decode($response, true);
		if (empty($json) || !isset($json['status_code']) || $json['status_code'] != 200) {
			return null;
		}
		return $json['data'];
	}
	public function sendRenew(array $post_data)
	{
		$post_data['53kf_token'] = config('custom.53kf_token');
		return $this->send('', $post_data, [$this, 'res']);
	}
	/**
	 * 获取月底续费的公司ID
	 *
	 * @param integer $facilitator_id 服务商ID
	 * @param string $month 指定月份
	 * @return mixed
	 */
	public function monthCompanyIds(int $facilitator_id, string $month = null)
	{
		$post_data = [
			'cmd' => 'month_company_ids',
			'facilitator_id' => $facilitator_id
		];
		if ($month) {
			$post_data['month'] = $month;
		}
		return $this->sendRenew($post_data);
	}
};
