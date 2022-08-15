<?php

namespace App\Console\Commands;

use App\Lib\BasicApi;
use App\Lib\BankRemitService;
use Illuminate\Console\Command;
use Log;

class Remit extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Remit';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '泰隆银行线下汇款到账脚本';

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
        Log::setDefaultDriver('remit');
        Log::info('执行');

        //地址
        $url = 'http://111.1.11.27:8078';
        //当天时间
        $startDate = date("Ymd", strtotime("-1 day"));
        $endDate = date("Ymd");
        $data = [
            "startDate" => $startDate,
            "endDate" => $endDate,
        ];

        $basic_api = new BasicApi($url);
        $rt = $basic_api->send('/accountauthentication/bankAuth/dpstAcctTranQry', $data);
        Log::info('查询结果：' . var_export($rt, true));
        if (!$rt) {
            Log::info('未查到信息');
            return true;
        }
        //模拟测试
        //        $rt[]=[
        //            'oriTranDate' => '20210923',
        //            'oriTxnTime' => '042338094',
        //            'abstracts' => 'IB9999',
        //            'drAmt' => '0.0',
        //            'crAmt' => '21284.49',
        //            'olAcctBal' => '33249.03',
        //            'oriTranSeqNo' =>'' ,
        //            'oriTellerSeqNo' => '37545819',
        //            'regionNo' => '',
        //            'acctName' => '财付通支付科技有限公司',
        //            'acctNo' => '243300133',
        //            'rvsFlag' =>'' ,
        //            'smmryMsg' => '财付通收款',
        //            'oriTranCode' => 'ibw023',
        //            'postscript' => '0923_1543181311',
        //            'ccy' => '156',
        //            'openBankCode' => '',
        //            'openBankName' => '',
        //        ];

        foreach ($rt as $k => $v) {
            $datas = json_decode(json_encode($v), true);
            //直接调用入账方法
            $capi_data = [
                'transno'           => $datas['oriTellerSeqNo'],
                'transtime'         => $datas['oriTxnTime'],  //'原交易时间（Ymd）',
                'transamount'       => $datas['crAmt'],  //'交易金额',
                'payeracctno'       => $datas['acctNo'],  //'对方账户',
                'payeracctname'     => $datas['acctName'],  //'对方户名',
                'payeeacctno'       => '',  //'合作企业结算账号',
                'payeeacctname'     => '',  //'合作企业结算账户名',
                'abstractinfo'      => $datas['abstracts'],  //'摘要',
                'oppositebankno'    => $datas['openBankCode'],  //'对方行号',
                'oppositebankname'  => $datas['openBankName'],  //'对方行名',
                'currentbalance'    => $datas['olAcctBal'],  //'当前余额',
                'proofno'           => '',  //'凭证号',
                'transcode'         => $datas['oriTranCode'],  //'交易代码',
                'tellerno'          => $datas['oriTellerSeqNo'],  //'柜员号',
                'currtype'          => $datas['ccy'],  //'币种',
                'bank_type'         => '泰隆银行', //'银行信息',
            ];
            $remit = new BankRemitService();
            $result = $remit->capitalRecharge($capi_data);
            Log::info('返回信息====' . var_export($result, true));
        }
    }
}
