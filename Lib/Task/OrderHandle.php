<?php

namespace App\Lib\Task;

use App\Models\NewOrder;
use App\Models\Company;
use App\Models\ProductMeal;
use App\Models\Products;
//处理快服网的订单数据
class OrderHandle
{
    use Task;
    //来自快服网支付系统的推送消息
    public function order_service($msg)
    {
        $info = $msg['info'] ?? [];
        if (empty($info) || is_array($info)) {
            echolog('order_service 订单数据为空', 'error');
            return;
        }
        //只处理订单支付的消息
        foreach ($info as $value) {
            if (isset($value['cmd']) && $value['cmd'] == 'insertNewOrder') {
                $this->insertNewOrder(json_decode($value['info'], true));
            }
        }
    }
    //只处理订单的数据,根据订单的数据，插入其他表的数据
    private function insertNewOrder($data)
    {
        if (empty($data)) {
            echolog('insertNewOrder 订单数据为空', 'error');
            return;
        }
        $data_data = $data['data'];
        unset($data['data']);
        //插入订单表
        $order = $this->insertOrder($data_data + $data);
    }
    //生成订单记录
    private function insertOrder($data)
    {
        if (empty($data)) {
            echolog('insertOrder 订单数据为空', 'error');
            return;
        }
        $saas_order_id = $data['saas_order_id'] ?? '';
        if (empty($saas_order_id)) {
            echolog('insertOrder saas_order_id为空', 'error');
            return;
        }
        $order = NewOrder::firstOrNew(['saas_order_id' => $saas_order_id]);
        $order_type = intval($data['order_type']);
        if ($order_type == 2) { //退款
            $order->init_order_id = $data['init_order_id'] ?? '';
            $order->init_saas_order_id = $data['init_saas_order_id'] ?? '';
        }
        //开通功能的公司
        $company = Company::findById($data['company_id']);
        $order->order_time = $data['order_time'] ?? ''; //订单时间
        $order->cloud_company_id = $company->id; //快服网自己库里的公司ID
        $order->company_id = intval($data['company_id']); //公司ID
        $order->master_account = $company->master_account; //公司主账号
        //代扣的公司，付钱的
        $pay_compay = Company::findById($data['pay_company_id']);
        $order->daikou_cloud_company_id = $pay_compay->id; //代扣公司ID
        $order->daikou_company_id = intval($data['pay_company_id']); //代扣saas公司ID

        if ($data['account_type'] == 'worker') { //如果是工号
            $worker = Worker::findByKey($data['account']);
            $order->workers_id = $worker->id; //工号表自增ID
            $order->id6d = $worker->id6d;
            $order->sub_account = $data['account']; //开通账号
        }
        $product = Products::find($data['cat_id']); //产品表自增ID
        $meal = Products::find($data['pro_id']); //套餐表自增ID

        $order->cat_id = intval($data['cat_id']);
        $order->pro_id = intval($data['pro_id']);
        //以下是余字段，防止套餐和产品信息后续发生变化
        $order->pro_unit = $meal->product_unit;
        //购买数量
        $order->pro_num = $data['pro_num'];
        //订单备注
        $order->pro_other = $data['pro_other'];
        //是否是新优惠券,2是老优惠券，老优惠券直接全额支付，优惠部分不再使用新优惠券抵扣   
        $order->is_new_coupons = intval($data['is_new_coupons']);
        //支付金额
        $real_pay_price = $data['real_pay_price'];
        //需支付总资金
        $order->order_mc = $order->is_new_coupons == 2 ? ($real_pay_price['pay_coupon'] + $real_pay_price['pay_money']) : $real_pay_price['pay_money'];
        //支付资金
        $order->pay_money = $real_pay_price['pay_money'];
        //支付优惠券
        $order->pay_coupons = $real_pay_price['pay_coupon'];
        //订单原价，如果使用了抵扣券，上面的支付金额会比这个小
        $order->no_discount_mc = $data['yuan_money'];
        //支付时的会员等级
        $order->pay_member_grade = $data['init_member_grade'];
        //支付时的产品单价
        $order->pay_product_price = $data['pay_product_price'];
        //订单结算的时间
        $order->pay_time = $data['real_order_time'];
        //如果不是并发类型的套餐，这里是0
        $order->mixed_count = $data['mixed_count'];
        //客户经理
        $kfjl = $company->customerManager;
        //支付时的客户经理ID
        $order->inner_user_id = $kfjl->id;
        //购买的总数量
        $total = $meal->product_unit == 6 ? ($order->mixed_count * $order->pro_num) : $order->pro_num;
        if ($meal->cost_type == 1) { //固定金额
            $order->cost = bcmul($total, $meal->cost, 4);
        }
        if ($meal->cost_type == 2) { //比例
            $order->cost = bcmul($order->pay_money, intval($meal->cost), 4);
            //cost存的是百分比，所以要除以100
            $order->cost = bcdiv($order->cost, 100, 4);
        }
    }
}
