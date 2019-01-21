<?php

namespace system\addon;

use think\facade\Config;
use think\Loader;
use think\Request;

/**
 * Class addonTools
 * addon工具类
 *
 * @package system\addon
 * @VERSION
 * @AUTHOR  cavinHuang
 */
class AddonTools {
  
  /**
   * 获取插件类的配置值值
   * @param string $name 插件名
   * @return array
   */
  public static function getAddonConfig ($name) {
    $addon = self::getAddonInstallInstance($name);
    if (!$addon) {
      return [];
    }
    return $addon->getConfig($name);
  }
  
  public static function setAddonConfig () {}
  
  public static function getAddonList () {
    $results = scandir(ADDON_PATH);
    $list = [];
    foreach ($results as $name) {
      if ($name === '.' or $name === '..')
        continue;
      if (is_file(ADDON_PATH . $name))
        continue;
      $addonDir = ADDON_PATH . $name . DIRECTORY_SEPARATOR;
      if (!is_dir($addonDir))
        continue;
    
      if (!is_file($addonDir . ucfirst($name) . '.php'))
        continue;
    
      //这里不采用get_addon_info是因为会有缓存
      //$info = get_addon_info($name);
      $info_file = $addonDir . 'info.ini';
      if (!is_file($info_file))
        continue;
    
      $info = Config::parse($info_file, '', "addon-info-{$name}");
      $info['url'] = self::createAddonUrl($name);
      $list[$name] = $info;
    }
    return $list;
  }
  
  /**
   * 获取插件的单例
   *
   * @param $name
   * @return mixed|null
   */
  public static function getAddonInstallInstance($name){
    static $_addons = [];
    if (isset($_addons[$name])) {
      return $_addons[$name];
    }
    $class = self::getAddonClass($name);
    if (class_exists($class)) {
      $_addons[$name] = new $class();
      return $_addons[$name];
    } else {
      return null;
    }
  }
  
  /**
   * 读取插件的基础信息
   *
   * @param string $name 插件名
   * @return array
   */
  public static function getAddonInfo ($name) {
    $addon = self::getAddonInstallInstance($name);
    if (!$addon) {
      return [];
    }
    return $addon->getInfo($name);
  }
  
  public static function setAddonInfo () {}
  
  /**
   * 插件显示内容里生成访问插件的url
   *
   * @param string $url 地址 格式：插件名/控制器/方法
   * @param array $vars 变量参数
   * @param bool|string $suffix 生成的URL后缀
   * @param bool|string $domain 域名
   * @return bool|string
   */
  public static function createAddonUrl ($url, $vars = [], $suffix = true, $domain = false) {
    $url = ltrim($url, '/');
    $addon = substr($url, 0, stripos($url, '/'));
    if (!is_array($vars)) {
      parse_str($vars, $params);
      $vars = $params;
    }
    $params = [];
    foreach ($vars as $k => $v) {
      if (substr($k, 0, 1) === ':') {
        $params[$k] = $v;
        unset($vars[$k]);
      }
    }
    $val = "@addons/{$url}";
    $config = self::getAddonConfig($addon);
    $dispatch = app('request')->dispatch();
    
    $indomain = $dispatch && isset($dispatch->getParam()['indomain']) && $dispatch->getParam()['indomain'] ? true : false;
    $domainprefix = $config && isset($config['domain']) && $config['domain'] ? $config['domain'] : '';
    $rewrite = $config && isset($config['rewrite']) && $config['rewrite'] ? $config['rewrite'] : [];
    if ($rewrite) {
      $path = substr($url, stripos($url, '/') + 1);
      if (isset($rewrite[$path]) && $rewrite[$path]) {
        $val = $rewrite[$path];
        array_walk($params, function ($value, $key) use (&$val) {
          $val = str_replace("[{$key}]", $value, $val);
        });
        $val = str_replace(['^', '$'], '', $val);
        if (substr($val, -1) === '/') {
          $suffix = false;
        }
      } else {
        // 如果采用了域名部署,则需要去掉前两段
        if ($indomain && $domainprefix) {
          $arr = explode("/", $val);
          $val = implode("/", array_slice($arr, 2));
        }
      }
    } else {
      // 如果采用了域名部署,则需要去掉前两段
      if ($indomain && $domainprefix) {
        $arr = explode("/", $val);
        $val = implode("/", array_slice($arr, 2));
      }
      foreach ($params as $k => $v) {
        $vars[substr($k, 1)] = $v;
      }
    }
    return url($val, [], $suffix, $domain) . ($vars ? '?' . http_build_query($vars) : '');
  }
  
  /**
   * 获取插件类的类名
   *
   * @param string $name 插件名
   * @param string $type 返回命名空间类型
   * @param string $class 当前类名
   * @return string
   */
  public static function getAddonClass ($name, $type = 'hook', $class = null) {
    $name = Loader::parseName($name);
    // 处理多级控制器情况
    if (!is_null($class) && strpos($class, '.')) {
      $class = explode('.', $class);
    
      $class[count($class) - 1] = Loader::parseName(end($class), 1);
      $class = implode('\\', $class);
    } else {
      $class = Loader::parseName(is_null($class) ? $name : $class, 1);
    }
    switch ($type) {
      case 'controller':
        $namespace = "\\addons\\" . $name . "\\controller\\" . $class;
        break;
      default:
        $namespace = "\\addons\\" . $name . "\\" . $class;
    }
    return class_exists($namespace) ? $namespace : '';
  }
}
