<?php

namespace system;

use think\facade\Config;
use think\facade\Hook;
use think\facade\Validate;
use helpers\Random;
use think\Db;

class Auth {
  /**
   * auth 实例
   *
   * @var null
   */
  protected static $instance = null;
  
  /**
   * 错误信息
   *
   * @var string
   */
  protected $error = '';
  
  /**
   * 是否登录
   *
   * @var bool
   */
  protected $logined = false;
  
  /**
   * 用户实例
   *
   * @var null
   */
  protected $user = null;
  
  /**
   * token 实例
   *
   * @var string
   */
  protected $token = '';
  
  /**
   * Token默认有效时长
   *
   * @var int
   */
  protected $keeptime = 2592000;
  
  /**
   * 当前请求的URI
   *
   * @var string
   */
  protected $requestUri = '';
  
  /**
   * 用户权限策略
   *
   * @var array
   */
  protected $rules = [];
  
  /**
   * 默认配置
   *
   * @var array
   */
  protected $config = [];
  
  /**
   * auth 配置
   *
   * @var array
   */
  protected $options = [];
  
  /**
   * 允许访问的字段
   *
   * @var array
   */
  protected $allowFields = ['id', 'username', 'nickname', 'mobile', 'avatar', 'score'];
  
  /**
   * 用户Model实例
   *
   * @var
   */
  protected $userInstance;
  
  /**
   * 用户策略Model实例
   *
   * @var
   */
  protected $userRuleInstance;
  
  /**
   * 用户信息Model实例
   *
   * @var
   */
  protected $userProfile;
  
  /**
   * 构造函数
   *
   * Auth constructor.
   * @param array $options
   */
  public function __construct($options = [])
  {
    if ($config = Config::get('user')) {
      $this->config = array_merge($this->config, $config);
    }
    $this->options = array_merge($this->config, $options);
    
    $this->userInstance();
  }
  
  /**
   * 实例化用户和用户权限策略model
   *
   * @author cavinHUang
   * @date   2019/1/21 0021 下午 2:19
   *
   */
  public function userInstance()
  {
    if (Config::get('auth.user')) {
      $userPath = Config::get('auth.user');
      if (class_exists($userPath)) {
        $this->userInstance = new $userPath();
      }
    }
    
    if (Config::get('auth.userRule')) {
      $userRulePath = Config::get('auth.userRule');
      $this->userRuleInstance = new $userRulePath();
    }
    
    if (Config::get('auth.userProfile')) {
      $userRulePath = Config::get('auth.userProfile');
      $this->userProfile = new $userRulePath();
    }
  }
  
  /**
   *
   * @param array $options 参数
   * @return Auth
   */
  public static function instance($options = [])
  {
    if (is_null(self::$instance)) {
      self::$instance = new static($options);
    }
    
    return self::$instance;
  }
  
  /**
   * 获取User模型
   * @return User
   */
  public function getUser()
  {
    return $this->user;
  }
  
  /**
   * 兼容调用user模型的属性
   *
   * @param string $name
   * @return mixed
   */
  public function __get($name)
  {
    return $this->user ? $this->user->$name : null;
  }
  
  /**
   * 根据Token初始化
   *
   * @param string       $token    Token
   * @return boolean
   */
  public function init($token)
  {
    if ($this->logined) {
      return true;
    }
    if ($this->error) {
      return false;
    }
    $data = Token::get($token);
    if (!$data) {
      return false;
    }
    $user_id = intval($data['user_id']);
    if ($user_id > 0) {
      $user = $this->userInstance::get($user_id);
      if (!$user) {
        $this->setError('Account not exist');
        return false;
      }
      if ($user['status'] !== 1) {
        $this->setError('Account is locked');
        return false;
      }
      $this->user = $user;
      $this->logined = true;
      $this->token = $token;
      
      //初始化成功的事件
      Hook::listen("user_init_successed", $this->user);
      
      return true;
    } else {
      $this->setError('You are not logged in');
      return false;
    }
  }
  
