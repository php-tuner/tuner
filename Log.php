<?php

class Log {
	private static $data     = array();
	private static $is_debug = false;

	public static function init($debug = false) {
		self::$is_debug = $debug;
	}

	//记录调试信息
	public static function debug() {
		$vars = func_get_args();
		foreach ($vars as $var) {
			self::$data['debug'][] = self::getVarString($var);
		}
	}

	//获取变量的字符串表示
	public static function getVarString($var) {
		switch (true) {
		case is_object($var) && method_exists($var, '__toString'):
		case is_string($var):
			$data = strval($var) . PHP_EOL;
			break;
		default:
			$data = print_r($var, true);
		}
		return $data;
	}

	//输出
	public static function show() {
		if (!static::$is_debug) {
			//self::$data['debug'][] = "add data but not in debug mode";
			return false;
		}
		echo "<pre>Log:\n";
		foreach (self::$data as $k => $v) {
			echo "-------{$k}--------\n";
			echo implode(PHP_EOL, $v);
		}
	}

	//日志写到文件中
	public static function file($str, $dir = 'common', $rotate_type = 'day') {
		//不是绝对目录,作为子目录处理
		if (stripos($dir, '/') !== 0) {
			$base_dir = Config::site('log_dir');
			if (!$base_dir) {
                                return trigger_error('须设置日志记录目录～');
			}
			$dir = Helper::dir($base_dir, $dir);
		}
		//检查目录是否存在，若不存在则创建之
		is_dir($dir) || mkdir($dir, 0755, true);
		//滚动方式
		switch ($rotate_type) {
		case 'month':
			$filename = "Ym";
			break;
		//按小时
		case 'hour':
			$filename = date('YmdH');
			break;
		//按天
		case 'day':
			$filename = date("Ymd");
			break;
		}
		$filename .= ".txt";
		$filepath = Helper::dir($dir, $filename);
		$str      = trim($str);
		/*if (empty($str))
			{
				return false;
		*/
		$str = date("Y-m-d H:i:s\t") . $str . PHP_EOL;
		file_put_contents($filepath, $str, FILE_APPEND);
		return $str;
	}
}