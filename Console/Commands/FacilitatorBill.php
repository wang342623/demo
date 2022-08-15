<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Facilitator\Facilitator;
use App\Models\Facilitator\FundCharge;
use App\Models\Facilitator\Order;
use App\Models\Analysis\FacilitatorStatistics;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Log;
use function Symfony\Component\String\s;

class FacilitatorBill extends Command
{
    use StatsMonthTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'FBill {start?} {end?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '每个月月初执行 计算上个月服务商账号当月消费详情';

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
     * Execute the console command.
     *
     * @return int
     */
    public function handleMonth($create_time)
    {

        //计算上个月服务商资金明细以及提成 存入提成总汇表
        Log::setDefaultDriver('remit');
        Log::info('开始执行----' . date('Y-m-d H:i:s'));
        //批量处理
        $user_arr = Facilitator::select('company_id', 'facilitator_id', 'register_time')->get();
        $user_arr = empty($user_arr) ? array() : $user_arr->toArray();

        //获取起始结束时间
        $time_arr = getMonthDays($create_time);
        Log::info('get_time----' . json_encode($time_arr, true));
        foreach ($user_arr as $k => $v) {
            try {
                $ress = $this->detailed_statistics($v['facilitator_id'], $v['company_id'], $time_arr, $create_time, $v['register_time']);
                //                Log::info($v['company_id'].'==========aaa========'.var_export($ress,true));
            } catch (\Exception $e) {
                Log::info('error_massage=====' . $e->getMessage());
            }
        }
    }

    public function detailed_statistics($facilitator_id, $company_id, array $time_arr, $create_time, $register_time)
    {
        if (empty($company_id)) {
            Log::info('empty company_id====' . var_export($time_arr, true));
            return false;
        }
        //查询时间内服务商订单表充值金额
        $order_info = Order::where('company_id', $company_id)
            ->where('order_time', '>=', $time_arr['start_time'])
            ->where('order_time', '<=', $time_arr['end_time'])
            ->where('order_type', 1)
            ->get();

        $order_info = empty($order_info) ? array() : $order_info->toArray();
        //查询对应快服网账号信息
        $company_info = Company::select('master_account', 'company_id')->where('company_id', $company_id)->first();
        $company_info = empty($company_info) ? array() : $company_info->toArray();
        if (empty($company_info)) {
            Log::info('empty company_info====' . var_export(json_decode($company_id, true), true));
            return false;
        }
        //        Log::info($company_id . '---' . $time_arr['start_time'] . '~' . $time_arr['end_time'] . '===' . var_export(count($order_info), true));
        $register_time = date('Y-m', strtotime($register_time));
        $recharge_amount = 0;    //充值金额,
        $consumer_amount = 0;    //消费金额,
        $recharge_coupon = 0;    //充值优惠券,
        $consumer_coupon = 0;    //消费优惠券,
        $commission_statistics = 0;    //提成统计,
        $opening_balance = 0; //期初余额
        $closing_balance = 0; //期末余额
        $new_account = false; //是否新开账户
        if ($order_info) {
            foreach ($order_info as $k => $v) {
                $settlement_time = date('Y-m', strtotime($v['settlement_time']));
                switch ($v['meal_key']) {
                    case 'agent_cash': //充值金额
                        $recharge_amount = bcadd($recharge_amount, $v['order_price'], 4);
                        break;
                    case 'agent_coupon': //充值优惠券
                        $recharge_coupon = bcadd($recharge_coupon, $v['order_price'], 4);
                        break;
                    case 'agent_empower': //特殊套餐充值
                        $recharge_amount = bcadd($recharge_amount, config('same.agent_empower.money'), 4);
                        $recharge_coupon = bcadd($recharge_coupon, config('same.agent_empower.coupon'), 4);
                        $new_account = $settlement_time == $register_time;
                        break;
                    case 'empower_cloud':
                        $new_account = $settlement_time == $register_time;
                        break;
                }
            }
        }
        //查询消费金额以及提成
        $xftc_sum = Order::select(DB::raw('sum(order_cost_price) as consumer_amount,sum(pay_coupons) as consumer_coupon,sum(order_cost_price * inner_rate) as commission_statistics'))
            ->leftJoin('53cloud.cloud_product as pro', 'pro.product_id', '=', 'order.meal_key')
            ->where('facilitator_id', $facilitator_id)
            ->where('order_time', '>=', $time_arr['start_time'])
            ->where('order_time', '<=', $time_arr['end_time'])
            ->where('order_type', 1)
            ->first();

        $xftc_sum = empty($xftc_sum) ? array() : $xftc_sum->toArray();
        //查询上月最后一条订单时的余额
        $opening_order_info = FundCharge::select('id', 'capital')->where('facilitator_id', $facilitator_id)->where('charge_time', '<', $time_arr['start_time'])->orderBy('charge_time', 'desc')->orderBy('id', 'desc')->first();
        if (!empty($opening_order_info)) {
            $opening_balance = $opening_order_info->capital;
        }
        //查询当月最后一条订单时的余额
        $closing_order_info = FundCharge::select('id', 'capital')->where('facilitator_id', $facilitator_id)->where('charge_time', '<=', $time_arr['end_time'])->orderBy('charge_time', 'desc')->orderBy('id', 'desc')->first();
        if (!empty($closing_order_info)) {
            $closing_balance = $closing_order_info->capital;
        }
        //插入数据库
        $datas = [
            'company_id' => $company_id,
            'facilitator_id' => $facilitator_id,
            'kf_account' => $company_info['master_account'],
            'recharge_amount' => $recharge_amount,
            'consumer_amount' => $xftc_sum['consumer_amount'] ?? 0,
            'recharge_coupon' => $recharge_coupon,
            'consumer_coupon' => $xftc_sum['consumer_coupon'] ?? 0,
            'commission_statistics' => $xftc_sum['commission_statistics'] ?? 0,
            'add_time' => date('Y-m-d', strtotime($create_time)),
            'new_account' => $new_account ? 1 : 0,
            'opening_balance' => $opening_balance,
            'closing_balance' => $closing_balance,
        ];

        Log::info('save_data' . var_export($datas, true));
        //判断插入月的公司是否存在,存在则修改
        $res = FacilitatorStatistics::updateOrCreate(
            ['company_id' => $company_id, 'add_time' => date('Y-m-d', strtotime($create_time))],
            $datas
        );
        return $res;
    }
}
