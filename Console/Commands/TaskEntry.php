<?php

namespace App\Console\Commands;


use App\Lib\Task\Notice;
use Illuminate\Console\Command;
use Log;
use App\Lib\RabbitMq;

class TaskEntry extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'TaskEntry {--queue=kfw}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '接收rabbitmq后台任务处理入口';
    private $tasks;
    /**
     * 根据cmd获取类名
     */
    private $cmd_to_task = [];
    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->tasks = [
            'kfw' => [ //默认,快服网内部队列
                \App\Lib\Task\Order\SyncOrder::class,  //订单生成与同步相关
                \App\Lib\Tag\PaymentHandle::class, //充值标签
                \App\Lib\Task\Notice::class, //微信公众号通知推送
                \App\Lib\Tag\ArrearsHandle::class, //充值后消除欠费标签
                \App\Lib\Task\UpFacilitatorTestAccount::class, //客户经理分配通知
                \App\Lib\Task\ScrmPush::class, //scrm推送
            ],
            'get_push' => [ //saas广播队列
                \App\Lib\Task\PendingOrder::class, //生成待支付通知
                \App\Lib\Task\KFOpenAccount::class, //开户通知
                \App\Lib\Task\ScrmPush::class, //scrm推送
                // \App\Lib\Task\InventoryRecord::class, //库存记录
                \App\Lib\Task\Notice::class, //微信公众号通知推送
            ]
        ];
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        Log::setDefaultDriver('task');
        $queue = $this->option('queue');
        $this->rabbit = new RabbitMq($queue);
        $task = $this->tasks[$queue] ?? [];
        foreach ($task as $class_name) {
            if (empty($class_name)) {
                continue;
            }
            echo $class_name . PHP_EOL;
            try {
                $task = getSingleClass($class_name);
                $cmds = $task->getCmds();
            } catch (\Throwable $th) {
                echolog($th->getMessage());
                continue;
            }
            foreach ($cmds as $cmd) {
                if (!isset($this->cmd_to_task[$cmd])) {
                    $this->cmd_to_task[$cmd] = [];
                }
                $this->cmd_to_task[$cmd][] = $task;
            }
            $task->setMq($this->rabbit);
            if (method_exists($task, 'init')) { //需要初始化
                $task->init();
            }
        }
        while (true) {
            $r = $this->rabbit->consume([$this, 'handleMsg'], AMQP_AUTOACK);
            echo 'consume返回' . var_export($r, true) . PHP_EOL;
        }
    }
    /**
     * 根据cmd获取任务实例
     */
    private function getTask(string $cmd)
    {
        if (!isset($this->cmd_to_task[$cmd])) {
            return [];
        }
        return $this->cmd_to_task[$cmd];
    }
    public function handleMsg($envelope, $queue)
    {
        $msg_str = $msg = $envelope->getBody();

        $msg = json_decode($msg, true);
        if (empty($msg)) {
            return echolog('消息解析失败-> ' . var_export($msg, true), 'error');
        }
        if (!isset($msg['cmd']) || !is_string($msg['cmd'])) {
            return echolog('cmd参数不是字符串或者不存在-> ' . var_export($msg, true), 'error');
        }
        //指定了错误处理的类
        if (isset($msg['error_num']) && !empty($msg['class']) && !empty($msg['function'])) {
            $class_name = $msg['class'];
            $function_name = $msg['function'];
            echolog("开始执行错误消息[ {$class_name} -> {$function_name} ]");
            try {
                return (new $class_name)->$function_name($msg);
            } catch (\Throwable $th) {
                echolog($th->getFile() . "-->line:" . $th->getLine() . "-->message:" . $th->getMessage(), 'error');
                return;
            }
        }
        $cmd = $msg['cmd'];
        $class_arr = $this->getTask($cmd);
        if (empty($class_arr)) {
            return;
        }
        echolog("接收到消息[ {$msg_str} ] in pid" . getmypid());

        foreach ($class_arr as &$class) {
            $class_name = get_class($class);
            echolog("开始执行[ {$cmd} -> {$class_name} ]");
            try {
                $class->handleMsg($msg);
            } catch (\Throwable $th) {
                echolog($th->getFile() . "-->line:" . $th->getLine() . "-->message:" . $th->getMessage(), 'error');
            }
        }
    }
}
