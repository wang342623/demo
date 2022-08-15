<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
// use WxApi;
use GuzzleHttp\Client;
use App\Models\Tag\CompanyTag;

class TestCli extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'TestCli {action?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '测试代码';

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
        var_dump(firstWorkDay(3));
        die;
        if (($action = $this->argument('action')) && in_array($action, get_class_methods($this))) {
            $this->$action();
            return 0;
        }
        var_dump((new \App\Lib\Task\ScrmPush)->addTag());
        return 0;
    }
    public function initScrm()
    { //线上已经同步
        $all = CompanyTag::pluck('company_id');
        $ScrmPush = new \App\Lib\Task\ScrmPush;
        foreach ($all as $company_id) {
            try {
                $ScrmPush->KF_CustomerManager_first(['company_id' => $company_id]);
            } catch (\Throwable $th) {
                echo PHP_EOL;
                var_dump($th);
                echo PHP_EOL;
            }
        }
        var_dump($all);
    }

    //获取节假日,生成配置文件
    public function holidays()
    {
        $Client = new Client;
        $year = date("Y");
        $response = $Client->get("http://timor.tech/api/holiday/year/{$year}/");
        $response = $response->getBody()->getContents();
        $json = json_decode($response, true);
        $holiday = $json['holiday'] ?? [];
        if (empty($holiday)) {
            echo "获取失败->{$response}";
            return 0;
        }
        $holiday_arr = [];
        foreach ($holiday as $value) {
            $date = $value['date'];
            $holiday_arr[$date] = $value;
            unset($holiday_arr[$date]['rest']);
            unset($holiday_arr[$date]['date']);
        }
        $holiday_str = var_export($holiday_arr, true);
        $config_str = str_replace('array (', '[', "<?php\n return {$holiday_str};");
        $config_str = str_replace(')', ']', $config_str);
        echo $config_str . PHP_EOL;
        file_put_contents(config_path('holiday.php'), $config_str);
        return 0;
    }
}
