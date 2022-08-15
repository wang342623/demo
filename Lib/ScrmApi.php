<?php

namespace App\Lib;

class ScrmApi extends BasicApi
{
	private  $company_id;
	public function __construct(int $company_id)
	{
		parent::__construct('https://' . config('custom.scrm_host'));
		$this->company_id = $company_id;
	}
	/**
	 * 发送请求
	 *
	 * @param string $uri
	 * @param array $post_data
	 * @return mixed
	 */
	private function sendScrm(string $uri, array $post_data, string $res_key = 'code')
	{
		$post_data['company_id'] = $this->company_id;
		$post_data['appid'] = config('custom.scrm_appid');
		$post_data['app_secret'] = config('custom.scrm_app_secret');

		return $this->send($uri, $post_data, function ($response) use ($uri, $post_data, $res_key) {
			$json = json_decode($response, true);
			$re_code = $json['code'] ?? 500;
			if ($re_code != 200) {
				echolog("ScrmApi调用失败 {$uri} -> " . var_export($post_data, true) . " {$response} ", 'error');
			}
			return $json[$res_key] ?? null;
		});
	}
	/**
	 * 添加客户
	 *
	 * @param integer $company_id 公司ID
	 * @param integer $member_id 会员ID
	 * @param integer $member_userid 联系人ID
	 * @param string $name 姓名
	 * @param string $mobile 手机号
	 * @param integer|null $id6d 归属人的id6d
	 * @param string|null $wechat 微信号
	 * @param string|null $qq QQ号
	 * @param string|null $email 电子邮箱地址
	 * @param integer|null $gender 性别0未知1男性2女性
	 * @param string|null $corp_name 企业名称
	 * @param integer|null $reg_time 注册时间，秒级时间戳
	 * @return integer
	 */
	public function addUser(
		int $member_id,
		int $member_userid,
		string $name,
		string $mobile,
		int  $id6d = null,
		string $wechat = null,
		string $qq = null,
		string $email = null,
		int $gender = null,
		string $corp_name = null,
		int $reg_time = null
	) {
		return $this->sendScrm('/api/custom/addUser', getMethodArgs(func_get_args(), __CLASS__, __FUNCTION__));
	}
	/**
	 * 添加动态
	 *
	 * @param integer $company_id 公司ID
	 * @param integer $member_id 会员ID
	 * @param string $title	标题
	 * @param string $content 内容
	 * @return integer
	 */
	public function addUserDynamic(int $member_id, string $title, string $content)
	{
		return $this->sendScrm('/api/custom/addUserDynamic', getMethodArgs(func_get_args(), __CLASS__, __FUNCTION__));
	}
	/**
	 * 修改客户归属人
	 *
	 * @param integer $company_id 公司ID
	 * @param integer $member_id 会员ID
	 * @param integer $id6d 归属人的id6d
	 * @return integer
	 */
	public function changeUserOwner(int $member_id, int $id6d)
	{
		return $this->sendScrm('/api/custom/changeUserOwner', getMethodArgs(func_get_args(), __CLASS__, __FUNCTION__));
	}
	/**
	 * 编辑客户标签
	 *
	 * @param integer $member_id 客户ID
	 * @param array|null $add_tags 添加的标签信息
	 * @param array|null $del_tags 删除的标签信息
	 * @return void
	 */
	public function editUserTag(int $member_id, array $add_tags = null, array $del_tags = null)
	{
		$args = func_get_args();
		for ($i = 1; $i < 3; $i++) {
			if (!isset($args[$i])) {
				break;
			}
			$args[$i] = json_encode($args[$i]);
		}

		return $this->sendScrm('/api/custom/editUserTag', getMethodArgs($args, __CLASS__, __FUNCTION__));
	}
	public function getTagList()
	{
		return $this->sendScrm('/api/custom/getTagList', [], 'tags_list');
	}
	public function sendImNotice($member_ids, int $id6d,  string $remindTitle, string $remindContent, string $contentTitle, string $content)
	{
		$args = func_get_args();
		if (is_array($args[1])) {
			$args[1] = implode(',', $args[1]);
		}
		return $this->sendScrm('/api/custom/sendImNotice', getMethodArgs($args, __CLASS__, __FUNCTION__));
	}
};
