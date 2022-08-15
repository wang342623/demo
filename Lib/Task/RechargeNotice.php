<?php

namespace App\Lib\Task;

use App\Models\Worker;
use WxApi;

class RechargeNotice
{
	use Task;
	//充值发送提醒
	public function KF_recharge($data)
	{
		echo "开始通知" . PHP_EOL;
		$company_id = intval($data['company_id'] ?? 0);
		$id6d = intval($data['id6d'] ?? 0);
		$money = round(floatval($data['money'] ?? 0),2);
		$time = $data['add_time'] ?? time();

		if ($company_id == 0 || $money == 0) {
			return;
		}
		$main = Worker::where('company_id', $company_id)->where('account_type', '1')->first();
		$woker = null;
		if ($id6d) {
			$woker = Worker::where('company_id', $company_id)->where('id6d', $id6d)->first();
		}
		if ($woker && $woker->wxid) {
			echo "通知微信公众号{$woker->wxid}" . PHP_EOL;
			WxApi::rechargeReminder($woker->wxid, decryptAesCBC($woker->master_account), $money, $time);
		}
		if ($main && $main->wxid) {
			echo "通知微信公众号{$main->wxid}" . PHP_EOL;
			WxApi::rechargeReminder($main->wxid, decryptAesCBC($main->master_account), $money, $time);
		}
	}

}
