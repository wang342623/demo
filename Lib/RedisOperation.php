<?php

namespace App\Lib;

use Illuminate\Support\Facades\Log;

/**
 * 对默认的redis连接封装一层，以实现不同的redis键使用不同的方法获取
 * 不承诺所使用的连接是正常的，正确的链接需要在上一层调用中自己判断
 */
class RedisOperation
{
    private $connect = null;
    // static $class = null;
    public function __construct($c)
    {
        $this->connect = $c;
    }
    public function __call($name, $arguments)
    {
        return $this->connect->$name(...$arguments);
    }
    private function handleHash(string $key, $field = null, $handle = null)
    {
        if (empty($handle)) {
            $handle = $this->connect;
        }
        if (empty($field)) {
            return $handle->hgetall($key);
        }
        if (is_array($field)) {
            return $handle->hmset($key, $field);
        }
        return $handle->hget($key, $field);
    }
    public function comInfo(int $company_id, $key = null)
    {
        $redis_key = "com.info.{{$company_id}}";
        $v = $this->handleHash($redis_key, $key);
        if (empty($v) && $key == 'cloud_service') {
            $v = 'jh';
        }
        if (empty($key) && (!isset($v['cloud_service']) || empty($v['cloud_service']))) {
            $v['cloud_service'] = 'jh';
        }
        return $v;
    }
    public function hdelComInfo(int $company_id, string $key)
    {
        return $this->connect->hdel("com.info.{{$company_id}}", $key);
    }
    public function saasCompanyInfo(int $company_id, $key = null)
    {
        return $this->handleHash("saas.company.info.{{$company_id}}", $key);
    }
    public function workInfo(int $company_id, int $id6d, $key = null)
    {
        return $this->handleHash("work.info.{{$company_id}}.{$id6d}", $key);
    }
    public function mainAccount(int $company_id)
    {
        return $this->saasCompanyInfo($company_id)['account'] ?? null;
    }
    public function mainId6d(int $company_id)
    {
        return $this->comInfo($company_id, 'id6d');
    }
    public function facilitatorId(int $company_id)
    {
        $facilitator_id = $this->comInfo($company_id, 'facilitator_id');
        if (empty($facilitator_id)) {
            return config('up.jh_facilitator_id');
        }
        return intval($facilitator_id);
    }
    /**
     * 获取新的商城版ID
     *
     * @return void|string
     */
    public function shopID($company_id)
    {
        return $this->comInfo($company_id, 'shop_id');
    }
    public function account(int $company_id, int $id6d)
    {
        return $this->workInfo($company_id, $id6d, 'account');
    }
    public function cloudService(int $company_id)
    {
        return $this->comInfo($company_id, 'cloud_service');
    }
    public function groupHost(int $company_id)
    {
        $c = $this->cloudService($company_id);
        $jh_host = config("up.jh_group_host");
        if ($c == 'jh') {
            return $jh_host;
        }
        return $this->cloudInfo($c)['group_host'] ?? $jh_host;
    }
    public function cloudInfo(string $cloud, $key = null)
    {
        return $this->handleHash("cloud.info.{$cloud}", $key);
    }
    public function alias(int $company_id)
    {
        return $this->comInfo($company_id, 'alias');
    }
    public function setAlias(int $company_id, string $alias)
    {
        return $this->comInfo($company_id, ['alias' => $alias]);
    }
    public function dn(int $company_id)
    {
        return $this->comInfo($company_id, 'dn');
    }
    /**
     * 批量操作com.info键
     *
     * @param array $company_ids
     * @return array
     */
    public function pipelineComInfo(array $company_ids, $key = null)
    {
        $company_ids = array_values($company_ids);
        $obj = $this;
        $arr = $this->connect->pipeline(function ($pipe) use (&$company_ids, &$obj, &$key) {
            foreach ($company_ids as $company_id) {
                $obj->handleHash("com.info.{{$company_id}}", $key, $pipe);
            }
        });
        $new_arr = [];
        foreach ($company_ids as $i => $company_id) {
            $new_arr[$company_id] = $arr[$i];
        }
        return $new_arr;
    }
    public function pipelineAlias(array $company_ids)
    {
        return $this->pipelineComInfo($company_ids, 'alias');
    }
    //获取通知次数
    public function noticeNum(int $company_id)
    {
        return intval($this->connect->get("renew.notice.{{$company_id}}"));
    }
    //通知次数+1,并设置到期时间
    public function incNoticeNum(int $company_id, int $expire = null)
    {
        $num = $this->noticeNum($company_id);
        $num++;
        $this->connect->set("renew.notice.{{$company_id}}",  $num);
        if ($expire) {
            $this->connect->EXPIREAT("renew.notice.{{$company_id}}", $expire);
        }
        return  $num;
    }
    public function syncCom(int $company_id, array $data)
    {
        $redis_key = "com.info.{{$company_id}}";
        $this->comInfo($company_id, $data);
        $rt=syncRedis($company_id, $redis_key,  mapped_implode('|', $data, '|'), 'hash');
        Log::info('push----'.var_export($rt,true));
    }
};
