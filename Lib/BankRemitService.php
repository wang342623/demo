<?php

namespace App\Lib;

use App\Models\BankRemit;
use App\Models\BindCard;
use Illuminate\Support\Facades\Log;

/**
 * Class BankRemitService
 * @package App\Lib
 * 银行线下转账 自动到账处理类
 */
class BankRemitService
{
	//线下汇款充值方法
	//参数按照cloud_bank_remit表的字段传入
	public function capitalRecharge($data = [])
	{
		Log::setDefaultDriver('gh');
		Log::info('获取数据======' . var_export($data, true));
		//参数处理
		try {
			if (empty($data['bank_type'])) {
				return ['code' => '403', 'msg' => '银行信息不得为空'];
			}
			$payeracctno = $data['payeracctno']; //对方银行卡号
			$bank_data = [
				'transno'           => isset($data['transno']) ? $data['transno'] : '',  //'交易流水号',
				'transtime'         => isset($data['transtime']) ? $data['transtime'] : '',  //'原交易时间（Ymd）',
				'transamount'       => isset($data['transamount']) ? $data['transamount'] : '',  //'交易金额',
				'payeracctno'       => $data['payeracctno'],  //'对方账户',
				'payeracctname'     => isset($data['payeracctname']) ? $data['payeracctname'] : '',  //'对方户名',
				'payeeacctno'       => isset($data['payeeacctno']) ? $data['payeeacctno'] : '',  //'合作企业结算账号',
				'payeeacctname'     => isset($data['payeeacctname']) ? $data['payeeacctname'] : '',  //'合作企业结算账户名',
				'abstractinfo'      => isset($data['abstractinfo']) ? $data['abstractinfo'] : '',  //'摘要',
				'oppositebankno'    => isset($data['oppositebankno']) ? $data['oppositebankno'] : '',  //'对方行号',
				'oppositebankname'  => isset($data['oppositebankname']) ? $data['oppositebankname'] : '',  //'对方行名',
				'currentbalance'    => isset($data['currentbalance']) ? $data['currentbalance'] : '',  //'当前余额',
				'proofno'           => isset($data['proofno']) ? $data['proofno'] : '',  //'凭证号',
				'transcode'         => isset($data['transcode']) ? $data['transcode'] : '',  //'交易代码',
				'tellerno'          => isset($data['tellerno']) ? $data['tellerno'] : '',  //'柜员号',
				'currtype'          => isset($data['currtype']) ? $data['currtype'] : '',  //'币种',
				'bank_type'         => $data['bank_type'], //'银行信息',
				'add_time'          => date('Y-m-d H:i:s'),  //'添加时间',
				'update_time'       => '', //'入账时间',
				'is_info'           => 2,  //'是否入账 1 是 2 否',
				'cloud_company_id'  => '',  //'入账账号',
				'remark'            => '', //'备注',
			];
		} catch (\Exception $e) {
			Log::error('[error]====' . var_export($e->getMessage(), true));
			return ['code' => '402', 'msg' => '参数错误'];
		}

		Log::info('获取数据======' . var_export($data, true));
		//判断是否处理过
		$bank_info = BankRemit::where('tellerno', $bank_data['tellerno'])->where('bank_type', $bank_data['bank_type'])->first();
		if ($bank_info) {
			Log::info('该条以处理====' . var_export($bank_data['tellerno'], true));
			return ['code' => '406', 'msg' => '该条以处理'];
		}
		//判断银行卡是否绑定账号 查询cloud_bind_card表
		$bind_card = BindCard::where('bank_card_no', $payeracctno)->first();
		if ($bind_card) {
			//存在绑定账号直接入账
			//入账成功写入记录表
			$account = 'zc@163.com';
			$bank_data['is_info'] = 1; //入账
			$bank_data['update_time'] = date('Y-m-d H:i:s');
			$bank_data['cloud_company_id'] = '';
			$bank_data['remark']  = '工商银行线下汇款自动入账到' . $account . '公司账号';
		}

		//写入记录表
		try {

			Log::info('写入数据======' . var_export($bank_data, true));
			$rt = BankRemit::create($bank_data);
			if ($rt) {
				return ['code' => '200', 'msg' => 'success'];
			}
			return ['code' => '503', 'msg' => '数据可能已存在或新增失败'];
		} catch (\Exception $e) {
			Log::error('[error]=====' . var_export($e->getMessage(), true));
			return ['code' => '502', 'msg' => $e->getMessage()];
		}
	}
}