  /**
   * 注册用户
   *
   * @param string $username  用户名
   * @param string $password  密码
   * @param string $email     邮箱
   * @param string $mobile    手机号
   * @param array $extend    扩展参数
   * @return boolean
   */
  public function register($username, $password, $email = '', $mobile = '', $extend = [])
  {
    // 检测用户名或邮箱、手机号是否存在
    if ($this->userInstance::getByUsername($username))
    {
      $this->setError('Username already exist');
      return FALSE;
    }
    if ($email && $this->userInstance::getByEmail($email))
    {
      $this->setError('Email already exist');
      return FALSE;
    }
    if ($mobile && $this->userInstance::getByMobile($mobile))
    {
      $this->setError('Mobile already exist');
      return FALSE;
    }
  
    $ip = request()->ip();
    $time = time();
  
    $data = [
      'username' => $username,
      'password' => $password,
      'email'    => $email,
      'mobile'   => $mobile,
      'level'    => 1,
      'score'    => 0,
      'avatar'   => '',
    ];
    $params = array_merge($data, [
      'nickname'  => $username,
      'salt'      => Random::alnum(),
      'jointime'  => $time,
      'joinip'    => $ip,
      'logintime' => $time,
      'loginip'   => $ip,
      'prevtime'  => $time,
      'status'    => 'normal'
    ]);
    $params['password'] = $this->getEncryptPassword($password, $params['salt']);
    $params = array_merge($params, $extend);
  
    //账号注册时需要开启事务,避免出现垃圾数据
    Db::startTrans();
    try
    {
      $user = $this->userInstance::create($params);
      Db::commit();
    
      // 此时的Model中只包含部分数据
      $this->_user = $this->userInstance::get($user->id);
    
      //设置Token
      $this->_token = Random::uuid();
      Token::set($this->_token, $user->id, $this->keeptime);
    
      //注册成功的事件
      Hook::listen("user_register_successed", $this->_user);
    
      return TRUE;
    }
    catch (Exception $e)
    {
      $this->setError($e->getMessage());
      Db::rollback();
      return FALSE;
    }
  }
  
  /**
   * 用户登录
   *
   * @param string    $account    账号,用户名、邮箱、手机号
   * @param string    $password   密码
   * @return boolean
   */
  public function login($account, $password)
  {
    $field = Validate::is($account, 'email') ? 'email' : (Validate::regex($account, '/^1\d{10}$/') ? 'mobile' : 'username');
    $user = $this->userInstance::get([$field => $account]);
    if (!$user)
    {
      $this->setError('Account is incorrect');
      return FALSE;
    }
  
    if ($user->status != 'normal')
    {
      $this->setError('Account is locked');
      return FALSE;
    }
    if ($user->password != $this->getEncryptPassword($password, $user->salt))
    {
      $this->setError('Password is incorrect');
      return FALSE;
    }
  
    //直接登录会员
    $this->direct($user->id);
  
    return TRUE;
  }
  
  /**
   * 注销
   *
   * @return boolean
   */
  public function logout()
  {
    if (!$this->logined) {
      $this->setError('You are not logged in');
      return false;
    }
    //设置登录标识
    $this->logined = false;
    //删除Token
    Token::delete($this->token);
    //注销成功的事件
    Hook::listen("user_logout_successed", $this->user);
    return true;
  }
  
  /**
   * 修改密码
   * @param string    $newpassword        新密码
   * @param string    $oldpassword        旧密码
   * @param bool      $ignoreoldpassword  忽略旧密码
   * @return boolean
   */
  public function changepwd($newpassword, $oldpassword = '', $ignoreoldpassword = false)
  {
    if (!$this->logined) {
      $this->setError('You are not logged in');
      return false;
    }
    //判断旧密码是否正确
    if ($this->user->login_pwd == $this->getEncryptPassword($oldpassword, $this->user->login_secret) || $ignoreoldpassword) {
      $salt = Random::alnum();
      $newpassword = $this->getEncryptPassword($newpassword, $salt);
      $this->user->save(['password' => $newpassword, 'salt' => $salt]);
      
      Token::delete($this->token);
      //修改密码成功的事件
      Hook::listen("user_changepwd_successed", $this->user);
      return true;
    } else {
      $this->setError('Password is incorrect');
      return false;
    }
  }
  
  /**
   * 直接登录账号
   * @param int $user_id
   * @return boolean
   */
  public function direct($user_id)
  {
    $user = $this->userInstance->get($user_id);
    if ($user) {
      $ip = request()->ip();
      $time = time();
      
      //判断连续登录和最大连续登录
      if ($user->logintime < \fast\Date::unixtime('day')) {
        $user->successions = $user->logintime < \fast\Date::unixtime('day', -1) ? 1 : $user->successions + 1;
        $user->maxsuccessions = max($user->successions, $user->maxsuccessions);
      }
      
      $user->prevtime = $user->logintime;
      //记录本次登录的IP和时间
      $user->loginip = $ip;
      $user->logintime = $time;
      
      $user->save();
      
      $this->user = $user;
      
      $this->token = Random::uuid();
      Token::set($this->token, $user->id, $this->keeptime);
      
      $this->logined = true;
      
      //登录成功的事件
      Hook::listen("user_login_successed", $this->user);
      return true;
    } else {
      return false;
    }
  }
  
  /**
   * 检测是否是否有对应权限
   * @param string $path      控制器/方法
   * @param string $module    模块 默认为当前模块
   * @return boolean
   */
  public function check($path = null, $module = null)
  {
    if (!$this->logined) {
      return false;
    }
    
    $ruleList = $this->getRuleList();
    $rules = [];
    foreach ($ruleList as $k => $v) {
      $rules[] = $v['name'];
    }
    $url = ($module ? $module : request()->module()) . '/' . (is_null($path) ? $this->getRequestUri() : $path);
    $url = strtolower(str_replace('.', '/', $url));
    return in_array($url, $rules) ? true : false;
  }
  
