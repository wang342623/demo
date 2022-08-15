<?php

/**
 * 获取登录用户实例
 *
 * @param string $key
 * @return null|\App\Lib\webUser;
 */
function webUser(string $key = null)
{
    if (null == $key) {
        return app('webUser');
    }

    return app('webUser')->$key;
}

/**
 * 是否是测试环境
 */
function isDeve()
{
    return boolval(env('DEVELOPMENT_ENV'));
}
if (!function_exists('unparse_url')) {
    function unparse_url($parsed_url)
    {
        $scheme   = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : '';
        $host = isset($parsed_url['host']) ? $parsed_url['host'] : '';
        $port = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
        $user = isset($parsed_url['user']) ? $parsed_url['user'] : '';
        $pass = isset($parsed_url['pass']) ? ':' . $parsed_url['pass']  : '';
        $pass = ($user || $pass) ? "$pass@" : '';
        $path = isset($parsed_url['path']) ? $parsed_url['path'] : '';
        $query = isset($parsed_url['query']) ? '?' . $parsed_url['query'] : '';
        $fragment = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '';
        return "$scheme$user$pass$host$port$path$query$fragment";
    }
}

/**
 * url添加query参数
 *
 * @param string $url
 * @param array $arguments
 * @return string
 */
function urlAddQueryArg(string $url, array $arguments)
{
    $data = parse_url($url);
    $query = [];
    if (isset($data['query']) && $data['query'] != '') {
        parse_str($data['query'], $query);
    }
    $query = array_merge($query, $arguments);
    $data['query'] = \http_build_query($query);
    return unparse_url($data);
}

function json_response(int $code, $data = null, $msg = '', array $headers = [])
{
    $message_arr = [
        200 => 'success',
        404 => '资源不存在',
        403 => '无权限'
    ];
    if (empty($msg)) {
        $msg = $message_arr[$code] ?? '未知错误';
    }
    $res_data = ['code' => $code, 'message' => $msg];
    if (!is_null($data)) {
        $res_data['data'] = $data;
    }
    return response()->json($res_data, 200, $headers);
}


/**
 * [encryptAesCBC PHP7 AES加密  CBC模式  秘钥长度128 秘钥$cfg['privateKey_msg'] 秘钥偏移量$cfg['iv_msg'] 补码方式pkcs5 解密串编码方式十六进制]
 * @param  [type] $data           [description]
 * @param  string $privateKey_msg [description]
 * @param  string $iv_msg         [description]
 * @return [type]                 [description]
 */
function encryptAesCBC(string $data, string $privateKey_msg = null, string $iv_msg = null)
{
    $privateKey_msg = $privateKey_msg ?: config('custom.aes_key');
    $iv_msg = $iv_msg ?: config('custom.aes_iv');

    if ((strlen($data) % 16) != 0) {
        $data = $data . str_repeat("\x00", 16 - (strlen($data) % 16));
    }
    $encrypt_data = openssl_encrypt($data, 'AES-128-CBC', $privateKey_msg, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, $iv_msg);
    $encode = toHexString($encrypt_data);
    return $encode;
}




/**
 * [decryptAesCBC PHP7 AES解密]
 * @param  [type] $data           [description]
 * @param  string $privateKey_msg [description]
 * @param  string $iv_msg         [description]
 * @return [type]                 [description]
 */
function decryptAesCBC($data, string $privateKey_msg = null, string $iv_msg = null)
{
    if (empty($data) || !is_string($data)) {
        return $data;
    }
    $privateKey_msg = $privateKey_msg ?: config('custom.aes_key');
    $iv_msg = $iv_msg ?: config('custom.aes_iv');
    try {
        $encrypted = hex2bin($data);
        $decrypted = openssl_decrypt($encrypted, 'AES-128-CBC', $privateKey_msg, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, $iv_msg);
    } catch (\Throwable $th) {
        return $data; //解密失败返回原文
    }
    //去除填充
    $decrypted = fromPaddingString($decrypted);
    //去除补0
    $decrypted = rtrim($decrypted, "\0");
    //迁移老数据时 要解密两次 没加密的数据会变空
    if ($decrypted == "") {
        return $data;
    }
    return $decrypted;
}

/**
 * 处理PCKS7填充
 */
function fromPaddingString($cipher_text)
{
    $pad = ord($cipher_text[strlen($cipher_text) - 1]);
    if ($pad > 0 && $pad <= 16) {
        $cipher_text = substr($cipher_text, 0, -$pad);
    }
    return $cipher_text;
}

/**
 * [toHexString 把字符串中的每个字符转换成十六进制数]
 * @param  [type] $sa [description]
 * @return [type]     [description]
 */
if (!function_exists('toHexString')) {
    function toHexString($sa)
    {
        $buf = '';
        for ($i = 0; $i < strlen($sa); ++$i) {
            $val = dechex(ord($sa[$i]));
            if (strlen($val) < 2) {
                $val = '0' . $val;
            }

            $buf .= $val;
        }

        return $buf;
    }
}
/**
 * 重复调用，一次成功后就返回
 *
 * @param callable $call
 * @param integer $num 最大重复次数
 * @param integer $interval_time 每次间隔时间
 * @param [type] ...$args 回调参数
 * @return mixed
 */
function successRepeatCall($call, int $num = 10, int $interval_time = 1, ...$args)
{
    $r = false;
    while ($num-- > 0 && !($r = $call(...$args))) {
        sleep($interval_time);
    }
    return $r;
}

/**
 * [randStr 获取随机字符串]
 * @param  integer $len    [要求获取字符串的长度]
 * @param  string  $format [要求获取字符串的类型]
 * @return [type]          [description]
 */
