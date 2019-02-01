<?php

namespace app\common\behavior;

use think\facade\App;
use think\facade\Cache;
use think\facade\Config;
use think\facade\Cookie;
use think\facade\Hook;
use think\facade\Lang;
use think\facade\Route;
use think\Loader;

/**
 * Class AddonInitialize
 * 插件初始化
 *
 * @package app\common\behavior
 * @VERSION
 * @AUTHOR  cavinHuang
 */
class AddonInitialize {
  
  /**
   * 执行
   *
   * @param \think\Request $request
   * @author cavinHUang
   * @date   2019/2/1 0001 下午 2:47
   *
   */
  public function run () {
    
    // 初始化常量
    $this->initConst();
    
    // 初始化项目配置
    $this->init();
    
    // 初始化自定义包
    $this->loadLibs();
    
    // 初始化addon
    $this->initAddons();
  }
  
  /**
   * 导入自定义包
   *
   * @param \think\Request $request
   * @author cavinHUang
   * @date   2019/2/1 0001 下午 2:46
   *
   */
  public function loadLibs () {
    // 加载插件语言包
    Lang::load([
      env('app_path') . 'common' . DIRECTORY_SEPARATOR . 'lang' . DIRECTORY_SEPARATOR . app('request')->langset() . DIRECTORY_SEPARATOR . 'addon.php',
    ]);
  }
  
  /**
   * 初始化一些必要的常量
   *
   * @author cavinHUang
   * @date   2019/1/20 0020 下午 8:10
   *
   */
  public function initConst () {
    
    // 插件目录
    defined('ADDON_PATH') || define('ADDON_PATH', env('root_path') . 'addons' . DIRECTORY_SEPARATOR);
    
  }
  
  /**
   * 项目配置初始化
   *
   * @param \think\Request $request
   * @author cavinHUang
   * @date   2019/2/1 0001 下午 2:46
   *
   */
  public function init () {
    // 如果是trace模式且Ajax的情况下关闭trace
    if (Config::get('app_trace') && app('request')->isAjax())
    {
      Config::set('app_trace', false);
    }
    // 切换多语言
    if (Config::get('lang_switch_on') && app('request')->get('lang'))
    {
      Cookie::set('think_var', app('request')->get('lang'));
    }
  }
  
  /**
   * 初始化插件
   *
   * @author cavinHUang
   * @date   2019/2/1 0001 下午 2:37
   *
   */
  public function initAddons () {
  
    Route::any('addons/:addon/[:controller]/[:action]', "\\system\\addon\\Route@execute");
  
    // 如果插件目录不存在则创建
    if (!is_dir(ADDON_PATH)) {
      @mkdir(ADDON_PATH, 0755, true);
    }
  
    // 注册类的根命名空间
    Loader::addNamespace('addons', ADDON_PATH);
  
    // 监听addon_init
    Hook::listen('addon_init');
  
    // 闭包自动识别插件目录配置
    Hook::add('app_init', function () {
      // 获取开关
      $autoload = (bool)Config::get('addons.autoload', false);
      // 非正是返回
      if (!$autoload) {
        return;
      }
      // 当debug时不缓存配置
      $config = App::$debug ? [] : Cache::get('addons', []);
      if (empty($config)) {
        $config = get_addon_autoload_config();
        Cache::set('addons', $config);
      }
    });
  
    // 闭包初始化行为
    Hook::add('app_init', function () {
      //注册路由
      $routeArr = (array)Config::get('addons.route');
      $domains = [];
      $rules = [];
      $execute = "\\system\\addon\\Route@execute?addon=%s&controller=%s&action=%s";
      foreach ($routeArr as $k => $v) {
        if (is_array($v)) {
          $addon = $v['addon'];
          $domain = $v['domain'];
          $drules = [];
          foreach ($v['rule'] as $m => $n) {
            list($addon, $controller, $action) = explode('/', $n);
            $drules[$m] = sprintf($execute . '&indomain=1', $addon, $controller, $action);
          }
          //$domains[$domain] = $drules ? $drules : "\\addons\\{$k}\\controller";
          $domains[$domain] = $drules ? $drules : [];
          $domains[$domain][':controller/[:action]'] = sprintf($execute . '&indomain=1', $addon, ":controller", ":action");
        } else {
          if (!$v)
            continue;
          list($addon, $controller, $action) = explode('/', $v);
          $rules[$k] = sprintf($execute, $addon, $controller, $action);
        }
      }
      Route::rule($rules);
      if ($domains) {
        Route::domain($domains);
      }
    
      // 获取系统配置
      $hooks = App::$debug ? [] : Cache::get('hooks', []);
      if (empty($hooks)) {
        $hooks = (array)Config::get('addons.hooks');
        // 初始化钩子
        foreach ($hooks as $key => $values) {
          if (is_string($values)) {
            $values = explode(',', $values);
          } else {
            $values = (array)$values;
          }
          $hooks[$key] = array_filter(array_map('get_addon_class', $values));
        }
        Cache::set('hooks', $hooks);
      }
      //如果在插件中有定义app_init，则直接执行
      if (isset($hooks['app_init'])) {
        foreach ($hooks['app_init'] as $k => $v) {
          Hook::exec($v, 'app_init');
        }
      }
      Hook::import($hooks, false);
    });
  }
}
