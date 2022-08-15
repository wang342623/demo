<?php

namespace Wx;

use Illuminate\Support\Facades\Facade;

class WxFacade extends Facade
{
	protected static function getFacadeAccessor()
	{
		return 'WxApi';
	}
}
