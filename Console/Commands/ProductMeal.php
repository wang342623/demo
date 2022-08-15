<?php

namespace App\Console\Commands;

use App\Models\MealExtend;
use App\Models\MemberGrade;
use App\Models\Product;
use App\Models\Products;
use App\Models\SetMeal;
use Illuminate\Console\Command;
use function Symfony\Component\String\s;

class ProductMeal extends Command
{
    /**product_describe
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'productMeal';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '初始化产品套餐表';

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
        $product_info = Product::get()->toArray();
        foreach ($product_info as $k => $v) {
            try {
                $products_model = new Products();
                $products_model->id = $v['id'];
                $products_model->product_key = $v['category_id'];
                $products_model->product_name = $v['product_name'];
                $products_model->order_no = $v['order_no'];
                $products_model->show_page = $v['show_page'];
                $products_model->product_description = $v['category_description'];
                $products_model->is_display = $v['is_display'];
                $products_model->sell_status = $v['sell_status'];
                $products_model->notice_url = $v['notice_url'];
                $products_model->save();
            } catch (\Exception $e) {
                continue;
            }
        }
        $set_meal_info = SetMeal::get()->toArray();
        foreach ($set_meal_info as $k => $v) {
            try {
                $product_meal_model = new \App\Models\ProductMeal();
                $product_meal_model->id = $v['id'];
                $product_meal_model->meal_key = $v['product_id'];
                $product_meal_model->meal_name = $v['product_name'];
                $product_meal_model->product_key = $v['category_id'];
                $product_meal_model->unit_price = $v['unit_price'];
                $product_meal_model->price = $v['price'];
                $product_meal_model->order_no = $v['order_no'];
                $product_meal_model->sell_status = $v['sell_status'];
                $product_meal_model->is_autopay = $v['is_autopay'];
                $product_meal_model->is_fixed = $v['is_fixed'];
                $product_meal_model->is_cloud_show = $v['is_cloud_show'];
                $product_meal_model->is_allow_try = $v['is_allow_try'];
                $product_meal_model->free_months = $v['free_months'];
                $product_meal_model->is_tuihuo = $v['is_tuihuo'];
                $product_meal_model->proxy_rate = $v['proxy_rate'];
                $product_meal_model->proxy_ticheng_type = $v['proxy_ticheng_type'];
                $product_meal_model->inner_rate = $v['inner_rate'];
                $product_meal_model->inner_ticheng_type = $v['inner_ticheng_type'];
                $product_meal_model->is_shouxin = $v['is_shouxin'];
                $product_meal_model->package_id = $v['package_id'];
                $product_meal_model->save();
            } catch (\Exception $e) {
                continue;
            }
        }
        $meal_extend_info = MealExtend::get()->toArray();
        foreach ($meal_extend_info as $k => $v) {
            //查询新的表是否存在数据
            $product_meal_info = \App\Models\ProductMeal::where('meal_key', $v['product_id'])->where('product_key', $v['category_id'])->first();
            if (empty($product_meal_info)) {
                continue;
            }
            $product_meal_info->cost_type = $v['cost_type'];
            $product_meal_info->cost = $v['cost'];
            $product_meal_info->sale_settlement = $v['sale_settlement'];
            $product_meal_info->sale_proportion = $v['sale_proportion'];
            $product_meal_info->supplier_type = $v['supplier_type'];
            $product_meal_info->supplier_price = $v['supplier_price'];
            $product_meal_info->product_unit = $v['product_unit'];
            $product_meal_info->time_unit = $v['time_unit'];
            $product_meal_info->save();
        }
        $member_grade_info = MemberGrade::get()->toArray();
        foreach ($member_grade_info as $k => $v) {
            $product_meal_info = \App\Models\ProductMeal::where('meal_key', $v['module_id'])->first();
            if (empty($product_meal_info)) {
                continue;
            }
            $product_meal_info->ordinary_discount = $v['ordinary_discount'];
            $product_meal_info->silver_discount = $v['silver_discount'];
            $product_meal_info->gold_discount = $v['gold_discount'];
            $product_meal_info->coupon_discount = $v['coupon_discount'];
            $product_meal_info->special_discount = $v['special_discount'];
            $product_meal_info->inner_discount = $v['inner_discount'];
            $product_meal_info->save();
        }
        echo 'end';
    }

}
