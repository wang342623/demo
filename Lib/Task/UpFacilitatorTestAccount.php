<?php

namespace App\Lib\Task;

use App\Models\Facilitator\Facilitator;

class UpFacilitatorTestAccount
{
    use Task;
    public function KF_CustomerManager_first($data)
    {
        $this->updateFacTest($data);
    }

    public function KF_CustomerManager($data)
    {
        $this->updateFacTest($data);
    }
    //根据客户经理id修改账号状态
    public function updateFacTest($data): bool
    {
        try {
            $test_account = config('same.test_inner_id') != $data['inner_user_id'] ? 0 : 1;
            $fac_info = Facilitator::where('company_id', $data['company_id'])->first();
            $fac_info->is_test_account = $test_account;
            $fac_info->save();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
