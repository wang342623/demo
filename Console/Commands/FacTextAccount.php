<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Facilitator\Facilitator;
use Illuminate\Console\Command;

class FacTextAccount extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'FacT';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '服务商测试账号初始化';

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
        //查询服务商所有company_id (数量较少直接查询)

        $fa_info = Facilitator::select('company_id', 'facilitator_id')->get()->toArray();
        foreach ($fa_info as $k => $v) {
            //查询是否是测试账号
            $user_info = Company::from('cloud_company as c')->select('d.inner_user_id')
                ->leftjoin('cloud_user_distribute as d', 'd.cloud_company_id', '=', 'c.id')
                ->where('company_id', $v['company_id'])
                ->first();
            $user_info = empty($user_info) ? array() : $user_info->toArray();
            if (!empty($user_info['inner_user_id']) && $user_info['inner_user_id'] == 17) { //测试账号
                $fac_model = Facilitator::find($v['facilitator_id']);
                $fac_model->is_test_account = 1;
                $fac_model->save();
                echo $v['company_id'];
                echo "\n";
            }
        }
        return 0;
    }
}
