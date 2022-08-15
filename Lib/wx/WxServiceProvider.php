<?php

namespace Wx;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use \Wx\WxApi;

class WxServiceProvider extends ServiceProvider implements DeferrableProvider
{
	/**
	 * Register services.
	 *
	 * @return void
	 */
	public function register()
	{
		//默认配置
		$this->mergeConfigFrom(
			__DIR__ . '/config.php',
			'wx'
		);
		$this->app->singleton('WxApi', function () {
			return new WxApi(config('wx'), app('redis'));
		});
	}
	/**
	 * 获取由提供者提供的服务。
	 *
	 * @return array
	 */
	public function provides()
	{
		return [WxApi::class];
	}

	/**
	 * Bootstrap services.
	 *
	 * @return void
	 */
	public function boot()
	{
		$this->publishes([
			__DIR__ . '/config.php' => config_path('wx.php'),
		]);
	}
}
