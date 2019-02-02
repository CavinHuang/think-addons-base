<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 流年 <liu21st@gmail.com>
// +----------------------------------------------------------------------

// 应用公共文件
// 公共助手函数

/**
 * 打印输出数据到文件
 *
 * @param mixed       $data  输出的数据
 * @param bool        $force 强制替换
 * @param string|null $file
 */
function p($data, $force = false, $file = null)
{
  is_null($file) && $file = env('runtime_path') . date('Ymd') . '.txt';
  $str = (is_string($data) ? $data : (is_array($data) || is_object($data)) ? print_r($data, true) : var_export($data, true)) . PHP_EOL;
  $force ? file_put_contents($file, $str) : file_put_contents($file, $str, FILE_APPEND);
}

/**
 *
 * @param  mixed ...$
 * @author cavinHUang
 * @date   2018/10/8 0008 下午 2:45
 **/
function dd(...$args)
{
  foreach ($args as $x) {
    dump($x);
  }
  exit;
}

if (!function_exists('__')) {
  
  /**
   * 获取语言变量值
   * @param string $name 语言变量名
   * @param array $vars 动态变量值
   * @param string $lang 语言
   * @return mixed
   */
  function __($name, $vars = [], $lang = '')
  {
    if (is_numeric($name) || !$name)
      return $name;
    if (!is_array($vars)) {
      $vars = func_get_args();
      array_shift($vars);
      $lang = '';
    }
    return \think\facade\Lang::get($name, $vars, $lang);
  }
  
}


if (!function_exists('adminAuth')) {
  
  /**
   * admin auth 实例
   *
   * @return \system\Auth
   * @author cavinHUang
   * @date   2019/2/2 0002 下午 8:32
   *
   */
  function adminAuth () {
    return \system\Auth::instance(config('admin.auth'));
  }
}
