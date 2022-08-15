<?php

namespace App\Lib\Task;
//开户处理
use App\Lib\RedisOperation;
use Illuminate\Support\Facades\Log;

class KFOpenAccount
{
    use \App\Lib\Task\Task;

    private $cmds = ['KF_openAccount'];

    public function KF_openAccount($data)
    {
        Log::info('open account datas===='.var_export($data,true));
        if(empty($data['company_id'])){
            return false;
        }

        $redis_open= new RedisOperation(app('redis'));
        $redis_open->syncCom($data['company_id'],['open_account_time'=>empty($data['date_time'])?date('Y-m-d H:i:s'):$data['date_time']]);
        return true;
    }
}
