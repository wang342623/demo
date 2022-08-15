<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Worker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Log;

class IniAccount extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'initAccount';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '账号初始化';

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
        //查询所有公司信息
        Log::setDefaultDriver('gh');
        //        $company=Company::get()->toArray();
        $company = DB::table('cloud_company')->orderBy('id')->chunk('1000', function ($users) {
            $users = json_decode(json_encode($users), true);
            foreach ($users as $k => $v) {
                //解密在加密判断
                $de_account = decryptAesCBC($v['master_account']);

                if (!preg_match('/^([a-zA-Z0-9_\.\-])+\@(([a-zA-Z0-9\-])+\.)+([a-zA-Z0-9]{2,4})+$/', $de_account)) {
                    Log::info('错误账号信息======' . var_export($v['company_id'] . '---' . $v['master_account'] . '==========' . $de_account, true));
                    continue;
                }
                $new_account = encryptAesCBC($de_account);
                if ($new_account != $v['master_account']) {
                    //                if(preg_match('/^([a-zA-Z0-9_\.\-])+\@(([a-zA-Z0-9\-])+\.)+([a-zA-Z0-9]{2,4})+$/',$v['master_account'])){
                    Log::info('账号信息======' . var_export($v['company_id'] . '---' . $v['master_account'], true));
                    Company::where('company_id', $v['company_id'])
                        ->update(['master_account' => $new_account, 'sub_account' => $new_account]);
                    //初始化工号
                    $this->iniWorker($v['company_id']);
                }
            }
        });


        echo 'end';
    }


    public function iniWorker($company_id)
    {
        try {

            $worker = DB::table('cloud_worker')->where('company_id', $company_id)->get();
            $worker = json_decode(json_encode($worker), true);
            foreach ($worker as $k => $v) {
                $de_account = decryptAesCBC($v['master_account']);
                if (!preg_match('/^([a-zA-Z0-9_\.\-])+\@(([a-zA-Z0-9\-])+\.)+([a-zA-Z0-9]{2,4})+$/', $de_account)) {
                    continue;
                }
                $new_account = encryptAesCBC($de_account);
                if ($new_account != $v['master_account']) {
                    Worker::where('id', $v['id'])->update(['master_account' => $new_account]);
                }
                $de_sub_account = decryptAesCBC($v['sub_account']);
                if (!preg_match('/^([a-zA-Z0-9_\.\-])+\@(([a-zA-Z0-9\-])+\.)+([a-zA-Z0-9]{2,4})+$/', $de_sub_account)) {
                    continue;
                }
                $new_sub_account = encryptAesCBC($de_sub_account);
                if ($new_sub_account != $v['sub_account']) {
                    //                if(preg_match('/^([a-zA-Z0-9_\.\-])+\@(([a-zA-Z0-9\-])+\.)+([a-zA-Z0-9]{2,4})+$/',$v['sub_account'])){
                    Worker::where('id', $v['id'])->update(['sub_account' => $new_sub_account]);
                }
            }
        } catch (\Exception $e) {
            echo $e->getMessage();
        }
    }
}
