<?php

namespace App\Lib\Tag;

use App\Models\NewOrder;
//月消费资金标签
class MonthConsumHandle extends Handle
{
	public function company(&$CompanyTag, &$company)
	{
		$tags = $this->getTags(['[0,199]', '[200,999]', '[1000,+∞]'], 14);
		$start_time = date("Y-m-01 00:00:00", strtotime(date("Y-m-01")) - 10);
		$end_time = date("Y-m-d 23:59:59", strtotime(date("Y-m-01")) - 10);
		$pay_money = NewOrder::where('company_id', $company->company_id)
			->where('order_type', 1)
			->whereBetween('order_time', [$start_time, $end_time])->sum('pay_money');
		$pay_money = floatval($pay_money);
		$tag_num_arr = [[0, 199], [200, 999], [1000, PHP_INT_MAX]];
		$this->interval($CompanyTag, $pay_money, $tag_num_arr, $tags);
	}
};
