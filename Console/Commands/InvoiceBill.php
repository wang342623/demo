<?php

namespace App\Console\Commands;

use App\Models\Bill;
use App\Models\Company;
use App\Models\MonthInvoiceBill;
use App\Models\NewOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InvoiceBill extends Command
{
    use StatsMonthTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoiceBill {start?} {end?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '按月合并发票';

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
        echolog(var_export($create_time, true));
        //获取起始结束时间
        $time_arr = getMonthDays($create_time);
        echolog('时间区间' . var_export($time_arr, true));
        //获取所有公司
        Company::where('member_grade', '>', '0')->orderBy('id')->chunk('5000', function ($users) use ($time_arr) {
            $users = json_decode(json_encode($users), true);
            foreach ($users as $k => $v) {
                try {
                    //获取当前公司指定区间内是否存在订单 cloud_new_order 这里月底自动续费订单按照下月计算
                    $month_invoice_money = Bill::from('cloud_bill as b')->select(DB::raw('sum(money) as money,
                                sum(case
                                when e.product_unit = 2 then money
                                when e.product_unit = 3 then money
                                when e.product_unit = 4 then money
                                when e.product_unit = 5 then money
                                else 0
                                end
                                ) as product_bill_money'))
                        ->leftJoin('cloud_new_order as o', 'b.order_num', '=', 'o.id') #关联订单表
                        ->leftJoin('cloud_product as p', 'o.pro_id', '=', 'p.id')
                        ->leftJoin('cloud_product_extend as e', 'p.product_id', '=', 'e.product_id')
                        ->where('user_id', $v['id'])
                        ->where('bill_type', '1') #订单类型 支出
                        ->where('arrive_date', '>=', $time_arr['start_time']) #支付时间
                        ->where('arrive_date', '<=', $time_arr['end_time']) #支付时间
                        ->where('is_receipt', '0') #未开票订单
                        ->first();

                    $month_invoice_money = empty($month_invoice_money) ? array() : $month_invoice_money->toArray();
                    if (!empty($month_invoice_money['money'])) {
                        echolog('invoice money ' . var_export(['money' => $month_invoice_money, 'company_id' => $v['company_id']]));
                    }
                    if (empty($month_invoice_money['money'])) {
                        continue; #没有金额跳出本次循环
                    }
                    $data = [
                        'invoice_name' => date('Y年m月', strtotime($time_arr['start_time'])),
                        'invoice_date' => date('Ym', strtotime($time_arr['start_time'])),
                        'invoice_money' => $month_invoice_money['money'],
                        'overdue_date' => date('Y-m-d', strtotime('+3 month', strtotime($time_arr['start_time']))),
                        'company_id' => $v['company_id'],
                        'cloud_company_id' => $v['id'],
                        'product_bill_money' => $month_invoice_money['product_bill_money'],
                        'increment_bill_money' => $month_invoice_money['money'] - $month_invoice_money['product_bill_money'],
                    ];
                    $invoice_model = MonthInvoiceBill::where('company_id', $v['company_id'])
                            ->where('invoice_date', date('Ym', strtotime($time_arr['start_time'])))
                            ->first() ?? new MonthInvoiceBill();
                    foreach ($data as $kk => $vv) {
                        $invoice_model->$kk = $vv;
                    }
                    $invoice_model->save();
                } catch (\Throwable $th) {
                    echolog($th->getFile() . "-->line:" . $th->getLine() . "-->message:" . $th->getMessage(), 'error');
                    return;
                }
            }
        });
    }
}
