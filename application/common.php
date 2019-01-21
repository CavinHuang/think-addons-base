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
