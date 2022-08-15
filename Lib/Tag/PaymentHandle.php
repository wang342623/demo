<?php

namespace App\Lib\Tag;

use App\Models\Tag\CompanyTag;
//付款标签
class PaymentHandle extends Handle
{
	use \App\Lib\Task\Task;
	private $cmds = ['KF_recharge'];
	public function company(&$CompanyTag, &$company)
	{
	}
	//处理充值的rabbmitmq消息
	public function KF_recharge($data)
	{
		$company_id = $data['company_id'] ?? 0;
		$pay_bank = $data['pay_bank'] ?? '';
		if ($company_id == 0 || empty($pay_bank)) {
			return;
		}
		$CompanyTag = CompanyTag::where('company_id', $company_id)->first();
		if (empty($CompanyTag)) { //不需要统计
			return;
		}
		$tags = $this->getTags(['个人', '对公'], 13);
		$tag = $tags[1]; //默认对公
		switch ($pay_bank) {
			case '支付宝':
			case '支付宝2':
			case '微信':
				$tag = $tags[0];
				break;
		}
		$this->addCompanyTag($CompanyTag, $tag);
	}
}
