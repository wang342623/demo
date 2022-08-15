<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Lib\mTalkApi;
use App\Lib\TalkApi;
use App\Models\Analysis\MealUsage;
use App\Models\Facilitator\Order;
use DB;

class MealUsageTask extends Command
{
	use StatsDayTrait;
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'MealUsage {action?} {start?} {end?}';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = '统计套餐使用量';

	/**
	 * Create a new command instance.
	 *
	 * @return void
	 */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * 执行一天的数据
	 *
	 * @param string $date
	 * @return void
	 */
	private function handleOneDay(string $date)
	{
		echolog("MealUsageTask:{$date}");
		$this->checkEmptyNumber($date); //空号检测统计
		if ($this->isInit() && $date > $this->getStartDate()) {
			return; //初始化的时候，追问线索只需要操作第一天的
		}
		$this->askClues($date); //追问线索统计
		$this->handleOrder($date, 'kefu_callback'); //统计网页回呼
	}
	/**
	 * 统计订单数据
	 *
	 * @param string $date 日期
	 * @param string $meal_key 套餐key
	 * @param array $basic_data 基础数据 键是类型-公司ID，值是数量
	 * @return void
	 */
	private function handleOrder(string $date, string $meal_key, array $basic_data = [])
	{
		$order_list = Order::where('order_time', '>=', $date)
			->where('order_time', '<=', $date . ' 23:59:59')
			->where('meal_key', $meal_key)
			->get();
		foreach ($order_list as $order) {
			//可以查看 kfadmin/config/same.php 文件中的配置
			if (empty($meal_key_source = $order->meal_key_source)) { //套餐渠道是空的
				continue;
			}
			$company_id = $order->company_id;
			if (!isset($basic_data[$meal_key_source])) {
				$basic_data[$meal_key_source] = [];
			}
			$num = $basic_data[$meal_key_source][$company_id] ?? 0;
			$basic_data[$meal_key_source][$company_id] = $num + 1; //在原有基础上+1
		}
		foreach ($basic_data as $type => $com_arr) {
			foreach ($com_arr as $company_id => $num) {
				//company_id+type+usage_date 是唯一的
				$new_data = [
					'company_id' => $company_id,
					'type' => $type,
					'usage_date' => $date,
				];
				echolog("{$type}->" . var_export($new_data + ['num' => $num], true));
				$MealUsage = MealUsage::firstOrNew($new_data);
				$MealUsage->usage_num = $num;
				$MealUsage->save(); //更新
			}
		}
	}
	private function checkEmptyNumber(string $date)
	{
		echo "checkEmptyNumber开始执行{$date}" . PHP_EOL;
		//查接口,千次包的使用量
		$list = [];
		$back = function () use ($date, &$list) {
			$arr = (new mTalkApi)->emptyNumber($date, $date); // date("Y-m-d", strtotime($date) + 24 * 3600 + 1)
			if (is_array($arr)) { //返回数组即请求成功
				$list = $arr;
				return true;
			}
			return false;
		};
		successRepeatCall($back);
		echolog("emptyNumber 返回数量：" . count($list));
		// echo PHP_EOL . "mTalkApi::emptyNumber-->" . var_export($list, true) . PHP_EOL;
		$all_data = [];
		foreach ($list as  $value) {
			$third_type = $value['third_type']; //jy是聚赢,dx是玄武，lt是联通
			$company_id = $value['company_id'];
			$num = $value['empty_monitor_count'];
			if (!isset($all_data[$third_type])) {
				$all_data[$third_type] = [];
			}
			$all_data[$third_type][$company_id] = $num;
		}
		//查订单，单次的使用量
		$this->handleOrder($date, 'phone_isnull', $all_data);
	}
	/**
	 * 追问线索统计
	 *
	 * @param string $date 开始日期
	 * @return void
	 */
	private function askClues(string $date)
	{
		echo PHP_EOL . "askClues开始执行{$date}" . PHP_EOL;
		$alias_list = DB::connection('kf')->table('company')->groupBy('alias')->pluck('alias');
		foreach ($alias_list as &$alias) {
			$this->fzAskClues($alias, $date); //处理单个分组
		}
		echo PHP_EOL . "askClues结束{$date}" . PHP_EOL;
	}
	/**
	 * 处理单个分组追问线索的数据
	 *
	 * @param string $alias 分组域名
	 * @param string $date 开始日期
	 * @return void
	 */
	private function fzAskClues(string $alias, string $date)
	{
		$talk_ids = [];
		$start_time = "{$date} 00:00:00";
		$end_date = $this->getEndDate();
		$end_time = "{$end_date} 23:59:59";
		$list  = [];
		$back = function (string $alias, string $time) use (&$list) {
			var_dump("{$time}/{$alias}");
			$TalkApi = new TalkApi;
			//取大于等于$time的1000条数据
			$list = $TalkApi->getRTaskTimes($alias, $time);
			if (is_array($list)) { //空数据转为bool时是false
				return true;
			}
			$list = [];
			return false;
		};
		while ($start_time <= $end_time) { //超过结束时间的不需要统计
			successRepeatCall(
				$back,
				10,
				1,
				$alias,
				$start_time
			);
			$start_time = $this->handleAskList($list, $talk_ids, $end_time);
			if (count($list) < 1000) { //可能是没有数据了，也可能是该分组不可用，请求失败
				break;
			}
		}
	}
	/**
	 * 处理talk接口返回追问线索的数据
	 *
	 * @param array $list
	 * @param array $talk_ids	用于判断talk_id重复
	 * @param string $end_time  统计的结束时间
	 * @return string 返回list中最大的时间(时间格式为2021-08-23 16:14:33)
	 */
	private function handleAskList(array &$list, array &$talk_ids, string $end_time): string
	{
		$db_data = []; //要保存到数据库的数据
		$max_time = ''; //统计最大时间
		foreach ($list as &$value) {
			$task_time = $value['task_time'];
			$max_time = $max_time < $task_time ? $task_time : $max_time;
			if ($value['task_time'] > $end_time) {
				continue; //超过结束时间的数据直接不统计
			}
			if (isset($talk_ids[$value['talk_id']])) {
				continue; //之前已经统计了
			}
			$talk_ids[$value['talk_id']] = true;
			$v_date = substr($task_time, 0, 10);
			$company_id = $value['company_id'];
			if (!isset($db_data[$company_id])) {
				$db_data[$company_id] = [];
			}
			if (!isset($db_data[$company_id][$v_date])) {
				$db_data[$company_id][$v_date] = 0;
			}
			$db_data[$company_id][$v_date]++;
		}
		foreach ($db_data as $company_id => $com_arr) {
			foreach ($com_arr as $usage_date => $num) {
				$new_data = [
					'company_id' => $company_id,
					'type' => 'ask_clues',
					'usage_date' => $usage_date,
				];
				Log::info("askClues->" . var_export($new_data + ['num' => $num], true));
				$MealUsage = MealUsage::firstOrNew($new_data);
				$MealUsage->usage_num = $num;
				$MealUsage->save(); //更新
			}
		}
		return $max_time;
	}
}
