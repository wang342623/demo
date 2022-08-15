<?php

namespace App\Console\Commands;

use App\Lib\Task\CreateTable\CreateBill;
use Illuminate\Console\Command;

class CreateTable extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:table';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '新建数据表';

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
        \App\Lib\Task\CreateTable\CreateTable::createBill();
    }
}
