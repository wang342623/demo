<?php

namespace App\Console\Commands;

use Log;

/**
 * 按天统计,默认前一天 trait
 */
trait StatsDayTrait
{
	public function handle()
	{
		Log::setDefaultDriver('timing');
		Log::info(__CLASS__ . "开始执行");
		if ($this->isInit()) {
			return $this->handleInit();
		}
		$date = date('Y-m-d', strtotime('-1 days'));
		if (isDeve()) {
			$date = date('Y-m-d');
		}
		$this->handleOneDay($date); //默认统计前一天的
		Log::info(__CLASS__ . "结束");
		return 0;
	}
	/**
	 * 判断是否初始化
	 *
	 * @return boolean
	 */
	private function isInit(): bool
	{

		return $this->argument('action') == 'init';
	}
	//初始化
	private function handleInit()
	{
		$start_date = $this->getStartDate();
		$end_date = $this->getEndDate();
		if ($start_date > $end_date) {
			Log::error("start_time>end_time");
			throw (new Exception("start_time>end_time"));
			return 0;
		}
		for ($i = $start_date; $i <= $end_date; $i = date("Y-m-d", strtotime("{$i} +1 days"))) {
			echo $i . PHP_EOL;
			$this->handleOneDay($i);
		}
		return 0;
	}
	private function getStartDate()
	{
		$start = $this->argument('start');
		if (empty($start)) {
			$msg = __CLASS__ . "缺少开始日期";
			Log::error($msg);
			throw (new Exception($msg));
			return 0;
		}
		return $start;
	}
	private function getEndDate()
	{
		$end_date = date('Y-m-d', strtotime('-1 days'));
		if (isDeve()) {
			$end_date = date('Y-m-d');
		}
		$end = $this->argument('end') ?? $end_date;
		return $end;
	}
}
