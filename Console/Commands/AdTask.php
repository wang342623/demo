<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Lib\RabbitMq;
use App\Models\CloudAd\Plan;
use App\Models\CloudAd\AD;
use App\Models\CloudAd\CompanyPlan;
use App\Models\Tag\Tag;
use App\Models\Tag\CompanyTag;
use Log;

class AdTask extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'AdTask';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '广告后台任务';

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
        Log::setDefaultDriver('ad');
        echolog('开始启动');
        $RabbitMq = new RabbitMq('ad');
        $r = $RabbitMq->consume([$this, 'handleMsg'], AMQP_AUTOACK);
        echo 'consume返回' . var_export($r, true) . PHP_EOL;
        return 0;
    }
    public function handleMsg($envelope)
    {
        $msg = $envelope->getBody();
        // echolog('接收到消息[  ' . $msg . '  ] in pid ' . getmypid());
        $json = json_decode($msg, true);
        if (empty($json)) {
            return echolog("消息格式错误");
        }
        $plan_id = $json['plan_id'] ?? 0;
        switch ($json['cmd'] ?? '') {
            case 'creat_update': //创建计划
                if (!empty($Plan = Plan::find($plan_id))) {
                    $this->upTags($Plan);
                    $this->upAdUsing($Plan->ad);
                }
                break;
            case 'plan_update': //更新计划
                $this->plan_update($plan_id, $json['changes'] ?? []);
                break;
            case 'plan_delete': //删除计划
                CompanyPlan::where('plan_id', $plan_id)->withTrashed()->forceDelete(); //彻底删除对应的公司关联数据
                $this->upAdUsing(AD::find($json['ad_id']));
                break;
            case 'open_push':
                $this->open_push($json);
                break;
        }
    }
    private function upAdUsing(AD $ad = null)
    {
        echolog("开始执行 upAdUsing " . var_export(is_null($ad), true));
        if (is_null($ad)) {
            return;
        }
        $c = $ad->using = $ad->plans()
            ->where('state', 1) //没有暂停
            ->where('end_date', '>', date('Y-m-d')) //未到期
            ->count();
        $ad->save();
        echolog("upAdUsing 执行结果 {$ad->ad_id} {$c}");
    }
    //更新计划
    private function plan_update($plan_id, $changes)
    {
        $Plan = Plan::find($plan_id);
        if (empty($Plan)) {
            return echolog("未找到对应计划");
        }
        if (in_array('tags_json', $changes)) { //改变标签选择
            $this->upTags($Plan);
        }
        if (in_array('state', $changes)) { //改变计划状态
            $this->upState($Plan);
        }
        $this->upAdUsing($Plan->ad);
    }
    /**
     * 更新状态
     *
     * @param Plan $Plan
     * @return void
     */
    private function upState(Plan &$Plan)
    {
        if ($Plan->state == 0) { //暂停
            $Plan->companys()->delete(); //软删除
        }
        if ($Plan->state == 1) {
            $Plan->companys()->restore(); //恢复
        }
    }
    /**
     * 更新标签选择
     *
     * @param Plan $Plan
     * @return void
     */
    private function upTags(Plan &$Plan)
    {
        $plan_id = $Plan->plan_id;
        $tags = $Plan->tags;
        $query = CompanyTag::query();
        foreach ($tags as $type_id => $tagid_arr) {
            $this->makeQuery($query, $type_id, $tagid_arr);
        }
        //该计划正确的所有公司ID
        $plan_company_ids = $query->pluck('company_id')->toArray();
        //当前的公司ID
        $cur_company_ids = $Plan->companys()->pluck('company_id')->toArray();
        //需要新增的公司ID
        $add_company_ids = array_diff($plan_company_ids, $cur_company_ids);
        echolog("[{$plan_id}]-添加的公司id-[ " . implode(',', $add_company_ids) . ' ]');
        //恢复之前删除的模型
        $Plan->companys()->withTrashed()->whereIn('company_id', $add_company_ids)->restore();
        foreach ($add_company_ids as $company_id) {
            $Plan->companys()->firstOrCreate(['company_id' => $company_id]);
        }
        //需要删除的
        $del_company_ids = array_diff($cur_company_ids, $plan_company_ids);
        echolog("[{$plan_id}]-删除的公司id-[ " . implode(',', $del_company_ids) . ' ]');
        //删除多于的
        $Plan->companys()->whereIn('company_id', $del_company_ids)->delete();
    }
    private function makeQuery(&$query, $type_id, $tagid_arr)
    {
        if (!is_array($tagid_arr) || empty($tagid_arr)) {
            return;
        }
        if (
            in_array('all', $tagid_arr) &&
            empty($tagid_arr = Tag::where('type_id', $type_id)->pluck('id')->toArray())
        ) { //该类别全部标签
            return;
        }
        $query->where(function ($q) use ($tagid_arr) {
            $c = count($tagid_arr);
            if (is_array($tagid_arr[0])) { //嵌套数组，标签有上下级关系
                $c2 = count($tagid_arr[0]);
                $q->whereJsonContains('label_json', $tagid_arr[0][$c2 - 1]);
                for ($i = 1; $i < $c; $i++) {
                    $c2 = count($tagid_arr[$i]);
                    $q->orWhereJsonContains('label_json', $tagid_arr[$i][$c2 - 1]);
                }
                return;
            }
            $q->whereJsonContains('label_json', $tagid_arr[0]);
            for ($i = 1; $i < $c; $i++) {
                $q->orWhereJsonContains('label_json', $tagid_arr[$i]);
            }
        });
    }
    //接收开通推送
    private function open_push($json)
    {
        $meal_key = $json['meal_key'] ?? '';
        if (empty($meal_key)) {
            return;
        }
        $list = AD::whereJsonContains('meal_json', $meal_key)->get();
        if ($list->isEmpty()) {
            return;
        }
        foreach ($list as $ad) {
            $click_list = $ad->clicks; //获取该广告的所有点击
            foreach ($click_list as $click) {
                $plan = Plan::find($click->plan_id);
                if (!empty($plan)) {
                    $plan->conversions = $plan->conversions + 1;
                    $plan->save();
                    echolog("{$click->plan_id} 增加转化数");
                }
            }
        }
    }
}
