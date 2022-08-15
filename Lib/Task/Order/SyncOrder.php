<?php

namespace App\Lib\Task\Order;

use App\Lib\Task\Task;
use App\Models\Company;
use App\Models\CompanyBalance;
use App\Models\InnerUserBill;
use App\Models\InnerUserBuAdvance;
use App\Models\NewOrder;
use App\Models\NewOrderAttach;
use App\Models\SetMeal;
use App\Models\UserDistribute;
use App\Models\WithdrawToUnWithdraw;
use App\Models\Worker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Log;

class SyncOrder
{
    use Task;

    protected $cmds = ['order_service']; //只接收订单数据
    protected $new_order_id = ''; //生成的new_order_id
    protected $bill_num = ''; //生成的bill_n
    protected $real_pay_price = ''; //支付金额详情
    protected $balanceTransferId = '';
    protected $couponsTransferId = '';

    public function handleMsg($message)
    {
        Log::info('======order_service' . var_export($message, true));
        if (empty($message) || $message['cmd'] != 'order_service') {
            return Log::error('cmd is error');
        }
        $data_info = $message['info'];
        foreach ($data_info as $k => $v) {
            //运行type类型的sql insert or update
            //表名为 tableName
            try {
                if (isset($v['type']) && !empty($v['type']) && isset($v['tableName']) && !empty($v['tableName'])) {
                    switch ($v['type']) {
                        case 'insert':
                            $this->insertSQ($v);
                            break;
                        case 'update':
                            $this->updateSQ($v);
                            break;
                    }
                }
            } catch (\Exception $e) {
                return Log::error('add_sql====' . $e->getMessage());
            }
            if (isset($v['cmd']) && !empty($v['cmd'])) {
//                  特殊处理
                try {
                    $this->handleCMD($v);
                }catch (\Throwable $th) {
                    echolog('handleCMD===='.$th->getFile() . "-->line:" . $th->getLine() . "-->message:" . $th->getMessage(), 'error');
                }
            }
        }
        return true;
    }

    /***
     * 大写转小写加下划线
     * @param $str
     * @return string
     */
    function capital_to_underline($str): string
    {
        $temp_array = array();
        for ($i = 0; $i < strlen($str); $i++) {
            $ascii_code = ord($str[$i]);
            if ($ascii_code >= 65 && $ascii_code <= 90) {
                if ($i == 0) {
                    $temp_array[] = chr($ascii_code + 32);
                } else {
                    $temp_array[] = '_' . chr($ascii_code + 32);
                }
            } else {
                $temp_array[] = $str[$i];
            }
        }
        return implode('', $temp_array);
    }

