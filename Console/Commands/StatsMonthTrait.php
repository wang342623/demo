<?php

namespace App\Console\Commands;

use Log;

trait StatsMonthTrait{
    /***
     * 此方法为按月执行方法 传入年月即可 可连续多月传值 {start?} 开始年月 {end?} 结束年月份
     * 如不传开始年月则视为默认执行 上个月 ***
     */
    public function handle(){
        $start = $this->argument('start');
        $end = $this->argument('end');

        Log::setDefaultDriver('remit');
        Log::info('start----' . $start);
        Log::info('end----' . $end);
        $date_arr=pro_time($start,$end);
        for ($i=0;$i<=$date_arr['count'];$i++){
            $create_time=date('Y-m',strtotime('+'.$i.'month',strtotime($date_arr['start'])));
            $this->handleMonth($create_time);
       }
    }



}
