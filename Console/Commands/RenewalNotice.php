<?php

namespace App\Console\Commands;

use App\Models\AccountProduct;
use App\Models\Product;
use App\Models\SetMeal;
use App\Models\Worker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class RenewalNotice extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'RenewalNotice';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '自动续费提前（三天）通知';

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
        /*
        WHERE `account_product`.`auto_renew` = 1 -- 开启自动续费
        AND `company`.`is_delete` = 0 -- 公司未删除
        AND `account`.`is_delete` = 0 -- 账号未删除
        AND `account`.`resign_type` = 0 -- 未离职
        AND `product_meal`.`no_renewal` = 0 -- 套餐开启自动续费
        AND `product_meal`.`sell_status` = 1 -- 套餐未下架
        AND `product`.`sell_status` = 1 -- 产品未下架
        AND `company`.`stopping` = 1 -- 公司未停机*/

//      $expire_time = date('Y-m-d 23:59:59',strtotime('+3 day')); #获取三天后到期的公司
        $expire_time = date('Y-m-31 23:59:59', strtotime('+3 day'));

        //获取需要通知的公司
        AccountProduct::select('company_id')
            ->where('expire_time', $expire_time) #三天后到期
            ->where('auto_renew', 1) #自动续费开启的
            ->groupBy('company_id')
            ->orderBy('company_id', 'desc')->chunk(1000, function ($account_product_info) use ($expire_time) {
                $account_product_info = $account_product_info->isEmpty() ? array() : $account_product_info->toArray();
                foreach ($account_product_info as $k => $v) {
                    $infos = AccountProduct::where('expire_time', $expire_time)
                        ->where('auto_renew', 1)
                        ->where('company_id', $v['company_id'])
                        ->get();
                    $infos = $infos->isEmpty() ? array() : $infos->toArray();
                    $res = $this->renewWorker($infos);
                    if(!empty($res)){
                    echo "<pre>";
                    print_r($res);
                    echo "</pre>";
                    }
                }
            });
        return 0;
    }

    //获取公司下所有需要通知的账户信息
    public function renewWorker($infos)
    {
        if (empty($infos)) {
            return [];
        }
        $arr=[];
        //验证账户是否满足通知条件
        foreach ($infos as $k => $v) {
            //该公司是否停机
            $stopping = Redis::hget('com.info.{'.$v['company_id'].'}','stopping');
            if($stopping == 2){
                continue;
            }
            //产品是否下架或删除
            $product_info=Product::where('sell_status',1)->where('del_flag',0)->where('category_id',$v['product_key'])->first();
            if(empty($product_info)){
                continue;
            }
            //套餐是否上架销售 或是否被删除 或者是否支持自动续费
            $set_meal_info = SetMeal::where('product_id', $v['meal_key'])->where('del_flag', 0)->where('sell_status', 1)->where('is_autopay',1 )->first();
            if(empty($set_meal_info)){
                continue;
            }
            //该账户是否离职或删除
            if($v['account_type'] == 1){ #普通工号
               $worker_info = Worker::where('id6d',$v['id6d'])->where('company_id',$v['id6d'])->where('state','0')->first();
                if(empty($worker_info)){ #工号离职或删除
                    continue;
                }
            }
            $arr[]=$v;
        }
        return $arr;
    }

}
