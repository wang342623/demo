<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Company;
use App\Models\Tag\CompanyTag;
use App\Lib\Tag\MemberHandle;
use App\Lib\Tag\BalanceHandle;
use App\Lib\Tag\MonthConsumHandle;
use App\Lib\Tag\RechargeHandle;
use App\Lib\Tag\ArrearsHandle;
use App\Lib\RabbitMq;

class AnalysisLabel extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'AnalysisLabel';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '统计分析客户标签';
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
        $max_id = 0;
        while (true) {
            $list = CompanyTag::where('id', '>', $max_id)->orderBy('id', 'asc')->limit(1000)->get();
            if (empty($list) || $list->isEmpty()) { //分析完成
                break;
            }
            foreach ($list as &$value) {
                $max_id = $value->id > $max_id ? $value->id : $max_id;
                $this->handleCompany($value);
            }
        }
        return 0;
    }
    //处理单个公司
    private function handleCompany(CompanyTag &$CompanyTag)
    {
        $company = Company::where('company_id', $CompanyTag->company_id)->first();
        if (empty($company)) {
            return;
        }
        echolog("开始给[{$CompanyTag->company_id}]标记标签");
        $class_arr = [
            MemberHandle::class, //会员等级标签
            BalanceHandle::class, //余额标签
            MonthConsumHandle::class, //月消化金额
            RechargeHandle::class, //充值次数
            ArrearsHandle::class, //欠费
        ];
        foreach ($class_arr as $class_name) {
            getSingleClass($class_name)->company($CompanyTag, $company);
        }
        $CompanyTag->save(); //执行过程不保存数据库,因此最后进行保存
    }
}
