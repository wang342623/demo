<?php

namespace App\Lib\Task;

use App\Models\AccountProduct;
use App\Models\Company;
use App\Models\SpecialAccount;
use App\Models\Worker;
use Illuminate\Support\Facades\Log;

/***
 * Class InventoryRecord
 * @package App\Lib\Task
 *
 * 库存记录
 */
class InventoryRecord
{
    use Task;
    public function open_push($data)
    {
        Log::info('[data info]:' . var_export($data, true));
        try {
            //参数获取
            $request = $data['order'];
            $save_data = $this->paramAuth($request);
            if (empty($save_data)) return false;
            $update_data['company_id'] = $save_data['company_id'];
            $update_data['meal_key'] = $save_data['meal_key'];
            if ($save_data['account_type'] == 1) {
                $update_data['id6d'] = $save_data['id6d'];
            } else {
                $update_data['special_account_id'] = $save_data['special_account_id'];
            }
            $account_product = AccountProduct::updateOrcreate(
                $update_data,
                $save_data
            );
            $save_id = $account_product->id;
            //新增或修改次数
            AccountProduct::find($save_id)->increment('open_number',ceil($save_data['order_amount'])??1);
            return true;
        } catch (\Exception $e) {
            return Log::error('[product error] : ' . var_export($e->getMessage(), true));
        }
    }

    //参数处理
    public function paramAuth($request)
    {
        Log::info('request=========' . var_export($request, true));
        $save_data = [];
        //判断是否是快服网账户 不是快服网账户跳出
        if ($request['facilitator_id'] != config('custom.facilitator_id')) {
            Log::info('facid jump');
            return $save_data;
        }
        $company_info = Company::where('company_id', $request['company_id'])->first();
        //公司不存在跳出
        if (empty($company_info)) {
            Log::info('company_id jump');
            return $save_data;
        }
        $save_data['company_id'] = $request['company_id'];
        //判断是否是普通账号 普通账户记录 id6d 特殊账户记录 特殊账户id
        if ($request['account_type'] == 'worker') {
            //普通账号查询worker表
            $worker_info = Worker::where('id6d', $request['id6d'])->where('company_id', $request['company_id'])->first();
            if (empty($worker_info)) {
                Log::info('id6d jump');
                return $save_data;
            }
            $save_data['id6d'] = $request['id6d'];
            $save_data['account_type'] = 1;
        } else {
            //查询特殊账号表是否存在
            $save_data['special_account_id'] = $request['dec_account_id'];
            $save_data['account_type'] = 2;
        }
        $save_data['auto_renew'] = $request['auto_renew']; #是否开启自动续费
        $save_data['product_key'] = $request['product_id_old']; #产品
        $save_data['meal_key'] = $request['meal_key']; #套餐id
        $save_data['product_unit'] = $request['product_unit']; #产品类型
        $save_data['order_amount'] = $request['order_amount']; #开通数量
        $save_data['expire_time'] = $request['now_expire_time'] ?? '';  #到期时间
        $save_data['start_time'] = $request['order_time']; #开始时间
        $save_data['on_trial'] = $request['on_trial'] ?? '1'; #是否是试用
        $save_data['mixed_count'] = $request['mixed_count'];
        Log::info('save_data=========' . var_export($save_data, true));
        return $save_data;
    }

}
