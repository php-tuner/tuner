<?php
// 路由处理器
// 负责将URL映射到对应的处理器

class Route {

	// 处理器列表
	public static $handlers = array();

	// 添加处理器
	public static function addHandler($handler) {
		self::$handlers[] = $handler;
	}

	public static function isController($class) {
		while ($class = get_parent_class($class)) {
			if ($class == 'Controller') {
				return true;
			}
		}
		return false;
	}

	// 分发请求
	public static function dispatch($req, $res, $cfg) {
		//处理自定义路由
		foreach (Config::route() as $route_type => $routes) {
			switch ($route_type) {
			case 'uri': //通过uri定义路由
				$subject = $req->route_uri;
				break;
			}
			foreach ($routes as $pattern => $action) {
				if (preg_match($pattern, $subject, $match)) {
					if (!$action) {
						$class = ucwords($match['controller']) . "Controller";
						$action = array($class, $match['action']);
					}
					if (is_array($action)) {
						list($class, $method) = $action;
						if (self::isController($class)) {
							$action = array(new $class($req, $res, $cfg), $method);
						}
					}
					if (is_callable($action)) {
						return call_user_func_array($action, array('params' => $match));
					}
				}
			}
		}
		//分发给默认处理器
		foreach (self::$handlers as $handler) {
			$re = call_user_func_array($handler, func_get_args());
			if (false === $re) {
				return false;
			}
		}
	}

}