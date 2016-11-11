<?php
// Copyright 2016 The PHP Tuner Authors. All rights reserved.
// Use of this source code is governed by a GPL-3.0
// license that can be found in the LICENSE file.

// 应用程序管理类
// 负责框架内部外部数据流动传递
class App {

	// 初始化
	public function __construct($is_debug = false) {
		// 初始化开始
		Log::init($is_debug);
		if ($is_debug && Config::$mode == 'dev') {
			ini_set('display_errors', 1);
			error_reporting(E_ALL);
		}
	}

	// must run in cgi mode
	public static function runGGI($is_debug = false){
		if(php_sapi_name() !== 'cgi'){
			exit('must run in cgi mode');
		}
		self::run($is_debug);
	}
	
	// must run in cli mode
	public static function runCLI($is_debug = false){
		if(php_sapi_name() !== 'cli'){
			exit('must run in cli mode');
		}
		self::run($is_debug);
	}

	// 执行处理流程
	public static function run($is_debug = false) {
		// 如果用cli方式运行(不去改变$_SERVER变量)
		$app = new self($is_debug);
		if (php_sapi_name() === 'cli') {
			// use $_SERVER['argv'] instead of $argv(not available)
			$req = new RequestCli($_SERVER['argv'][1], getopt('d'));
		} else {
			$req = new Request();
		}
		// 使用PATH_INFO路由
		$req->route_uri = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : $req->uri;
		// 将多个／替换成一个
		$req->route_uri = preg_replace('#/{1,}#', '/', $req->route_uri);
		$is_https       = isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '443';
		$req->base_url  = $is_https ? "https" : 'http';
		isset($req->header['Host']) && $req->base_url .= "://{$req->header['Host']}";
		if ($req->route_uri) {
			$base_pos = stripos($_SERVER['REQUEST_URI'], $req->route_uri);
			$req->base_url .= parse_url(substr($_SERVER['REQUEST_URI'], 0, $base_pos), PHP_URL_PATH);
		} else {
			$req->base_url .= $_SERVER['REQUEST_URI'];
		}
		$res = new Response(Config::common('charset'));
		$cfg = new Config();
		try {
			// 默认处理器
			Route::addHandler(function ($req, $res, $cfg) {
				//开始查找控制器
				$route_path = parse_url($req->route_uri, PHP_URL_PATH);
				if (!$route_path) {
					$route_path = preg_replace('/\?(.*)/i', '', $req->route_uri);
				}
				$route_file_path = realpath(Helper::dir(APP_ROOT_DIR, $route_path));
				if (preg_match('#^/public/#', $route_path) && is_file($route_file_path)) {
					$res->file($route_file_path);
					return true;
				}
				$pathinfo = pathinfo($route_path);
				//$pathinfo = pathinfo(parse_url($req->uri, PHP_URL_PATH));
				$ext    = isset($pathinfo['extension']) ? $pathinfo['extension'] : '';
				$path   = str_replace(array('//'), '', "{$pathinfo['dirname']}/{$pathinfo['filename']}");
				$params = explode("/", trim($path, '/'));

				//附加上最后的文件名
				//$pathinfo['filename'] && $params[] = $pathinfo['filename'];
				$len = count($params);
				//默认倒数第二个是controller, 倒数第一个是action
				$controller_pos = $len > 1 ? $len - 2 : 0;
				//从DOCUMENT_ROOT开始找起
				$controller_dir = APP_CONTROLLER_DIR;
				//尝试匹配子目录(大于三层目录时)
				for ($i = 0; $i < $len - 2; $i++) {
					$value          = $params[$i];
					$controller_pos = $i;
					$controller_dir = Helper::dir($controller_dir, $value);
					if (!file_exists($controller_dir)) {
						$controller_dir = dirname($controller_dir);
						break;
					}
					$controller_pos++;
				}
				//子目录
				$sub_dir = call_user_func_array('Helper::dir', array_slice($params, 0, $controller_pos));

				$controller = isset($params[$controller_pos]) && preg_match('/^[a-zA-Z]+/i', $params[$controller_pos]) ? $params[$controller_pos] : Config::common('defaultController');
				$controller = str_replace(' ', '', ucwords(str_replace(CAMEL_CLASS_SEP, ' ', $controller)));
				$class      = ucwords($controller) . "Controller";
				//controller优先查找当前目录
				spl_autoload_register(_createLoader_(
					$controller_dir,
					Helper::dir(APP_ROOT_DIR, $sub_dir, 'model'),
					Helper::dir(APP_ROOT_DIR, $sub_dir, 'lib')
				), true, true);

				if (!class_exists($class)) {
					throw new Exception("not found(controller: $class)", 404);
				}
				//实例化控制器
				$c = new $class($req, $res, $cfg);
				//寻找控制器方法
				if(isset($params[$controller_pos + 1])){
					$action = $params[$controller_pos + 1];
					if(!preg_match('/^([a-zA-Z_\-]+)$/i', $action)){
						throw new Exception("action is illegal", 500);
					}
					$action_args = array_slice($params, $i + 2);
				}else{//目录请求
					$action = $c->default_action ? $c->default_action : Config::common('defaultAction');
					$action_args = array_slice($params, $i + 1);
				}
				//分隔符也许可以定制
				$action = str_replace(' ', '', ucwords(str_replace(CAMEL_CLASS_SEP, ' ', $action)));
				if (!method_exists($c, $action)) {
					throw new Exception("not found(action: $action)", 404);
				}
				//捕获应用层异常，交给controller 处理
				try {
					$action_args = array_map('urldecode', $action_args);
					//action 后的做为参数
					call_user_func_array(array($c, $action), $action_args);
				} catch (Exception $e) {
					$c->_handleException($e);
				}
			});
			Route::dispatch($req, $res, $cfg);
		} catch (Exception $e) {
			//Todo 需要优化
			self::handleException($e);
		}
		return $app;
	}

	//处理异常
	public static function handleException($e) {
		Log::debug($e);
		$code                = $e->getCode() ? $e->getCode() : 500;
		$msg                 = $e->getMessage();
		header($msg, true, $code);
		echo <<<HTML
<!DOCTYPE html>
<html>
<head>
<title>$msg - $code</title>
<meta charset="utf-8" />
</head>
<body>
	<h3>$msg - $code</h3>
</body>
</html>
HTML;
		exit(0);
	}

	//all done
	public function __destruct() {
		Log::show();
	}

}