    /***
     * 生成 new_order_id
     * @param $data
     * @param $pay_price
     * @param $member_grade
     * @param $no_discount_price
     * @param $wid
     * @param int $is_new_coupons
     * @return false|mixed
     */
    public function addOrderRecord($data, $pay_price, $member_grade, $no_discount_price, $wid, $is_new_coupons = 2)
    {
        if ($is_new_coupons == 2) { #老优惠券
            $order_mc = $pay_price['pay_coupon'] + $pay_price['pay_money'];
        } else {
            $order_mc = $pay_price['pay_money'];
        }
        //查询company
        $company = Company::where('company_id', $data['company_id'])->first();
        //查询代扣公司信息
        $pay_company = Company::where('company_id', $data['pay_company_id'])->first();
        //查询工号信息
        $worker_info = Worker::where('company_id',$data['company_id'])->where('id6d',$data['id6d'])->first();
        //查询客服经理id
        $inner_info = UserDistribute::select('inner_user_id')->where('cloud_company_id', $company->id)->first();
        $new_order_obj = new NewOrder();
        $new_order_obj->saas_order_id = $data['saas_order_id'];
        $new_order_obj->pro_other = $data['pro_other'] ?? ''; #备注外部传入
        $new_order_obj->order_time = $data['order_time'];
        $new_order_obj->order_from = isset($data['order_from']) ?? '1';
        $new_order_obj->cloud_company_id = $company->id;
        $new_order_obj->workers_id = $wid;
        $new_order_obj->company_id = $company->company_id;
        $new_order_obj->id6d = $data['id6d'];
        $new_order_obj->master_account = encryptAesCBC($worker_info->master_account);
        $new_order_obj->sub_account = encryptAesCBC($worker_info->sub_account);
        $new_order_obj->daikou_cloud_company_id = $pay_company->id;
        $new_order_obj->daikou_company_id = $pay_company->company_id;
        $new_order_obj->cat_id = $data['cat_id'];
        $new_order_obj->pro_id = $data['pro_id'];
        $new_order_obj->pro_unit = $data['pro_unit'];
        $new_order_obj->pro_num = $data['pro_num'];
        $new_order_obj->order_mc = $order_mc;
        $new_order_obj->pay_money = $pay_price['pay_money'];
        $new_order_obj->pay_coupons = $pay_price['pay_coupon'];
        $new_order_obj->no_discount_mc = $no_discount_price ? $no_discount_price : 0;
        $new_order_obj->pay_member_grade = $member_grade;
        $new_order_obj->pay_product_price = $data['pay_product_price'];
        $new_order_obj->pay_time = $data['real_order_time'];
        $new_order_obj->is_new_coupons = $is_new_coupons;
        $new_order_obj->mixed_count = $data['mixed_count'];
        $new_order_obj->inner_user_id = isset($inner_info->inner_user_id) ?? '';
        $new_order_obj->is_renew = isset($data['is_renew'])?$data['is_renew']:0;

        //查询成本
        $product_info = SetMeal::find($data['pro_id']);
        $extend_info = $product_info->extend;
        Log::info('extend_info===='.var_export($extend_info,true));
        $new_order_obj->cost = $extend_info->cost??''; #'成本'
        $new_order_obj->cost_type = $extend_info->cost_type??''; #'生产成本类型 1固定金额，2销售占比 0不计算',
        $new_order_obj->inner_ticheng_type = $product_info->inner_ticheng_type??'';#'客户经理提成计算类型：1正常计算提成，2按月合并计算提成，3不计算提成',
        $new_order_obj->inner_rate = $product_info->inner_rate??'';#'客户经理提成金额',
        $new_order_obj->supplier_type = $extend_info->supplier_type??'';#'供应商结算方式 1固定金额，2销售金额， 0不计算提成
        $new_order_obj->supplier_price = $extend_info->supplier_price??'';#'供应商提成金额',
        $new_order_obj->proxy_ticheng_type = $product_info->proxy_ticheng_type??'';#'代理商提成计算类型：1正常计算提成,3不计算提成',
        $new_order_obj->proxy_rate = $product_info->proxy_rate??'';#'代理商提成金额',
        $new_order_obj->sale_settlement = $extend_info->sale_settlement??'';#'渠道商结算方式 ：1固定金额，2销售金额， 0不计算提成',
        $new_order_obj->sale_proportion = $extend_info->sale_proportion??'';#'渠道商',
        $new_order_obj->save();

        Log::info('new_order_id====' . var_export($new_order_obj->id, true));
        $new_order_id = $new_order_obj->id;
        if ($new_order_id !== false) {
            $save_order_obj = NewOrder::find($new_order_id);
            $save_order_obj->order_num = sprintf('%010d', $new_order_id);
            $save_order_obj->save();
        }
        $product_extend = SetMeal::select(DB::raw("CASE e.cost_type
                            WHEN 1 THEN e.cost/p.price
                            WHEN 2 THEN e.cost/100
                            ELSE '0'
                        END as scale"))->from('cloud_product as p')
            ->join('cloud_product_extend as e', 'p.product_id', '=', 'e.product_id')
            ->where('p.id', $data['pro_id'])
            ->first();

        if (!empty($product_extend) && $product_extend->scale > 0) {
            if ($is_new_coupons == 2) {
                $cost = ($pay_price['pay_coupon'] + $pay_price['pay_money']) * $product_extend->scale;
            } else {
                $cost = $pay_price['pay_money'] * $product_extend->scale;
            }
            $data = array(
                'order_id' => $new_order_id,
                'order_cost' => $cost,
            );
            DB::table('cloud_order_other_msg')->insert($data);
        }
        Log::info('return new_order_id====' . var_export($new_order_id, true));
        return $new_order_id;
    }

    public function ticheng($company_id, $pro_key, $is_free, $real_order_time, $real_pay_price, $id6d)
    {
        //判断是否产生提成 true为可以产生提成
        $is_commision = true;
        if (in_array($company_id, config('same.no_commision_company')) && in_array($pro_key, config('same.no_commision_product'))) {
            $is_commision = false;
        }
        if ($is_free && !$is_commision) {
            return false;
        }
        $company_info = Company::where('company_id', $company_id)->first();
        $account_info = CompanyBalance::where('cloud_company_id', $company_info->id)->first();
        $worker_info = Worker::where('id6d', $id6d)->first();
        $product_info = SetMeal::findKey($pro_key);
        //先查询该账号下客户经理是否有预支未补清，如果未补清提成记入预支补扣详情表，已经补清则正常发放提成
        //提成
        $ticheng = $real_pay_price['pay_money'] * $product_info->inner_rate;
        $u_info = DB::table('cloud_withdraw_to_unwithdraw')->where('cloud_company_id', $company_info->id)->where('is_fill_up', 2)->first();
        $u_info = json_decode(json_encode($u_info),true);

        if (!empty($u_info) && $real_pay_price['arrears_status'] == 2 && $ticheng > 0) {   #查到值，则未补清，并且不是欠费产生的提成，提成补扣记入cloud_inner_user_bu_advance表 ,arrears_status=2表示不是欠费产生的提成
            $inner_user_info = DB::table('cloud_user_distribute')->select('b.id', 'b.user_name', 'b.nick_name')
                ->from('cloud_user_distribute as a')
                ->leftjoin('cloud_inner_user as b', 'a.inner_user_id', '=', 'b.id')
                ->where('cloud_company_id', $company_info->id)
                ->first();

            if ($u_info['reissue_money'] > $ticheng) {    #未补清
                $reissue_money = $u_info['reissue_money'] - $ticheng;
                $bukou_ticheng = $ticheng;
                $updata = array(
                    'reissue_money' => $reissue_money,
                );
                $advance_remark = '客服经理之前有预支该客户的提成，这次产生的消化提成' . $ticheng . '元用于补扣预支金额,补扣后还欠预支金额为-' . $reissue_money . '元';
            } else {                                    #已补清
                $reissue_money = 0;
                $updata = array(
                    'reissue_money' => 0,
                    'is_fill_up' => 1,
                );
                $add_ticheng = $ticheng - $u_info['reissue_money'];
                $bukou_ticheng = $u_info['reissue_money'];
                if ($add_ticheng > 0) {        #补清提成后,多余的提成需要发给相应的客户经理

                    if ($company_info['yx_from'] > 0) {
                        $user_type = 2;  #非直属用户
                    } else {
                        $user_type = 1;  #直属用户
                    }
                    $inner_user_data_remark = '客户消费' . ($real_pay_price['pay_coupon'] + $real_pay_price['pay_money']) . '元(优惠券抵扣' . $real_pay_price['pay_coupon'] . '元),产生提成' . $ticheng . '元,其中' . $bukou_ticheng . '元用于补扣预支提成';

                    $inner_user_bill = new InnerUserBill();
                    $inner_user_bill->inner_user_id = $inner_user_info->id ?? '';
                    $inner_user_bill->user_name = $inner_user_info->user_name ?? '';
                    $inner_user_bill->nick_name = $inner_user_info->nick_name ?? '';
                    $inner_user_bill->money = $add_ticheng;
                    $inner_user_bill->bill_type = 0;
                    $inner_user_bill->bill_date = date('Y-m-d H:i:s');
                    $inner_user_bill->user_type = $user_type;
                    $inner_user_bill->ticheng_type = 1;  //1消化,2开户,3可提现转不可提现预支已补清，4可提现转不可提现预支未补清'
                    $inner_user_bill->sub_account = $worker_info->sub_account;
                    $inner_user_bill->workers_id = $worker_info->id;
                    $inner_user_bill->cloud_company_id = $company_info->id;
                    $inner_user_bill->xiaohua_money = $real_pay_price['pay_coupon'] + $real_pay_price['pay_money'];
                    $inner_user_bill->xiaohua_fund = $real_pay_price['pay_money'];
                    $inner_user_bill->remark = $inner_user_data_remark;
                    $inner_user_bill->is_jiru_ticheng = 1;  #记入提成
                    $inner_user_bill->is_arrearage = $real_pay_price['arrears_status'];        #不是欠费产生的
                    $inner_user_bill->order_num = $this->new_order_id;
                    $inner_user_bill->is_fill_ticheng = 2; #2不用补提成
                    $inner_user_bill->arrive_time = date("Y-m-d H:i:s");
                    $inner_user_bill->cat_id = $product_info->cag_id;
                    $inner_user_bill->pro_id = $pro_key;
                    $inner_user_bill->cat_type = 1; //产品类别所属类型:1 内部 2第三方产品
                    $inner_user_bill->new_type = 2; //'开通支付类型：1旧版，2新版'
                    $inner_user_bill->save();
                }

                $advance_remark = '客服经理之前有预支该客户的提成，这次产生的消化提成' . $ticheng . '元,其中' . $bukou_ticheng . '元用于补扣预支金额,补扣后预支金额已补清';

            }

            //更新cloud_withdraw_to_unwithdraw表数据(补清有两个字段，在cloud_withdraw_to_unwithdraw表和cloud_inner_user_bill表中,更改时都要更改)
            $insert_un = WithdrawToUnWithdraw::find($u_info['id']);
            foreach ($updata as $k => $v) {
                $insert_un->$k = $v;
            }
            $insert_un->save();

            //记入补扣明细表cloud_inner_user_bu_advance中
            $inner_user_bu_advance = new InnerUserBuAdvance();
            $inner_user_bu_advance->cloud_company_id = $company_info->id;
            $inner_user_bu_advance->inner_user_id = $inner_user_info->id ?? '';
            $inner_user_bu_advance->user_name = $inner_user_info->user_name ?? '';
            $inner_user_bu_advance->nick_name = $inner_user_info->nick_name ?? '';
            $inner_user_bu_advance->huafei_all_money = $real_pay_price['pay_coupon'] + $real_pay_price['pay_money'];
            $inner_user_bu_advance->xiaohua_money = $real_pay_price['pay_money'];
            $inner_user_bu_advance->ticheng_money = $bukou_ticheng;
            $inner_user_bu_advance->is_arrearage = $real_pay_price['arrears_status'];
            $inner_user_bu_advance->order_num = $this->new_order_id;
            $inner_user_bu_advance->category_id = $product_info->category_id;
            $inner_user_bu_advance->product_id = $pro_key;
            $inner_user_bu_advance->product_name = $product_info->product_name;
            $inner_user_bu_advance->remark = $advance_remark;
            $inner_user_bu_advance->detail_time = date("Y-m-d H:i:s");
            $inner_user_bu_advance->save();

        } else {       #查不到值，则已经补清,正常发放提成

            $userAuthData = DB::table('cloud_authentication')->where('cloud_company_id', $company_info->id)->first();

            $is_new_auth = DB::table('cloud_organzation_attestation')->where('cloud_company_id', $company_info->id)->first();
            $is_auth = 2;
            if ((!empty($userAuthData) && $userAuthData->audit_status_combine == 1) || !empty($is_new_auth)) {
                $is_auth = 1;
            }
            $inner_user_info = DB::table('cloud_user_distribute')->select('b.id', 'b.user_name', 'b.nick_name')
                ->from('cloud_user_distribute as a')
                ->leftjoin('cloud_inner_user as b', 'a.inner_user_id', '=', 'b.id')
                ->where('cloud_company_id', $company_info->id)
                ->first();
            $owing_money = $real_pay_price['arrears_status'] == 1 ? $account_info->balance - $real_pay_price['pay_money'] : 0;
            $remark = '开通功能' . $product_info->product_name;
            $new_order_attach = new  NewOrderAttach();
            $new_order_attach->new_order_id = $this->new_order_id;
            $new_order_attach->proxy = $company_info->proxy;//开通的账号
            $new_order_attach->arrears_status = $real_pay_price['arrears_status'];
            $new_order_attach->is_auth = $is_auth;
            $new_order_attach->inner_user_id = $inner_user_info->id ?? '';
            $new_order_attach->user_name = $inner_user_info->user_name ?? '';//开通的账号
            $new_order_attach->nick_name = $inner_user_info->nick_name ?? '';//开通的账号
            $new_order_attach->owing_money = $owing_money;
            $new_order_attach->proxy_rate = $product_info->proxy_rate;
            $new_order_attach->inner_rate = $product_info->inner_rate;
            $new_order_attach->proxy_ticheng_type = $product_info->proxy_ticheng_type;
            $new_order_attach->inner_ticheng_type = $product_info->inner_ticheng_type;
            $new_order_attach->status = 0;
            $new_order_attach->remark = $remark;
            $new_order_attach->member_discount = $real_pay_price['member_discount'];
            $new_order_attach->operate_time = $real_order_time;
            $new_order_attach->save();
        }
    }

    //新增
    public function insertSQ($v)
    {
        $data = json_decode($v['info'], true);
        $new_data = [];
        //键为大写这里特殊处理进行转小写加下划线处理
        foreach ($data as $kk => $vv) {
            if ($vv === '$new_order_id') {
                $new_data[$this->capital_to_underline($kk)] = $this->new_order_id;
            }else if($vv === '$bill_num'){
                $new_data[$this->capital_to_underline($kk)] = $this->bill_num;
            } else {
                $new_data[$this->capital_to_underline($kk)] = $vv;
            }
        }

        //根据对应表名进行新增并获取新增id
        $insert_id = DB::table($v['tableName'])->insertGetId($new_data);
        Log::info('insert_init=====' . var_export(['table_name' => $v['tableName'], 'insert_id' => $insert_id,'new_data'=>$new_data], true));
        //特殊表特殊处理
        $this->setTransferId($v['tableName'], $data, $insert_id);

    }

    //更新
    public function updateSQ($v)
    {
        $data = json_decode($v['info'], true);
        //更新主键判断处理
        if ($data['id'] == 'cloudTransferRecord_couponsTransferId') {
            $id = $this->couponsTransferId;
        } else if ($data['id'] == 'cloudTransferRecord_balanceTransferId') {
            $id = $this->balanceTransferId;
        } else {
            $id = $data['id'];
        }
        //更新参数处理
        $new_data = [];
        foreach ($data as $ks => $vs) {
            if ($vs === '$new_order_id') {
                $new_data[$this->capital_to_underline($ks)] = $this->new_order_id;
            } else {
                $new_data[$this->capital_to_underline($ks)] = $vs;
            }
        }
        unset($new_data['id']);
        DB::table($v['tableName'])->where('id', $id)->update($new_data);
        Log::info('update_init======' . var_export(['table_name' => $v['tableName'], 'update_id' => $id], true));
    }

    //这两个表的id需要存起
    public function setTransferId($tableName, $data, $id)
    {
        if ($tableName === 'cloud_transfer_record') {
            if ($data['transferType'] == 1) {
                $this->balanceTransferId = $id;
            } else if ($data['transferType'] == 2) {
                $this->couponsTransferId = $id;
            }
        }
    }

    //cmd处理
    public function handleCMD($data)
    {
//        $data = json_decode($data, true);
        $info = json_decode($data['info'], true);
        //new_order 新增
        if ($data['cmd'] == 'insertNewOrder') {
            $this->real_pay_price = $info['real_pay_price'];
            //生成 new_order_id
            $this->new_order_id = $this->addOrderRecord($info['data'], $info['real_pay_price'], $info['init_member_grade'], $info['yuan_money'], $info['wid'], $info['is_new_coupons']);
            Log::info('echo new_order_Id' . var_export($this->new_order_id, true));
        }
        //bill表计算提成
        if ($data['cmd'] == 'insertInnerUserBill') {
            $this->ticheng($info['company_id'], $info['pro_key'], $info['is_free'], $info['real_order_time'], $this->real_pay_price, $info['id6d']);
        }
        //bill表更新
        if ($data['cmd'] == 'insertCloudBill') {
            $new_data = [];
            //键为大写这里特殊处理进行转小写加下划线处理
            foreach ($info as $k => $v) {
                if ($v === '$new_order_id') {
                    $new_data[$this->capital_to_underline($k)] = $this->new_order_id;
                } else {
                    $new_data[$this->capital_to_underline($k)] = $v;
                }
            }
            //根据对应表名进行新增并获取新增id
            $insert_id = DB::table('cloud_bill')->insertGetId($new_data);
            $this->bill_num=$insert_id;
            Log::info('insert_init=====' . var_export(['table_name' => 'cloud_bill', 'insert_id' => $insert_id,'new_data'=>$new_data], true));
        }

    }

}
