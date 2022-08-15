<?php

namespace App\Lib\Tag;

use App\Models\Tag\Tag as TagModel;
use App\Models\Company;
use App\Models\Tag\CompanyTag;
use App\Models\Tag\Tag;
//标签处理基类
abstract class Handle
{
	//创建或者查询标签
	public function firstOrCreate(string $name, int $type)
	{
		return TagModel::firstOrCreate(['tag_name' => $name, 'type_id' => $type]);
	}
	/**
	 * 查询或创建多个标签
	 *
	 * @param array $name_arr
	 * @param integer $type
	 * @return array
	 */
	public function getTags(array $name_arr, int $type)
	{
		$tag_arr = [];
		foreach ($name_arr as $name) {
			$tag_arr[] = $this->firstOrCreate($name, $type);
		}
		return $tag_arr;
	}
	//添加公司标签
	public function addCompanyTag(CompanyTag &$CompanyTag, Tag $tag)
	{
		$type = $tag->type;
		if ($type->is_only == 1) { //单选标签
			$this->delCompanyTag( //删除其他标签
				$CompanyTag,
				$type->tags()->where('id', '!=', $tag->id)->get()
			);
		}
		$id_arr = $CompanyTag->tag_ids;
		if (in_array($tag->id, $id_arr)) { //已经存在了
			return true;
		}
		$id_arr[] = $tag->id;
		$CompanyTag->tag_ids = $id_arr;
		return $CompanyTag;
	}
	public function delCompanyTag(CompanyTag &$CompanyTag, $tags)
	{
		$id_arr = $CompanyTag->tag_ids;
		$del_ids = [];
		foreach ($tags as $value) {
			$del_ids[] = $value->id;
		}
		$id_arr = array_diff($id_arr, $del_ids); //删除
		$CompanyTag->tag_ids = $id_arr;
		return $CompanyTag;
	}
	//根据区间判断增加标签
	protected function interval(CompanyTag &$CompanyTag, $value, array $arr, array $tags)
	{
		for ($i = 0; $i < count($arr); $i++) {
			$compare = $arr[$i];
			if ($value >= $compare[0] && $value <= $compare[1]) {
				$this->addCompanyTag($CompanyTag, $tags[$i]);
				return true; //只需命中一次
			}
		}
	}
	/**
	 * 处理公司标签,处理完成后不需要更新至数据库
	 *
	 * @param CompanyTag $CompanyTag 标签库公司标签关联表对象
	 * @param Company $Company 快服网库公司表对象
	 * @return void
	 */
	abstract public function company(CompanyTag &$CompanyTag, Company &$Company);
	//company方法在app/Console/CommandsAnalysisLabel.php调用
};
