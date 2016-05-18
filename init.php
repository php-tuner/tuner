<?php
// 框架初始化（但是并未开始运行）

// 应用程序的根目录
defined('APP_ROOT_DIR') || define('APP_ROOT_DIR', dirname($_SERVER['SCRIPT_FILENAME']));

// 应用程序类库目录
defined('APP_LIB_DIR') || define('APP_LIB_DIR', APP_ROOT_DIR . DIRECTORY_SEPARATOR . 'lib');

// 应用程序控制器目录
defined('APP_CONTROLLER_DIR') || define('APP_CONTROLLER_DIR', APP_ROOT_DIR . DIRECTORY_SEPARATOR . 'controller');

// 应用程序数据模型目录
defined('APP_MODEL_DIR') || define('APP_MODEL_DIR', APP_ROOT_DIR . DIRECTORY_SEPARATOR . 'model');

// 应用程序配置目录
defined("APP_CONFIG_DIR") || define('APP_CONFIG_DIR', APP_ROOT_DIR . DIRECTORY_SEPARATOR . 'config');

// 项目名称
defined('PROJECT') || define('PROJECT', pathinfo(APP_ROOT_DIR, PATHINFO_FILENAME));

// 框架根目录
define('__ROOT__', __DIR__);

// 框架类库目录
define('__ROOT_LIB_DIR__', __ROOT__ . DIRECTORY_SEPARATOR . 'lib');

// 驼峰命名分隔符
defined('CAMEL_CLASS_SEP') || define('CAMEL_CLASS_SEP', '_');

// 设定时区，统一时间
date_default_timezone_set('Etc/GMT-8');

// 基于 socket 的流的默认超时时间(秒)
ini_set('default_socket_timeout', 30);

// 类的默认加载
spl_autoload_register(_createLoader_(
	array(APP_CONTROLLER_DIR, __ROOT__ . "/controller"), // 控制器寻找目录
	array(APP_MODEL_DIR, __ROOT__ . "/model"), // 模型寻找目录
	array(APP_LIB_DIR, APP_ROOT_DIR, __ROOT_LIB_DIR__, __ROOT__) // 其它类寻找目录
));

// 创建加载器
function _createLoader_($controller_dir, $model_dir, $final_dir) {
	return function ($class) use ($controller_dir, $model_dir, $final_dir) {
		// 限制类名仅能由字母数字组成
		if (!preg_match('/[\d\w]/i', $class)) {
			throw new Exception("class 含非法字符");
		}
		switch (true) {
		case preg_match('/[\d\w]+Controller$/i', $class):
			$scan_dirs = $controller_dir;
			// require APP_CONTROLLER_DIR."/{$class}.php";
			break;
		case preg_match('/[\d\w]+Model$/i', $class):
			$scan_dirs = $model_dir;
			// require APP_MODEL_DIR."/{$class}.php";
			break;
		default:
			$scan_dirs = $final_dir;

		}
		if (!is_array($scan_dirs)) {
			$scan_dirs = array($scan_dirs);
		}
		foreach ($scan_dirs as $dir) {
			$filepath = "$dir/{$class}.php";
			//echo $filepath.PHP_EOL;
			if (file_exists($filepath)) {
				require $filepath;
				break;
			}
		}
	};
}

if (!function_exists('mysql_escape_string')) {
	function mysql_escape_string($v) {
		$search = array("\\", "\x00", "\n", "\r", "'", '"', "\x1a");
		$replace = array("\\\\", "\\0", "\\n", "\\r", "\'", '\"', "\\Z");
		return str_replace($search, $replace, $v);
	}
}

// composer autoload
require 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
