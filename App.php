<?php
// 应用程序管理类
// 负责框架内部外部数据流动传递

class App {

	// 初始化
	public function __construct() {
		// 初始化开始
		$is_debug = Request::get('debug') == 'dodebug';
		Log::init($is_debug);
		if ($is_debug && Config::$mode == 'dev') {
			ini_set('display_errors', 1);
			error_reporting(E_ALL);
		}
	}

	// 执行处理流程
	public static function run() {
		$app = new self();
		// 如果用cli方式运行(不去改变$_SERVER变量)
		if (php_sapi_name() === 'cli') {
			// use $_SERVER['argv'] instead of $argv(not available)
			$req = new RequestCli($_SERVER['argv'][1], getopt('d'));
		} else {
			$req = new Request();
		}
		// 使用PATH_INFO路由
		$req->route_uri = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : $req->uri;
		$req->base_url  = "http://{$req->header['Host']}";
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
				if (is_file($route_file_path)) {
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
				//$req->base_url || $req->base_url = implode('/',  array_slice($params, 0, $controller_pos));

				$controller = isset($params[$controller_pos]) ? $params[$controller_pos] : Config::common('defaultController');
				$controller = str_replace(' ', '', ucwords(str_replace(CAMEL_CLASS_SEP, ' ', $controller)));
				$class      = ucwords($controller) . "Controller";
				//尝试加载控制器文件
				/*
	                                $controller_path = Helper::dir($controller_dir, "{$class}.php");
	                                if(!file_exists($controller_path)){
	                                        //是否需要抛出异常，替代直接返回
	                                        return $res->notFound();
	                                }
                                */
				//controller优先查找当前目录
				spl_autoload_register(_createLoader_(
					$controller_dir,
					Helper::dir(APP_ROOT_DIR, $sub_dir, 'model'),
					Helper::dir(APP_ROOT_DIR, $sub_dir, 'lib')
				), true, true);

				if (!class_exists($class)) {
					throw new Exception("not found(controller: $class)");
				}
				//实例化控制器
				$c = new $class($req, $res, $cfg);
				//寻找控制器方法
				$action = isset($params[$controller_pos + 1]) && preg_match('/^[a-zA-Z]+/i', $params[$controller_pos + 1]) ? $params[$controller_pos + 1] : Config::common('defaultAction');
				//分隔符也许可以定制
				$action = str_replace(' ', '', ucwords(str_replace(CAMEL_CLASS_SEP, ' ', $action)));
				//Log::debug($route_uri, $req->base_url, $path, $params, $controller, $action);
				if (!method_exists($c, $action)) {
					$action = $c->default_action; //"not found(action: $action)";
				}
				//捕获应用层异常，交给controller 处理
				try {
					//action 后的做为参数
					call_user_func_array(array($c, $action), array_slice($params, $i + 2));
				} catch (Exception $e) {
					$c->_handleException($e);
				}
			});
			Route::dispatch($req, $res, $cfg);
		} catch (Exception $e) {
			//Todo 需要优化
			Log::debug($e);
			$res->handleException($e);
		}
		return $app;
	}

	//all done
	public function __destruct() {
		Log::show();
	}

}
