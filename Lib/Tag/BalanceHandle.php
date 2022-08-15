<?php

namespace App\Lib\Tag;
//操作余额标签
class BalanceHandle extends Handle
{
	public function company(&$CompanyTag, &$company)
	{
		$tags = $this->getTags(['[0,99]', '[100,999]', '[1000,9999]', '[10000,+∞]'], 12);
		$balance = $company->balance; //余额
		if (empty($balance)) {
			return 0;
		}
		$balance_num = floatval($balance->balance);
		$tag_num_arr = [[0, 99], [100, 999], [1000, 9999], [10000, PHP_INT_MAX]];
		$this->interval($CompanyTag, $balance_num, $tag_num_arr, $tags);
	}
};
