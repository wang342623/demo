<?php

namespace App\Console\Commands;

use App\Lib\RedisOperation;
use App\Models\Company;
use Illuminate\Console\Command;


class OpenAccountTime extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'openAccount';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
     *d8907656 6
     * @return int
     */
    public function handle()
    {
        //查询最近两个月内注册的主账号并将开户时间同步至com.info
        Company::select('company_id', 'open_account_time', 'member_grade')
            ->where('reg_date', '>=', '2021-01-01')->orderBy('id', 'asc')->chunk('1000', function ($users) {
                $users = json_decode(json_encode($users), true);
                //            如果该账户以开户则写入redis
                foreach ($users as $k => $v) {
                    if ($v['member_grade'] > 0 && !empty($v['open_account_time'])) {
                        $redis_open = new RedisOperation(app('redis'));
                        $redis_open->syncCom($v['company_id'], ['open_account_time' => $v['open_account_time']]);
                    }
                }
            });
    }
}
