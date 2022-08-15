<?php

namespace App\Console\Commands;

use App\Models\Facilitator\Account;
use App\Models\Facilitator\AccountProduct;
use Illuminate\Console\Command;
use Log;

class InitAccountProduct extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'InitAccountProduct';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '初始化库存';

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
    public function handle()
    {
        try {
            $account_product = new AccountProduct();//老库存
            $account_product->chunkById(1000, function ($product_info) {
                $account = new Account();//账号
                $new_account_product = new \App\Models\AccountProduct();//新库存
                foreach ($product_info as $v) {
                    //遍历查询每条数据对应的账号是否是特殊账号
                    $account_info = $account->where(['account_id' => $v['account_id'], 'company_id' => $v['company_id'],'facilitator_id'=>config('custom.facilitator_id')])->select('account_type', 'id6d')->first();
                    $account_info = empty($account_info) ? array() : $account_info->toArray();
                    if (empty($account_info['account_type'])){
                        continue;
                    }
                    $update_date = [
                        'company_id' => $v['company_id'],
                        'product_key' => $v['product_key'],
                        'meal_key' => $v['meal_key'],
                    ];
                    if ($account_info['account_type'] != 'worker') {
                        $account_type = 2;
                        $special_account_id = $v['account_key'];
                        $id6d = '';
                        $update_date['special_account_id'] = $special_account_id;
                    } else {
                        $account_type = 1;
                        $special_account_id = '';
                        $id6d = $account_info['id6d'];
                        $update_date['id6d'] = $id6d;
                    }
                    $date = [
                        'company_id' => $v['company_id'],
                        'product_key' => $v['product_key'],
                        'meal_key' => $v['meal_key'],
                        'id6d' => $id6d,
                        'auto_renew' => $v['auto_renew'],
                        'product_unit' => $v['product_unit'],
                        'order_amount' => $v['order_amount'],
                        'expire_time' => $v['expire_time'],
                        'start_time' => $v['start_time'],
                        'on_trial' => $v['on_trial'],
                        'mixed_count' => $v['mixed_count'],
                        'special_account_id' => $special_account_id,
                        'account_type' => $account_type,
                        'open_number' => 1,
                    ];
                    $new_account_product->updateOrCreate($update_date, $date);
                }
            });
        } catch (\Exception $e) {
            echo $e->getMessage();
        }
    }
}
