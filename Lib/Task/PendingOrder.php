<?php

namespace App\Lib\Task;
//处理saas生成有页面支付的通知
use App\Models\SetMeal;
use Illuminate\Support\Facades\Log;

class PendingOrder
{
	use Task;
	protected $cmds = ['push_pending_order'];
	public function push_pending_order($message)
	{
		try {

			$order_info = $message['order_info'];
			if ($order_info['on_trial'] == 0) {
				return Log::error('试用订单');
			}
			//获取加密订单
			//  $arr_url=explode('=',$order_info['defray_url']);
			$saas_order_id = $order_info['order_id'];
			//判断是否存在该订单
			$pending_info = \App\Models\PendingOrder::where('saas_order_id', $saas_order_id)->first();
			if ($pending_info) {
				return Log::error('该订单已存在');
			}
			//不存在计算金额存入待支付表
			//获取原价
			$meal_info = SetMeal::where('product_id', $message['order_info']['meal_key'])->first();
			$meal_info = json_decode(json_encode($meal_info), true);
			if (!$meal_info) {
				return Log::error('产品不存在');
			}
            if($meal_info['is_fixed'] == 1){
                $order_info['order_amount'] = ceil($order_info['order_amount']);
            }
			$now_price = $this->calcProductMoney($meal_info['price'], $order_info['product_unit'], $order_info['order_amount']);
			$savePendOrder = new \App\Models\PendingOrder;
			$savePendOrder->company_id      = $order_info['company_id'];
			$savePendOrder->meal_key        = $order_info['meal_key'];
			$savePendOrder->product_key     = $order_info['product_id_old'];
			$savePendOrder->add_time        = date('Y-m-d H:i:s');
			$savePendOrder->defray_url      = $order_info['defray_url'];
			$savePendOrder->saas_order_id   = $saas_order_id;
			$savePendOrder->order_money     = $now_price['money'];
			$savePendOrder->pro_num         = $order_info['order_amount'];
			$savePendOrder->end_time        = date('Y-m-d H:i:s', strtotime('+7 day'));
			$savePendOrder->id6d        = $order_info['id6d'];
			$savePendOrder->save();
		} catch (\Exception $e) {

			return Log::error($e->getMessage());
		}
	}
	/**
	 * 根据产品开通数量和开通单位，计算具体扣除金额 暂时先放在这里后期放在公共方法中 ***
	 */
	public function calcProductMoney($pro_price, $pro_unit, $pro_num, $mixed_count = 1)
	{
		if (empty($mixed_count)) {
			$mixed_count = 1;
		}
		switch ($pro_unit) {
			case 1:  //时间  金额 = 产品价格 * 比率
				$money = $pro_price * $pro_num;
				break;
			case 2:  //条    金额 = 单条单价 * 条数
				$money = $pro_price * $pro_num;
				break;
			case 3:  //分钟  金额 = 单条单价 * 分钟数
				$money = $pro_price * $pro_num;
				break;
			case 4:  //金额
				$money = $pro_num;
				break;
			case 5:  //按产品计算
				$money = $pro_price * $pro_num;
				break;
			case 6:  //按产品数量*最大并发数
				$money = $pro_price * $pro_num * $mixed_count;
				break;
			default:
				return array('money' => 0);
		}
		//金额不足一分的按一分算 ,大于一分的四舍五入 保留两位小数
		if ($money < 0.01 && $money > 0) {
			$money = 0.01;
		} else {
			$money = round($money, 2);
		}
		return array('money' => $money);
	}
};