function randStr($len = 6, $format = 'ALL'): string
{
    switch ($format) {
        case 'ALL':
            $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
            break;
        case 'CHAR':
            $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
            break;
        case 'NUMBER':
            $chars = '0123456789';
            break;
        default:
            $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-@#~';
            break;
    }
    $password = "";
    while (strlen($password) < $len)
        $password .= substr($chars, (mt_rand() % strlen($chars)), 1);
    return $password;
}

if (!function_exists('getSingleClass')) {
    function getSingleClass(string $className, ...$params)
    {
        static $single = [];
        if (isset($single[$className])) {
            return $single[$className];
        }
        return $single[$className] = new $className(...$params);
    }
}

if (!function_exists('changeParams')) {
    function changeParams($data)
    {
        foreach ($data as &$v) {
            $v = $v ?? '';
        }
        return $data;
    }
}

//获取上个月
function getlastMonthDays()
{
    return date('Y-m', strtotime('-1 month'));
}
//获取当月的开始与结束时间
function getMonthDays($date)
{

    $timestamp = strtotime($date);
    $start_time = date('Y-m-1 00:00:00', $timestamp);
    $mdays = date('t', $timestamp);
    $end_time = date('Y-m-' . $mdays . ' 23:59:59', $timestamp);

    return ['start_time' => $start_time, 'end_time' => $end_time];
}

//计算两个日期相差几个月
function getMonthNum($date1, $date2)
{
    $date1_stamp = strtotime($date1);
    if (empty($date2)) {
        $date2 = getlastMonthDays();
    }
    $date2_stamp = strtotime($date2);

    list($date_1['y'], $date_1['m']) = explode("-", date('Y-m', $date1_stamp));

    list($date_2['y'], $date_2['m']) = explode("-", date('Y-m', $date2_stamp));

    return abs(($date_2['y'] - $date_1['y']) * 12 + $date_2['m'] - $date_1['m']);
}
//按月返回时间格式以及相差月份
function pro_time($start, $end)
{
    //处理开始时间 结束时间
    $is_start = empty($start) ? getlastMonthDays() : date('Y-m', strtotime($start));
    $is_end = !empty($end) ? date('Y-m', strtotime($end)) : '';
    $count = getMonthNum($is_start, $is_end);
    return ['count' => $count, 'start' => $is_start, 'end' => $is_end];
}
/**
 * 重复调用，一次成功后就返回
 *
 * @param callable $call 具体的执行
 * @param callable $judge 判断条件
 * @param integer $num 次数
 * @param [type] ...$args 参数
 * @return mixed 回调函数的返回值
 */
function successJudge($call, int $num = 10, $judge = 'boolval', ...$args)
{
    $r = false;
    while ($num-- > 0 && !($judge($r = $call(...$args)))) {
    }
    return $r;
}
/**
 * 判断日期是不是放假
 *
 * @param string|null $date 日期,默认当前日期
 * @return boolean
 */
function isHoliday(string $date = null): bool
{
    if (empty($date)) {
        $date = date('Y-m-d');
    }
    $info = config("holiday.{$date}");
    $h = $info['holiday'] ?? false;
    if (!$h && (date('N', strtotime($date)) > 5)) { //周末
        return true;
    }
    return $h;
}
/**
 * 获取本月第一个工作日
 *
 * @param integer $start 开始日期
 * @return integer
 */
function firstWorkDay(int $start = 1): int
{
    $start = strtotime(date("Y-m-{$start}"));
    while (isHoliday(date('Y-m-d', $start))) {
        $start = strtotime("+1 day", $start);
    }
    return date("d", $start);
}
/**
 * 打印日志并输出到日志文件
 *
 * @param string $msg
 * @param string $level
 * @return void
 */
function echolog(string $msg, string $level = 'info')
{
    echo $msg . PHP_EOL;
    app('Log')::$level($msg);
}
function mapped_implode(string $glue, array $array, string $symbol = '=')
{
    return implode(
        $glue,
        array_map(
            function ($k, $v) use ($symbol) {
                return $k . $symbol . $v;
            },
            array_keys($array),
            array_values($array)
        )
    );
}
/**
 * 通过rabbitmq同步redis
 *
 * @param integer $company_id 公司ID
 * @param string $key 键名
 * @param array|string $value
 * @param string $type 键类型小写
 * @param string $cmd 操作命令:RADD|RDEL|RUPDATE
 * @param string $rtyle redis类型:view|cluster
 * @param integer $expire 多少秒后到期
 * @return bool
 */
function syncRedis(int $company_id, string $key, $value, string $type, string $cmd = 'RADD', string $rtyle = 'cluster', int $expire = null)
{
    $cmd = (strtoupper($cmd) == 'RADD') ? 'RADD' : "RDEL";
    if (is_array($value)) {
        $value = mapped_implode('|', $value, '|');
    }
    $data = [
        'cmd' => $cmd,
        'type' => strtolower($type),
        'name' => $key,
        'values' => (string) $value,
        'rtyle' => $rtyle,
        'company_id' => $company_id,
    ];

    if (!empty($expire)) {
        $datas['expire'] = $expire;
    }
    return (new \App\Lib\RabbitMq('sync'))->publish(json_encode($data));
}
/**
 * 获取类成员函数的参数(关联数组)
 *
 * @param array $args
 * @param object|string $objectOrMethod
 * @param string|null|null $method
 * @return array
 */
function getMethodArgs(array $args,  $objectOrMethod, $method = null): array
{
    $data = [];
    $ref = new \ReflectionMethod($objectOrMethod, $method);
    foreach ($ref->getParameters() as $param) {
        $p = $param->getPosition();
        if ($args[$p] ?? false) {
            $data[$param->name] = $args[$p];
        }
    }
    return $data;
}
