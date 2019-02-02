<?php

namespace controller;

use think\Controller;

class AdminBasicController extends Controller {

  protected $auth = null;
  
  protected $request = null;
  
  /**
   * 无需登录的方法,同时也就不需要鉴权了
   * @var array
   */
  protected $noNeedLogin = ['*'];
  
  /**
   * 无需鉴权的方法,但需要登录
   * @var array
   */
  protected $noNeedRight = ['*'];
  
  /**
   * 构造函数
   *
   * AdminBasicController constructor.
   */
  public function __construct () {
    parent::__construct();
    
    $this->request = app('request');
  
    $this->auth = adminAuth();
    // token
    $token = $this->request->server('HTTP_TOKEN', $this->request->request('token', Cookie::get('token')));
  
    $path = 'addons/' . $this->addon . '/' . str_replace('.', '/', $this->controller) . '/' . $this->action;
    // 设置当前请求的URI
    $this->auth->setRequestUri($path);
    // 检测是否需要验证登录
    if (!$this->auth->match($this->noNeedLogin))
    {
      //初始化
      $this->auth->init($token);
      //检测是否登录
      if (!$this->auth->isLogin())
      {
        $this->error(__('Please login first'), 'index/user/login');
      }
      // 判断是否需要验证权限
      if (!$this->auth->match($this->noNeedRight))
      {
        // 判断控制器和方法判断是否有对应权限
        if (!$this->auth->check($path))
        {
          $this->error(__('You have no permission'));
        }
      }
    }
    else
    {
      // 如果有传递token才验证是否登录状态
      if ($token)
      {
        $this->auth->init($token);
      }
    }
  }
}
