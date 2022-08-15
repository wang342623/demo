<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Lib\aqptApi;
use App\Models\Analysis\CertCost;
use Log;

class CertCostTask extends Command
{
    use StatsDayTrait;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'CertCost {action?} {start?} {end?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '统计认证成本';

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
     * 执行一天的数据
     *
     * @param string $date
     * @return void
     */
    private function handleOneDay(string $date)
    {
        $list = successRepeatCall([(new aqptApi), 'certIntfaceNums'], 10, 10, $date . ' 00:00:00', $date . ' 23:59:59');
        Log::info("certIntfaceNums返回" . var_export($list, true));
        // var_dump($list);
        $key = [
            'business_auth_num' => 'business_auth',
            'weChat_person_to_baidu_num' => 'weChat_person_to_baidu',
            'company_ring_auth_num' => 'company_ring_auth',
            'alipay_person_auth_num' => 'alipay_person_auth',
            'person_ring_auth_num' => 'person_ring_auth',
            'business_get_verify_code_num' => 'business_get_verify_code',
            'weChat_person_auth_num' => 'weChat_person_auth',
        ];
        foreach ($list as $field => $num) {
            $type = $key[$field] ?? null;
            if (empty($type)) { //未知类型
                Log::error("CertCostTask遇到未知类型{$field}");
                continue;
            }
            //类型加日期是唯一的
            $cost = CertCost::firstOrNew([
                'type' => $type,
                'use_date' => $date,
            ]);
            $cost->num = $num;
            $cost->save();
        }
    }
}
