<?php

namespace App\Lib\Tag;

use App\Models\Tag\CompanyTag;
//操作欠费标签,与上个月消费比较，资金是否充足
class ArrearsHandle extends Handle
{
	use \App\Lib\Task\Task;
	private $cmds = ['KF_recharge'];
	private static $user; //欠费用户
	//监听rabbitmq充值删除标签
	public function KF_recharge($msg)
	{
		$company_id = $msg['company_id'] ?? 0;
		$CompanyTag = CompanyTag::where('company_id', $company_id)->first();
		if (empty($CompanyTag)) {
			return;
		}
		$tags = $this->getTags(['余额不足'], 20);
		$this->delCompanyTag($CompanyTag, $tags); //删除标签
		$CompanyTag->save(); //保存
	}
	public function company(&$CompanyTag, &$company)
	{
		//预计本月月订单的消费金额
		$order_price = $company->sumConsumMoney(
			date("Y-m-01 00:00:01", strtotime('-1 month')), //01排除上次月底续费
			date("Y-m-01 00:00:00") //当月的一号0点，包含本次月底续费金额
		);
		$balance = floatval($company->balance->balance);
		$diff = $balance - $order_price;
		$tags = $this->getTags(['余额不足'], 20);
		if ($diff >= 0) {
			$this->delCompanyTag($CompanyTag, $tags); //删除标签
			echolog("[ {$company->company_id} ]余额充足不需要打上欠费标签");
			return;
		}

		$this->addCompanyTag($CompanyTag, $tags[0]);
		echolog("[ {$company->company_id} {$balance} {$order_price} {$diff} ]余额不足打上欠费标签");
	}
};
