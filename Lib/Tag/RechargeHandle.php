<?php

namespace App\Lib\Tag;

//充值次数标签
class RechargeHandle extends Handle
{
	public function company(&$CompanyTag, &$company)
	{
		$start_time = date("Y-m-d H:i:s", strtotime('-1 year'));
		$end_time = date('Y-m-d H:i:s');
		$num = $company->bills()->where('is_earn', 10)->whereBetween('order_date', [$start_time, $end_time])->count();
		$tag = $this->getTags([$num], 15)[0];
		$this->addCompanyTag($CompanyTag, $tag);
	}
};