  /**
   * 判断是否登录
   * @return boolean
   */
  public function isLogin()
  {
    if ($this->logined) {
      return true;
    }
    return false;
  }
  
  /**
   * 获取当前Token
   * @return string
   */
  public function getToken()
  {
    return $this->token;
  }
  
  /**
   * 获取会员基本信息
   */
  public function getUserinfo()
  {
    $data = $this->user->toArray();
    $allowFields = $this->getAllowFields();
    $userinfo = array_intersect_key($data, array_flip($allowFields));
    $userinfo = array_merge($userinfo, Token::get($this->token));
    return $userinfo;
  }
  
  /**
   * 获取会员组别规则列表
   * @return array
   */
  public function getRuleList()
  {
    if ($this->rules) {
      return $this->rules;
    }
    $group = $this->user->group;
    if (!$group) {
      return [];
    }
    $rules = explode(',', $group->rules);
    $this->rules = $this->userRuleInstance->where('status', 'normal')->where('id', 'in', $rules)->field('id,pid,name,title,ismenu')->select();
    return $this->rules;
  }
  
  /**
   * 获取当前请求的URI
   * @return string
   */
  public function getRequestUri()
  {
    return $this->requestUri;
  }
  
  /**
   * 设置当前请求的URI
   * @param string $uri
   */
  public function setRequestUri($uri)
  {
    $this->requestUri = $uri;
  }
  
  /**
   * 获取允许输出的字段
   * @return array
   */
  public function getAllowFields()
  {
    return $this->allowFields;
  }
  
  /**
   * 设置允许输出的字段
   * @param array $fields
   */
  public function setAllowFields($fields)
  {
    $this->allowFields = $fields;
  }
  
  /**
   * 删除一个指定会员
   * @param int $user_id 会员ID
   * @return boolean
   */
  public function delete($user_id)
  {
    $user = $this->userInstance->get($user_id);
    if (!$user) {
      return false;
    }
    
    // 调用事务删除账号
    $result = Db::transaction(function ($db) use ($user_id) {
      // 删除会员
      $this->userInstance->destroy($user_id);
      // 删除会员指定的所有Token
      Token::clear($user_id);
      return true;
    });
    if ($result) {
      Hook::listen("user_delete_successed", $user);
    }
    return $result ? true : false;
  }
  
  /**
   * 获取密码加密后的字符串
   * @param string $password  密码
   * @param string $salt      密码盐
   * @return string
   */
  public function getEncryptPassword($password, $salt = '')
  {
    return md5(md5($password) . $salt);
  }
  
  /**
   * 检测当前控制器和方法是否匹配传递的数组
   *
   * @param array $arr 需要验证权限的数组
   * @return boolean
   */
  public function match($arr = [])
  {
    $request = app('request');
    $arr = is_array($arr) ? $arr : explode(',', $arr);
    if (!$arr) {
      return false;
    }
    $arr = array_map('strtolower', $arr);
    // 是否存在
    if (in_array(strtolower($request->action()), $arr) || in_array('*', $arr)) {
      return true;
    }
    
    // 没找到匹配
    return false;
  }
  
  /**
   * 设置会话有效时间
   * @param int $keeptime 默认为永久
   */
  public function keeptime($keeptime = 0)
  {
    $this->keeptime = $keeptime;
  }
  
  /**
   * 渲染用户数据
   * @param array     $datalist   二维数组
   * @param mixed     $fields     加载的字段列表
   * @param string    $fieldkey   渲染的字段
   * @param string    $renderkey  结果字段
   * @return array
   */
  public function render(&$datalist, $fields = [], $fieldkey = 'user_id', $renderkey = 'userinfo')
  {
    $fields = !$fields ? ['id', 'nickname', 'level', 'avatar'] : (is_array($fields) ? $fields : explode(',', $fields));
    $ids = [];
    foreach ($datalist as $k => $v) {
      if (!isset($v[$fieldkey])) {
        continue;
      }
      $ids[] = $v[$fieldkey];
    }
    $list = [];
    if ($ids) {
      if (!in_array('id', $fields)) {
        $fields[] = 'id';
      }
      $ids = array_unique($ids);
      $selectlist = $this->userInstance->where('id', 'in', $ids)->column($fields);
      foreach ($selectlist as $k => $v) {
        $list[$v['id']] = $v;
      }
    }
    foreach ($datalist as $k => &$v) {
      $v[$renderkey] = isset($list[$v[$fieldkey]]) ? $list[$v[$fieldkey]] : null;
    }
    unset($v);
    return $datalist;
  }
  
  /**
   * 设置错误信息
   *
   * @param string $error 错误信息
   * @return Auth
   */
  public function setError($error)
  {
    $this->error = $error;
    return $this;
  }
  
  /**
   * 获取错误信息
   * @return string
   */
  public function getError()
  {
    return $this->error ? __($this->error) : '';
  }
}
