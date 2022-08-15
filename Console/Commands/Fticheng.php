<?php

namespace App\Console\Commands;

use App\Models\Analysis\FacilitatorOpenCommission;
use App\Models\Facilitator\Facilitator;
use App\Models\Facilitator\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Log;

class Fticheng extends Command
{
    use StatsMonthTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Fticheng {start?} {end?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '服务商首开提成统计';

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
        //获取起始结束时间
        $time_arr = getMonthDays($create_time);
        //获取当月订单是否存在
        $order_info = Order::where('meal_key', 'agent_empower')->where('settlement_time', '>=', $time_arr['start_time'])->where('settlement_time', '<=', $time_arr['end_time'])->get();
        $order_info = empty($order_info) ? array() : $order_info->toArray();
        if (!empty($order_info)) {
            foreach ($order_info as $v) {
                $this->saveTable($v['company_id'],$v['settlement_time']);
            }
        }
        //获取分期订单
        $sum_num_info = Order::select(DB::raw('sum(order_amount) as num , company_id , facilitator_id , settlement_time'))
            ->where('meal_key', 'agent_cash')
            ->groupBy('company_id')
            ->having('num', '>=', 50000)
            ->get();
        $sum_num_info = empty($sum_num_info) ? array() : $sum_num_info->toArray();
        if (!empty($sum_num_info)) {
            foreach ($sum_num_info as $v) {
              $this->saveTable($v['company_id'],date('Y-m-d'));
            }
        }
        echo 'end';
        exit;
    }


    public function saveTable($company_id,$settlement_time)
    {
        $facilitator_info = Facilitator::where('company_id', $company_id)->first();
        if(empty($facilitator_info)){
            return;
        }
        $data = [
            'facilitator_id' => $facilitator_info->facilitator_id,
            'facilitator_name' => $facilitator_info->facilitator_name,
            'company_id' => $facilitator_info->company_id,
            'account' => $facilitator_info->fa_account,
            'ticheng_money' => 2000,
            'open_month' => $settlement_time,
        ];

        $obj = new FacilitatorOpenCommission();
        $fac_ticheng_info = $obj->where('facilitator_id', $data['facilitator_id'])->first();
        if (!empty($fac_ticheng_info)) {
            return;
        }
        foreach ($data as $k => $v) {
            $obj->$k = $v;
        }
        $obj->save();
    }
}
