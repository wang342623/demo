<?php

namespace App\Console;

// use App\Console\Commands\Remit;
use App\Console\Commands\FacilitatorBill;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Log;
use App\Console\Commands\CertCostTask;
use App\Console\Commands\MealUsageTask;
use App\Console\Commands\AnalysisLabel;
use App\Console\Commands\AdvanceNotice;
use App\Console\Commands\ArrearsNotice;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        Log::setDefaultDriver('timing');

        //统计认证成本
        $schedule->command(CertCostTask::class)->withoutOverlapping()->runInBackground()->dailyAt('00:01');
        //套餐使用量
        $schedule->command(MealUsageTask::class)->withoutOverlapping()->runInBackground()->dailyAt('00:01'); //每天零点1分执行

        //服务报表
        $schedule->command(FacilitatorBill::class)->withoutOverlapping()->runInBackground()->monthlyOn(1); //每月1号执行
        //统计客户标签
        $schedule->command(AnalysisLabel::class)->withoutOverlapping()->runInBackground()->monthlyOn(5); //每月5号执行
        //每月15号同步scrm标签
        $schedule->call([new \App\Lib\Task\ScrmPush, 'addTag'])->monthlyOn(15, '9:0');
        //月底续费提前通知
        $schedule->command(AdvanceNotice::class)->withoutOverlapping()->runInBackground()->dailyAt('15:00')->when(function () {
            $d = date('d');
            $t = date('t');
            if (in_array($d, [$t - 7, $t - 5])) { //本月倒数第8天和倒数第6天
                return true;
            }
            return false;
        });
        //欠费语音通知,每个月3号后第一个工作日,包括3号
        $schedule->command(ArrearsNotice::class)->withoutOverlapping()->runInBackground()->monthlyOn(firstWorkDay(3), '14:00');

        $task_shell = "export PHP_BINARY=" . PHP_BINARY . " && " . base_path('task.sh');
        //检查广告任务是不是活着;
        $schedule->exec($task_shell, ['start', 1, 'AdTask', 'AdTask'])->everyMinute();
        //检查待支付订单服务是不是活着
        // $schedule->exec(base_path('task.sh'), ['start', 1, 'PedOrder', 'PedOrder'])->everyTenMinutes();
        //检查TaskEntry服务是不是活着
        $schedule->exec($task_shell, ['start', 1, 'TaskEntry', 'TaskEntry'])->everyMinute(); //快服网内部
        $schedule->exec($task_shell, ['start', 1, 'TaskEntry2', 'TaskEntry', '--queue=get_push'])->everyMinute(); //公共广播
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
