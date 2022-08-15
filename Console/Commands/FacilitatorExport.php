<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Facilitator\Facilitator;
use App\Models\Facilitator\Order;
use App\Models\NewOrder as KfwOrder;
use App\Models\Company;
use App\Models\Bill;
use DB;

class FacilitatorExport extends Command
{
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'FacilitatorExport {fid} {type=all}';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = '导出服务商数据';

	/**
	 * Create a new command instance.
	 *
	 * @return void
	 */
	public function __construct()
	{
		parent::__construct();
	}
	private function row(array $data)
	{
		foreach ($data as &$value) {
			$value = str_replace(',', ';', $value);
		}
		return mb_convert_encoding('"' . str_replace(',', '","', implode(',', array_values($data))) . '"', "GBK", "UTF-8");
	}
	static $meal_arr = [];
	private function getMeal(string $meal_key)
	{
		if (!isset(self::$meal_arr[$meal_key])) {
			self::$meal_arr[$meal_key] = [
				\App\Models\SetMeal::findKey($meal_key), //套餐基本信息
				\App\Models\MemberGrade::findKey($meal_key), //套餐折扣信息
				\App\Models\MealExtend::findKey($meal_key) //套餐扩展信息
			];
		}
		return self::$meal_arr[$meal_key];
	}
	/**
	 * Execute the console command.
	 *
	 * @return int
	 */
	public function handle()
	{
		$fid_arg = $this->argument('fid');
		if ($fid_arg == 'repair') { //需要先修复数据
			return $this->repair();
		}
		$facilitator_id = intval($fid_arg);
		if ($facilitator_id <= 0) {
			echo ("服务商ID参数错误" . PHP_EOL);
			return 1;
		}
		$facilitator = Facilitator::find($facilitator_id);
		if (empty($facilitator)) {
			echo ("服务商ID不存在" . PHP_EOL);
			return 1;
		}
		$type = explode('|', $type_str =  $this->argument('type'));
		$handle = [
			'order' => 'orderEx',
			'kfw' => 'kfwOrderEx',
			'kfwbill' => 'kfwBillEx',
		];
		foreach ($handle as $key => $value) {
			if ($type_str == 'all' || in_array($key, $type)) {
				$this->$value($facilitator_id, $facilitator);
			}
		}
		echo PHP_EOL . date("Y-m-d H:i:s") . PHP_EOL;
		echo "服务商余额：{$facilitator->capital} {$facilitator->coupon}";
		echo PHP_EOL;
		return 0;
	}
	//导出快服网账单
	private function kfwBillEx(int $facilitator_id, $facilitator)
	{
		$company = Company::where('company_id', $facilitator->company_id)->first();
		$tables = ['cloud_bill_twenty_split', 'cloud_bill'];
		$select = [
			'bill_num' => '流水号', 'bill_type' => '账单类型(0收入1支出)', 'pay_type' => '充值方式',
			'order_date' => '时间', 'money' => '金额', 'balance' => '总余额',
			'withdraw_balance' => '可提现余额', 'unwithdraw_balance' => '不可提现余额',
			'remark' => '备注'
		];
		$title =  $this->row($select);
		$order_file = base_path("facilitator/{$facilitator_id}kfwbill.csv");
		file_put_contents($order_file, $title . "\r\n");
		foreach ($tables as $tab_name) {
			echo "正在处理:{$tab_name}" . PHP_EOL;
			$tab = (new Bill)->setTable($tab_name);
			$bar = $this->output->createProgressBar($tab->where('user_id', $company->id)->count());
			$bar->start();
			$list = $tab->select(...array_keys($select))->where('user_id', $company->id)->orderBy('bill_num', 'asc')->get();
			$list->map(function ($value) use ($order_file, &$bar) {
				file_put_contents($order_file, $this->row($value->toArray()) . "\r\n", FILE_APPEND);
				$bar->advance();
			});
			$bar->finish();
			$this->output->newLine();
		}
	}
	//导出开服网订单
	private function kfwOrderEx(int $facilitator_id, $facilitator)
	{
		//导出快服网订单
		$tables = ['cloud_new_order_twenty_split', 'cloud_new_order'];
		$select = [
			'order_num' => '订单编号', 'order_time' => '时间', 'order_type' => '订单类型',
			'company_id' => '公司ID', 'id6d' => '工号id', 'product_name' => '套餐名',
			'product_id' => '套餐键值', 'order_mc' => '支付总金额', 'pay_money' => '资金',
			'pay_coupons' => '优惠券', 'pro_other' => '备注'
		];

		$title =  $this->row($select);
		$order_file = base_path("facilitator/{$facilitator_id}kfw.csv");
		file_put_contents($order_file, $title . "\r\n");


		foreach ($tables as $tab_name) {
			$tab = (new KfwOrder)->setTable($tab_name);
			$id = 0;
			echo "正在处理:{$tab_name}" . PHP_EOL;
			$bar = $this->output->createProgressBar($tab->where('company_id', $facilitator->company_id)->count());
			$bar->start();
			do {
				$list = $tab->join('cloud_product', "{$tab_name}.pro_id", '=', 'cloud_product.id')
					->select(...array_keys($select))
					->where('company_id', $facilitator->company_id)
					->where('order_num', '>', $id)
					->orderBy('order_num', 'asc') //从小到大排序
					->limit(1000)->get();
				$list->map(function ($value) use (&$id, $order_file, &$bar) {
					$id = $value->order_num > $id ? $value->order_num : $id;
					file_put_contents($order_file, $this->row($value->toArray()) . "\r\n", FILE_APPEND);
					$bar->advance();
				});
				$num = $list->count();
			} while ($num == 1000);
			$bar->finish();
			$this->output->newLine();
		}
	}
	//导出订单
	private function orderEx(int $facilitator_id)
	{
		$tables = ['order_twenty_split', 'order'];
		$select = [
			'order_id' => '订单ID',
			'order_key' => '订单编号', 'product_key' => '产品标识', 'meal_key' => '套餐标识',
			'company_id' => '公司ID', 'id6d' => '工号ID', 'order_amount' => '订单数量',
			'mixed_count' => '并发数', 'order_time' => '订单时间', 'order_cost_price' => '订单扣费',
			'order_type' => '订单类型', 'facilitator_id' => '服务商编号', 'order_remarks' => '备注'
		];
		$title =  $this->row($select + ['price' => '应扣']);
		$order_file = base_path("facilitator/{$facilitator_id}order.csv");
		file_put_contents($order_file, $title . "\r\n");
		foreach ($tables as $tab_name) {
			$id = 0;
			echo "正在处理:{$tab_name}" . PHP_EOL;
			$tab = DB::connection('facilitator')->table($tab_name);
			$bar = $this->output->createProgressBar($tab->where('facilitator_id', $facilitator_id)->count());
			$bar->start();
			do {
				$list = $tab->select(...array_keys($select))
					->where('facilitator_id', $facilitator_id)
					->where('order_id', '>', $id)
					->orderBy('order_id', 'asc') //从小到大排序
					->limit(1000)->get()->toArray();
				foreach ($list as $value) {
					$id = $value->order_id > $id ? $value->order_id : $id;
					$row = (array)$value + ['price' => $this->getPrice($value)];
					file_put_contents($order_file, $this->row($row) . "\r\n", FILE_APPEND);
					$bar->advance();
				}
				$num = count($list);
			} while ($num == 1000); //limit(1000)不可能出现大于1000的情况,当小于1000时说明没有了
			$bar->finish();
			$this->output->newLine();
		}
	}
	private function getPrice($order)
	{
		$meal_info = $this->getMeal($order->meal_key);

		$meal = $meal_info[0];
		$discount = $meal_info[1];
		$extend = $meal_info[2];

		if (empty($meal) || empty($discount) || empty($extend)) {
			return  "丢失";
		}
		$price = $meal->price * $discount->gold_discount * $order->order_amount; //单价*金卡折扣*订单数量
		if ($extend->product_unit == 6) {
			$price = $price * $order->mixed_count;
		}
		//最大接听数在2020年4月以后开始优惠
		if ($meal->meal_key == 'link_server' && $order->facilitator_id == 28 && $order->order_time > '2020-04') {
			$price = $price * 0.5; //好315打五折
		}
		return $price;
	}
	private function repair()
	{
		$tables = ['order_twenty_split', 'order']; //待修复的订单表
		$check_company = [];
		$i = 0;
		foreach ($tables as $tab_name) {

			$tab = (new Order)->setTable($tab_name);
			$list = $tab->where('facilitator_id', 0)->get();
			echo "正在处理:{$tab_name}" . PHP_EOL;
			$bar = $this->output->createProgressBar($tab->where('facilitator_id', 0)->count());
			$bar->start();
			foreach ($list as &$order) {
				// $this->performTask($order);
				$bar->advance();
				if (isset($check_company[$order->company_id])) { //已经处理过了
					continue;
				}
				$company = DB::connection('facilitator')->table('company')->where('company_id', $order->company_id)->first();
				if (empty($company)) {
					continue;
				}
				$order->facilitator_id = $company->facilitator_id;
				$order->save();
				$i++;
			}
			$bar->finish();
			$this->output->newLine();
		}
		var_dump($i);
		return 0;
	}
}
