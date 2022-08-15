<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Lib\RenewApi;
use Log;
use kfRedis;
use App\Models\Company;
use App\Models\Worker;
use App\Lib\eiisysApi;
use WxApi;

class AdvanceNotice extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'AdvanceNotice {--f|force}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '月底续费前7天预通知(短信、微信)客户及时充值';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->sendNum = 0;
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
        $company_ids = successJudge([$RenewApi, 'monthCompanyIds'], 3, 'boolval', $facilitator_id);
        if (empty($company_ids)) {
            echolog('获取续费的公司ID失败');
            return;
        }
        foreach ($company_ids as $company_id) {
            try {
                $this->handleCompany($company_id);
            } catch (\Throwable $th) {
                echolog("[ {$company_id} ] 处理时发生报错[ " . $th->getMessage() . " ]");
                continue;
            }
        }
        $count = count($company_ids);
        echolog("共计发送[ {$this->sendNum} ] 次,处理{$count}个公司");
        return 0;
    }
    //处理单个公司ID
    private function handleCompany(int $company_id)
    {
        $num = kfRedis::noticeNum($company_id);
        if ($num > 1 && !$this->option('force')) {
            return;
        }
        $company = Company::findById($company_id);
        if (empty($company)) {
            echolog("[ {$company_id} ]找不到公司信息");
            return;
        }
        $balance = $company->balance;
        if (empty($balance)) { //获取余额失败
            echolog("[ $company_id ] 获取余额失败");
            return;
        }
        $balance = floatval($balance->balance);
        //月底续费消费金额
        $order_price = $company->sumConsumMoney(date('Y-m-01 00:00:00'), date('Y-m-01 00:00:00'));
        if ($order_price <= 0) {
            return echolog("[ {$company_id} ] 消费金额为0");
        }
        //余额减去订单金额
        $diff = $balance - $order_price;
        if ($diff > 0) { //不需要通知
            echolog("[ {$company_id} ]-[ {$diff} ] 不需要通知");
            return;
        }
        echolog("[ {$company_id} ]-[ {$diff} ] 开始通知");
        $main_account = $company->master_account;
        var_dump($main_account);
        $worker_list = Worker::select('account_type', 'company_id', 'mobile', 'id6d', 'wxid')->where('company_id', $company_id)->get();
        if (empty($worker_list)) {
            return echolog("[ {$company_id} ] 获取工号失败", 'error');
        }
        $super_admin = null;
        $admin_list = []; //管理员工号列表
        foreach ($worker_list as $worker) {
            if ($worker->account_type == 1) { //超级管理员
                $super_admin = $worker;
                continue;
            }

            if (kfRedis::workInfo($company_id, $worker->id6d, 'isadmin') == '1') { //管理员
                $admin_list[] = $worker;
            }
        }
        if (!empty($super_admin)) { //通知超级管理员
            $super_res = $this->handleWork($super_admin, $order_price, $main_account, $balance);
        }

        //如果是第二次通知或者是超级管理员通知失败
        if ($num == 1 || !$super_res) { //通知所有管理员
            echolog("[ {$company_id} ] 通知所有管理员");
            foreach ($admin_list as $value) {
                $this->handleWork($value, $order_price, $main_account, $balance);
            }
        }
        $cur_num = $num + 1;
        echolog("[ {$company_id} ] < renew.notice.{{$company_id}} > 通知第{$cur_num}次");
        kfRedis::incNoticeNum($company_id, strtotime(date("Y-m-t 23:59:59"))); //增加通知次数，并设置redis键月底到期
    }
    /**
     * 通知工号
     *
     * @param Worker $worker 工号数据
     * @param float $order_price 上个月消费金额
     * @param string $main_account 主账号
     * @param float $balance 当前余额
     * @return boolean
     */
    private function handleWork(Worker &$worker, float $order_price, string $main_account, float $balance): bool
    {
        // return true;
        $id6d = $worker->id6d;
        $company_id = $worker->company_id;
        $mobile = $worker->mobile;

        if (empty($mobile)) {
            return false; //通知失败
        }
        if (strlen($mobile) > 11) { //加密数据
            $mobile = decryptAesCBC($mobile);
        }

        echolog("[ {$company_id} ]-[ {$id6d} ]-[ {$mobile} ] 通知工号");
        $this->sendNum++;
        // return true;
        $eiisysApi = new eiisysApi();
        $r = successJudge(
            [$eiisysApi, 'sendSMS'],
            3,
            'boolval',
            $mobile,
            config('custom.template_id'),
            [$order_price, $main_account, $balance]
        );
        echolog("[ {$company_id} ]-[ {$id6d} ]-[ {$mobile} ] 短信通知结果" . var_export($r, true));
        if (!$r) {
            return false;
        }
        //53kf公众号
        $r = successJudge([$eiisysApi, 'sendWx'], 3, 'boolval', $company_id, $id6d, $balance, $order_price, $main_account);
        echolog("[ {$company_id} ]-[ {$id6d} ]-[ {$mobile} ] 微信通知结果" . var_export($r, true));
        if (!empty($worker->wxid)) { //快服网公众号
            wxApi::renewReminder($worker->wxid, $main_account, $order_price, $balance);
        }
        return true; //只要短信发送成功就视为成功
    }
}
