<?php
require "vendor/autoload.php";
header("Content-type: text/html; charset=utf-8");

// 配置项目的路径
define('USER_PATH', dirname(__FILE__));

// 配置基库的目录路径
define('X_PATH', '/data/html/xyframework');
require_once(X_PATH . '/xy.php');

//-----------初始化结束------------