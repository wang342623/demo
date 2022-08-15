<?php

namespace App\Lib\Task;

use App\Lib\ScrmApi;
use App\Models\Company;
use App\Models\Product;
use App\Models\Tag\CompanyTag;
use App\Models\Tag\Tag;
use kfRedis;
//scrm推送逻辑
class ScrmPush
{
	use \App\Lib\Task\Task;
	private $scrm_api;
	public function __construct()
	{
		$this->scrm_api = new ScrmApi(config('custom.self_company_id'));
	}
	private $push_meals = []; //需要推送的套餐
	private function getCompany(int $company_id)
	{
		if ($company_id <= 0) {
			return null;
		}
		$fa_id = kfRedis::facilitatorId($company_id);
		if ($fa_id != config('custom.facilitator_id')) { //不是快服网账号
			return null;
		}
		$company = Company::findById($company_id);
		//补充客户经理的id6d
		if (
			!empty($customer_manager = $company->customerManager) &&
			!empty($cm_worker = $customer_manager->worker)
		) {
			$company->cm_id6d = $cm_worker->id6d;
		}
		return  $company;
	}
	/**
	 * 向scrm发送数据
	 *
	 * @param string $fun_name 调用函数名
	 * @param [type] ...$args 函数参数，第一个参数为客户的公司ID
	 * @return void
	 */
	private function sendScrm(string $fun_name, ...$args)
	{
		$r = $this->scrm_api->$fun_name(...$args);
		if ($r == 212) { //没有客户数据,先添加客户，再执行一次
			$this->addUser($args[0]);
			$r = $this->scrm_api->$fun_name(...$args);
		}
		return $r;
	}
	//saas订单推送
	public function open_push($msg)
	{
		$company_id = $msg['company_id'] ?? 0;
		//客户信息
		$company = $this->getCompany($company_id);
		if (empty($company)) {
			return echolog("srcm open_push {$company_id} 没有找到公司信息");
		}
		$product_key = $msg['product_key'] ?? ''; //产品key

		$product = Product::findByKey($product_key); //产品信息
		if (empty($product)) {
			return echolog("srcm open_push {$product_key} 没有找到产品信息");
		}
		$order_id = ($msg['order'] ?? [])['order_id'] ?? '';
		if (empty($order_id)) {
			return echolog("srcm open_push {$order_id} 为空");
		}
		$old_order = $product->orders()
			->where('company_id', $company->company_id)
			->where('saas_order_id', '!=', $order_id)
			->first();
		if (!empty($old_order)) { //以前开通过
			return echolog("srcm open_push {$company_id} {$product_key} 开通过");
		}
		$con = "账号{$company->master_account}";
		if (!empty($company->company_name)) {
			$con .= "（{$company->company_name}）";
		}
		$con .= "开通了{$product->product_name}";
		$r = $this->sendScrm(
			'addUserDynamic',
			$company->company_id,
			'客户开通新产品',
			$con
		);
		echolog("scrm 添加开通动态-执行结果返回 " . var_export($r, true));
		if (empty($company->cm_id6d)) {
			return echolog("srcm open_push {$company_id} 没有找到客户经理的id6d");
		}
		$r = $this->sendScrm(
			'sendImNotice',
			$company->company_id,
			$company->cm_id6d,
			'客户开通新产品通知',
			'你有1位客户开通了新产品！去看看>>',
			'客户开通新产品提醒',
			$con
		);
		echolog("scrm 开通新产品通知-执行结果返回 " . var_export($r, true));
	}
	//客户经理变更
	public function KF_CustomerManager($msg)
	{
		//客户信息
		$company = $this->getCompany($msg['company_id'] ?? 0);
		if (empty($company->cm_id6d)) {
			return;
		}
		$r = $this->sendScrm(
			'changeUserOwner',
			$company->company_id,
			$company->cm_id6d
		);
		echolog("scrm 修改客户归属 " . var_export($r, true));
	}
	//某个客户首次(注册时)分配客户经理
	public function KF_CustomerManager_first($msg)
	{
		$msg_company_id = $msg['company_id'] ?? 0; //消息中的公司ID
		return $this->addUser($msg_company_id);
	}
	/**
	 * 向scrm添加客户
	 *
	 * @param int $company_id 客户的公司ID
	 * @return void
	 */
	private function addUser(int $company_id)
	{
		//客户信息
		$company = $this->getCompany($company_id);

		if (empty($company)) {
			return echolog("向scrm添加客户 {$company_id} 快服网数据库里没有找到");
		}
		$cus_id6d = $company->cm_id6d; //归属客户经理的id6d

		$main_worker = $company->workers()->where('account_type', '1')->first(); //主账号信息
		if (empty($main_worker)) {
			return echolog("向scrm添加客户 {$company_id} 没有找到主账号");
		}
		$main_worker_id6d = $main_worker->id6d;
		if (empty($main_worker->id6d)) { //缺数据了
			$main_worker_id6d = kfRedis::mainId6d($company_id);
		}
		$r = $this->scrm_api->addUser(
			$company_id, //客户的公司ID
			$main_worker_id6d, //客户主账号的id6d
			$main_worker->contact_name ?? $main_worker->master_account, //主账号姓名
			$main_worker->mobile,
			$cus_id6d, //归属客户经理的id6d
			null, //快服网没有微信号
			$main_worker->qq,
			$main_worker->email,
			$main_worker->sex,
			$company->company_name ?? $company->master_account,
			strtotime($company->reg_date)
		);
		echolog("向scrm添加客户 {$company_id} 执行结果返回 " . var_export($r, true));
	}
	public function KF_openAccount($msg)
	{
		$company = $this->getCompany($msg['company_id'] ?? 0);
		if (empty($company)) {
			return;
		}
		$bill = $company->bills()->where('bill_type', 0)->orderBy('id', 'asc')->first();
		$money = '0';
		$con = "无充值";
		if (!empty($bill)) {
			$money =  sprintf("%.2f", $bill->money);
			$con = "首次充值金额：￥{$money}";
		}
		$r = $this->sendScrm(
			'addUserDynamic',
			$company->company_id,
			'开户',
			$con
		);
		echolog("scrm 添加开户动态-执行结果返回 " . var_export($r, true));
		if (empty($company->cm_id6d)) { //客户经理信息
			return echolog("scrm push {$company->company_id} 没有找到对应的客户经理信息");
		}
		$con = "账号{$company->master_account}";
		if (!empty($company->company_name)) {
			$con .= "（{$company->company_name}）";
		}
		$con .= "开户，";
		$con .= "首次充值金额：￥{$money}";
		$r = $this->sendScrm(
			'sendImNotice',
			$company->company_id,
			$company->cm_id6d,
			'客户开户通知',
			'你有1位新客户开户了！去看看>>',
			'客户开户提醒',
			$con
		);
		echolog("scrm 添加开户通知-执行结果返回 " . var_export($r, true));
	}
	//添加标签
	public function addTag()
	{
		$tag_config = config('custom.scrm_tag');
		//需要添加的标签ID
		$tag_ids = array_keys($tag_config);
		$query = CompanyTag::WhereJsonContains('label_json', $tag_ids[0]);
		for ($i = 1; $i < count($tag_ids); $i++) {
			$query->orWhereJsonContains('label_json', $tag_ids[$i]);
		}
		$company_tags = $query->get();
		$notice_arr = []; //需要发送通知的数据
		foreach ($company_tags as $value) {
			//排除不需要添加的的标签,理论上这里不会为空
			if (empty($intersect = array_intersect($value->tag_ids, $tag_ids))) {
				continue;
			}
			$add_tag = []; //需要添加的标签信息
			foreach ($intersect as $id) { //$id属于$tag_ids
				$add_tag[] = [
					'tag_id' => $tag_config[$id],
					'exp_time' => strtotime(date('Y-m-t 23:59:59'))
				];
			}
			$r = $this->sendScrm(
				'editUserTag',
				$value->company_id,
				$add_tag
			);
			echolog("scrm 添加标签 " . var_export($r, true));
			if (empty($company = $this->getCompany($value->company_id)) || empty($company->cm_id6d)) {
				continue;
			}
			$id6d = $company->cm_id6d;
			if (!isset($notice_arr[$id6d])) {
				$notice_arr[$id6d] = [];
			}
			if (!isset($notice_arr[$id6d][$id])) {
				$notice_arr[$id6d][$id] = [];
			}
			$notice_arr[$id6d][$id][] = $value->company_id;
		}
		//执行完成后，发送通知
		foreach ($notice_arr as $id6d => $tag_companys) {
			foreach ($tag_companys as $tag_id => $company_ids) {
				$tag = Tag::find($tag_id);
				$num = count($company_ids);
				if (empty($tag) || $num <= 0) {
					continue;
				}
				$r = $this->scrm_api->sendImNotice(
					$company_str = implode(',', $company_ids),
					$id6d,
					"客户{$tag->tag_name}通知",
					"当月你有{$num}位{$tag->tag_name}的客户！去看看>>",
					"客户{$tag->tag_name}提醒",
					"当月你有{$num}位{$tag->tag_name}的客户"
				);
				echolog("scrm 标签通知结果[ {$company_str} ]" . var_export($r, true));
			}
		}
	}
	//saas推送的添加工号信息，此时可能快服网的数据还没完成写入
	public function Qworker_add($msg)
	{
		if (($msg['is_superAdmin'] ?? 0) == 1) { //主账号，不需要操作，在KF_CustomerManager_first中已经添加客户
			return;
		}
		$company_id = $msg['company_id'] ?? 0;
		$fa_id = kfRedis::facilitatorId($company_id);
		if (empty($fa_id) || $fa_id != config('custom.facilitator_id')) {
			echolog(var_export($msg, true) . "找不到服务商id", 'error');
			return;
		}
		$id6d = $msg['id6d'] ?? 0;
		if (empty($id6d)) {
			return;
		}
		$r = $this->scrm_api->addUser(
			$company_id, //客户的公司ID
			$id6d, //客户子账号的id6d
			$msg['name'], //账号姓名
			decryptAesCBC($msg['mobile'])
		);
		echolog("向scrm添加联系人 {$company_id} 执行结果返回 " . var_export($r, true));
	}
	//充值添加动态
	public function KF_recharge($msg)
	{
		$company = $this->getCompany($msg['company_id'] ?? 0);
		$money = $msg['money'] ?? 0;
		$con = "充值金额：￥{$money}";
		$r = $this->sendScrm(
			'addUserDynamic',
			$company->company_id,
			'充值',
			$con
		);
		echolog("scrm 添加充值动态-执行结果返回 " . var_export($r, true));
	}
};
