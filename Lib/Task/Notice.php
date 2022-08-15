<?php

namespace App\Lib\Task;

use App\Lib\Task\Task;
use App\Models\Company;
use App\Models\Deduction;
use App\Models\NewOrder;
use App\Models\SetMeal;
use App\Models\Worker;
use Illuminate\Support\Facades\Log;
use WxApi;

/***
 * 提醒推送方法
 */
class Notice
{
    use Task;

    //消费提醒
    public function open_push($data)
    {
        try {
            //参数获取
            $request = $data['order'];
            $save_data = $this->paramAuth($request);
            Log::info('[$save_data]:' . var_export($save_data, true));
            if (empty($save_data)) return;
            Log::info('[openOrderReminder]:' . var_export($save_data, true));
            //发送消费通知
            $res = WxApi::openOrderReminder($save_data['open_id'], $save_data['order_id'], $save_data['open_time'], $save_data['purchase_details'], $save_data['pay_coupons'], $save_data['pay_money']);
            Log::info('[openOrderReminder]:' . var_export($res, true));
        } catch (\Exception $e) {
            Log::info('[Error error 消费提醒]:' . var_export($e->getMessage(), true));
        }
    }

    //自动续费开关开启提醒
    public function renew_switch($data)
    {

        if ($data['auto_renew'] == 0 || $data['old_auto_renew'] == 1) { #关闭的不提醒
            return;
        }
        if ($data['account_type'] == 'worker') {
            //获取工号信息
            $worker_info = Worker::where('company_id', $data['company_id'])->where('id6d', $data['id6d'])->first();
            if (empty($worker_info)) {
                return;
            }
        }
        $master_worker_info = Worker::where('company_id', $data['company_id'])->where('account_type', '1')->first();
        //查询套餐信息
        $set_meal_info = SetMeal::where('product_id', $data['meal_key'])->where('category_id', $data['product_key'])->first();
        if (empty($set_meal_info)) {
            return;
        }
        WxApi::renewSwitchReminder($master_worker_info->wxid, empty($worker_info) ? $data['account'] : $worker_info->sub_account, $set_meal_info->product_name);
    }

    //参数处理
    public function paramAuth($request): array
    {
        $save_data = [];
        //判断是否是快服网账户 不是快服网账户跳出
        if ($request['facilitator_id'] != config('custom.facilitator_id')) {
            return $save_data;
        }
        $company_info = Company::findById($request['company_id']);
        //公司不存在跳出
        if (empty($company_info)) {
            return $save_data;
        }
        //套餐有误 //金额小于十元则不提醒
        $set_meal_info = SetMeal::where('product_id', $request['meal_key'])->first();
        if (empty($set_meal_info) || $set_meal_info->price < 10) {
            return $save_data;
        }
        //获取主账号wxid
        $master_worker_info = Worker::where('account_type', '1')->where('company_id', $request['company_id'])->first();
        if (empty($master_worker_info) || empty($master_worker_info->wxid)) {
            return $save_data;
        }
        //判断是否是普通账号 普通账户记录 id6d 特殊账户记录 特殊账户id
        if ($request['account_type'] == 'worker') {
            //普通账号查询worker表
            $worker_info = Worker::where('id6d', $request['id6d'])->where('company_id', $request['company_id'])->first();
            if (empty($worker_info)) { #工号不存在
                Log::info('id6d jump');
                return $save_data;
            }
            //生成购买明细
            $save_data['purchase_details'] = "用户" . $worker_info->sub_account . "开通套餐【" . $set_meal_info->product_name . "】";
            $save_data['id6d'] = $request['id6d'];
            $save_data['account_type'] = 1;
        } else {
            //生成购买明细
            $save_data['purchase_details'] = "开通套餐【" . $set_meal_info->product_name . "】";
            //查询特殊账号表是否存在
            $save_data['special_account_id'] = $request['dec_account_id'];
            $save_data['account_type'] = 2;
        }
        $save_data['company_id'] = $request['company_id'];              #公司id
        $save_data['auto_renew'] = $request['auto_renew'];              #是否开启自动续费
        $save_data['product_key'] = $request['product_id_old'];         #产品id
        $save_data['meal_key'] = $request['meal_key'];                  #套餐id
        $save_data['product_unit'] = $request['product_unit'];          #产品类型
        $save_data['order_amount'] = $request['order_amount'];          #开通数量
        $save_data['expire_time'] = $request['now_expire_time'] ?? '';  #到期时间
        $save_data['start_time'] = $request['order_time'];              #开始时间
        $save_data['on_trial'] = $request['on_trial'] ?? '1';           #是否是试用
        $save_data['mixed_count'] = $request['mixed_count'];            #最大并发数
        $save_data['pay_money'] = $request['pay_money'];                #支付金额
        $save_data['pay_coupons'] = $request['pay_coupons'];            #支付优惠券
        $save_data['order_remarks'] = $request['order_remarks'];        #备注
        $new_order_info = '';
        while (empty($new_order_info)) {
            $new_order_info = NewOrder::where('saas_order_id', $request['order_id'])->first();
        }
        $save_data['order_id'] =  $new_order_info->id;                  #订单编号
        //查询抵扣表是否使用
        $ded_info = Deduction::where('order_id', $new_order_info->id)->first();
        if ($ded_info) {
            $save_data['pay_coupons'] += $ded_info->max_money;
        }
        //查询订单id
        $save_data['open_id'] = $master_worker_info->wxid;              #openid
        $save_data['open_time'] = $request['open_time'];                #下单时间

        return $save_data;
    }
    //充值通知
    public function KF_recharge($data)
    {
        $company_id = intval($data['company_id'] ?? 0);
        $id6d = intval($data['id6d'] ?? 0);
        $money = round(floatval($data['money'] ?? 0), 2);
        $time = $data['add_time'] ?? time();

        if ($company_id == 0 || $money == 0) {
            return;
        }
        $main = Worker::where('company_id', $company_id)->where('account_type', '1')->first();
        $woker = null;
        if ($id6d) {
            $woker = Worker::where('company_id', $company_id)->where('id6d', $id6d)->first();
        }
        if ($woker && $woker->wxid) {
            echo "通知微信公众号{$woker->wxid}" . PHP_EOL;
            WxApi::rechargeReminder($woker->wxid, decryptAesCBC($woker->master_account), $money, $time);
        }
        if ($main && $main->wxid) {
            echo "通知微信公众号{$main->wxid}" . PHP_EOL;
            WxApi::rechargeReminder($main->wxid, decryptAesCBC($main->master_account), $money, $time);
        }
    }
}
