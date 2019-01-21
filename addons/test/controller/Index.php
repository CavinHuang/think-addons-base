<?php

namespace addons\test\controller;

use system\addon\Controller;

class Index extends Controller {
  
  public function index () {
    return $this->fetch();
  }
}
