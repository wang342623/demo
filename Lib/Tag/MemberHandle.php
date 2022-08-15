<?php

namespace App\Lib\Tag;
//操作会员标签
class MemberHandle extends Handle
{
	public function company(&$CompanyTag, &$company)
	{
		$tag = null;
		$tags = $this->getTags(['普通会员', '银卡会员', '金卡会员', '特惠会员'], 11);
		switch ($company->member_grade) {
			case 1:
				$tag = $tags[0];
				break;
			case 2:
				$tag = $tags[1];
				break;
			case 3:
				$tag = $tags[2];
				break;
			case 4:
				$tag = $tags[3];
				break;
		}
		if (empty($tag)) {
			return;
		}
		$this->addCompanyTag($CompanyTag, $tag);
	}
};
