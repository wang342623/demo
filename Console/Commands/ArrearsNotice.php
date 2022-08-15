<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Lib\eiisysApi;
use App\Lib\RenewApi;
use App\Models\Worker;
use Log;

class ArrearsNotice extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ArrearsNotice';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '月底续费未完成语音通知';

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
        Log::setDefaultDriver('notice');

        $RenewApi = new RenewApi;
        $facilitator_id = config('custom.facilitator_id');
        $month = date('Y-m', strtotime('-1 month'));
        // var_dump($month);
        //获取上个月末到期的自动续费开着的公司ID，即上个月末未续费成功的客户
        $company_ids = successJudge([$RenewApi, 'monthCompanyIds'], 3, 'boolval', $facilitator_id, $month);
        if (empty($company_ids)) {
            echolog("获取续费的公司ID失败 [{$facilitator_id}:{$month}]");
            return;
        }
        // array_push($company_ids, ...$company_ids);//数量不够测试用
        echolog('总通知数' . count($company_ids));
        $eiisysApi = new eiisysApi;
        $phone_data = [];
        foreach ($company_ids as $company_id) {
            $Worker = Worker::where('company_id', $company_id)->where('account_type', '1')->first();
            if (empty($Worker) || empty($mobile = decryptAesCBC($Worker->mobile))) {
                continue;
            }
            $phone_data[] = [
                'phone' => $mobile,
                'guest_name' => decryptAesCBC($Worker->master_account)
            ];
        }
        if (empty($phone_data)) {
            echolog("phone_data 为空");
            return 0;
        }
        $date = date('Y-m-d');
        $number_arr = config('custom.number');
        $phone_data = array_chunk($phone_data, 15); //每个外呼号码每天打15个电话
        $i = 0;
        $flag = true;
        do {
            foreach ($number_arr as $number) {
                if (!isset($phone_data[$i])) { //已经全都分配完了
                    $flag = false; //退出整个循环
                    break;
                }
                if (isDeve()) { //测试环境防止发生费用
                    continue;
                }
                $r = successJudge(
                    [$eiisysApi, 'voiceNotice'],
                    3,
                    'boolval',
                    $phone_data[$i++],
                    '尊敬的客户您好，您的快服账号{{guest_name}}有待续费订单,请及时充值或联系客户经理处理,谢谢。',
                    1,
                    $number,
                    $date,
                    $date
                );
                echolog("语音通知结果 -> " . var_export($r, true));
                if (!boolval($r)) {
                    echolog("语音通知出错 响应内容 -> " . var_export($eiisysApi->getLastRes(), true));
                }
            }
        } while ($flag && $date = date("Y-m-d", strtotime("{$date} +1 days")));
        return 0;
    }
}
