<?php
namespace App\Lib;

/**
 * Class FacilitatorApi
 * @package App\Lib
 * 服务商相关接口调用
 */
class FacilitatorApi extends BasicApi
{
    protected $mel_url;
    protected $Token;
    public function __construct()
    {
        parent::__construct(config('custom.Facilitator_host'));
        $this->mel_url = '/api/productmeal';
        $this->Token = 'fyweao&@^#()@)#!><?F';
    }
    //处理响应
    public function res(string &$response)
    {
        return json_decode($response,true);
    }

    //同步套餐 新增或修改套餐接口
    public function addOrupdateMeal($data){
        return $this->send($this->mel_url,$data,[$this,'res']);
    }
    //删除套餐
    public function delMeal($meal_key){
        $data=[
            'cmd' => 'del_meal',
            'meal_id' => $meal_key,
        ];
        return $this->send($this->mel_url,$data,[$this,'res']);
    }
    //同步产品 新增产品
    public function addProduct($data){
        try{

            $data['cmd']='add_product';
            $data['product_key']=$data['category_id'];
            $data['53kf_token']=$this->Token;
            return $this->send($this->mel_url,$data,[$this,'res']);
        }catch (\Exception $e){
            return ['code'=>500,'msg'=>'param is error'];
        }

    }
    //同步产品 新增产品
    public function updateProduct($data){
        try{
            $data['cmd']='mod_product';
            $data['product_key']=$data['category_id'];
            $data['53kf_token']=$this->Token;
            return $this->send($this->mel_url,$data,[$this,'res']);
        }catch (\Exception $e){

            return ['code'=>500,'msg'=>'param is error'];
        }

    }
    //删除产品
    public function del_product($category_id){
        $saas_pro_data = array (
            'cmd' => 'del_product',
            'product_id' => $category_id,
            'category_id'=> $category_id,
        );
        return $this->send($this->mel_url,$saas_pro_data,[$this,'res']);
    }



}
