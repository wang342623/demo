<?php

namespace App\Console\Commands;

use App\Models\Worker;
use App\Models\WxRelation;
use App\Models\WxUser;
use Illuminate\Console\Command;
use WxApi;

class WxInit extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Wxinit';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '微信公众号用户初始化';

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
        //查询所有存在wxid的工号账户

        Worker::whereNotNull('wxid')->where('wxid', '!=', '')->where('wxid', '!=', 'NULL')->where('wxid', '!=', 'null')->chunk('1000', function ($users) {
            $users = json_decode(json_encode($users), true);

            foreach ($users as $k => $v) {
                $re = WxApi::getUnionID($v['wxid']);
                if (!empty($re)) {
                    //查询是否存在uid
                    $wx_user_info = WxUser::updateOrCreate(
                        ['u_id' => $re],
                        ['wx_id' => $v['wxid'], 'u_id' => $re]
                    );
                    if ($wx_user_info) {
                        WxRelation::updateOrCreate(
                            ['wx_user_id' => $wx_user_info->id, 'worker_id' => $v['id']],
                            ['wx_user_id' => $wx_user_info->id, 'worker_id' => $v['id']]
                        );
                    }
                }
            }
        });
    }
}
