<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CloudAd\Plan;
use Illuminate\Database\Eloquent\Collection;
use Log;

class AdPlanUp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'AdPlanUp';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '更新广告计划状态';

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
        $date = date('Y-m-d');
        var_dump($date);
        $arr = Plan::where('state', '!=', 0)
            ->where('start_date', '>', $date)
            ->get();
        $this->upHandle($arr, 2); //未到时间

        $arr = Plan::where('state', '!=', 0)
            ->where('start_date', '>=', $date)
            ->where('end_date', '<=', $date)
            ->get();
        $this->upHandle($arr, 1); //投放中

        $arr = Plan::where('state', '!=', 0)
            ->where('end_date', '<', $date)
            ->get();
        $this->upHandle($arr, 3); //3已过期
        return 0;
    }
    public function upHandle(Collection &$plans, int $state)
    {
        if ($plans->isEmpty()) {
            return;
        }
        foreach ($plans as $p) {
            echo "更新计划[ {$p->plan_id} -> {$state} ]" . PHP_EOL;
            $p->state = $state;
            $p->save();
        }
    }
}
