<?php
// Copyright 2016 The PHP Tuner Authors. All rights reserved.
// Use of this source code is governed by a GPL-3.0
// license that can be found in the LICENSE file.

// HTTP 请求
class Request {

	// Header头
	public $header = array();
	// 请求方法
	public $method = '';
	// 请求时间
	public $time = 0;
	// URI
	public $uri = '';
	// route URI
	public $route_uri = '';
	// base url
	public $base_url = '';
	// 要求的数据格式
	public $format = '';
	// 客户端IP
	public $client_ip = '';
	// 是否ajax请求
	public $is_ajax = false;

	// 构造方法
	public function __construct() {
		$this->header    = $this->getHeader();
		$this->method    = trim($_SERVER['REQUEST_METHOD']);
		$this->time      = intval($_SERVER['REQUEST_TIME']);
		$this->uri       = trim($_SERVER['REQUEST_URI']);
		$this->format    = $this->getFormat();
		$this->client_ip = static::getClientIp();
		$this->is_ajax   = static::isAjax();
		// 使用PATH_INFO路由
		$this->route_uri = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : $this->uri;
		// 将多个／替换成一个
		$this->route_uri = preg_replace('#/{1,}#', '/', $this->route_uri);
		$is_https       = isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '443';
		$this->base_url  = $is_https ? "https" : 'http';
		isset($this->header['Host']) && $this->base_url .= "://{$this->header['Host']}";
		if ($this->route_uri) {
			$base_pos = stripos($_SERVER['REQUEST_URI'], $this->route_uri);
			$this->base_url .= parse_url(substr($_SERVER['REQUEST_URI'], 0, $base_pos), PHP_URL_PATH);
		} else {
			$this->base_url .= $_SERVER['REQUEST_URI'];
		}
	}

	public static function __callStatic($method, $args) {
		static $obj = null;
		if ($obj == null) {
			$obj = new self();
		}
		return call_user_func_array(array($obj, $method), $args);
	}

	//获取原始的请求体
	public static function getRawBody() {
		$body = null;
		if ($_SERVER['REQUEST_METHOD'] != 'POST') {
			//echo "{$_SERVER['REQUEST_METHOD']} != 'POST'";
			return $body;
		}
		$content_type = $_SERVER['CONTENT_TYPE'];
		switch (true) {
		case 'application/x-www-form-urlencoded' == $content_type:
			$body = file_get_contents("php://input");
			break;
		case preg_match('#^multipart/form-data; boundary=(.*)#', $content_type, $match):
			//rebuild it according $_POST, $_FILE
			$body = self::buildMultipartFormData($_POST, $_FILES, $match[1]);
			break;
		default:
			//throw new Exception('expected');
		}
		return $body;
	}

	//构建请求
	public static function buildMultipartFormData($assoc, $files, &$boundary = '') {
		// invalid characters for "name" and "filename"
		static $disallow = array("\0", "\"", "\r", "\n");
		$body            = array();
		// build normal parameters
		foreach ($assoc as $k => $v) {
			$k      = str_replace($disallow, "_", $k);
			$body[] = implode("\r\n", array(
				"Content-Disposition: form-data; name=\"{$k}\"",
				"",
				filter_var($v),
			));
		}
		// build file parameters
		foreach ($files as $k => $v) {
			if (is_array($v)) {
				$filepath = $v['tmp_name'];
				$filename = $v['name'];
				$type     = $v['type'];
			} else {
				$type     = 'application/octet-stream';
				$filepath = $filepath = $v;
			}
			switch (true) {
			case false === $filepath = realpath(filter_var($filepath)):
			case !is_file($filepath):
			case !is_readable($filepath):
				continue; // or return false, throw new InvalidArgumentException
			}
			$data     = file_get_contents($filepath);
			$filename = call_user_func("end", explode(DIRECTORY_SEPARATOR, $filename));
			$k        = str_replace($disallow, "_", $k);
			$filename = str_replace($disallow, "_", $filename);
			$body[]   = implode("\r\n", array(
				"Content-Disposition: form-data; name=\"{$k}\"; filename=\"{$filename}\"",
				"Content-Type: $type",
				"",
				$data,
			));
		}

		// generate safe boundary
		if (!$boundary) {
			do {
				$boundary = "---------------------" . md5(mt_rand() . microtime());
			} while (preg_grep("/{$boundary}/", $body));
		}

		// add boundary for each parameters
		array_walk($body, function (&$part) use ($boundary) {
			$part = "--{$boundary}\r\n{$part}";
		});

		// add final boundary
		$body[] = "--{$boundary}--";
		$body[] = ""; //append last \r\n
		return implode("\r\n", $body);
	}

	//判断是否是ajax请求
	public static function isAjax() {
		if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
			return true;
		}
	}

	//获取当前URL
	public function getCurrentUrl($host = true) {
		if ($host) {
			return "http://{$this->header['Host']}{$this->uri}";
		} else {
			return $this->uri;
		}
	}

	//获取客户端IP
	public static function getClientIp() {
		if ($_SERVER) {
			if (isset($_SERVER["HTTP_X_FORWARDED_FOR"])) {
				return $_SERVER["HTTP_X_FORWARDED_FOR"];
			} else if (isset($_SERVER["HTTP_CLIENT_IP"])) {
				return $_SERVER["HTTP_CLIENT_IP"];
			} else if (isset($_SERVER["REMOTE_ADDR"])) {
				return $_SERVER["REMOTE_ADDR"];
			} else {
				return 'no client ip';
			}
		} else {
			if (getenv('HTTP_X_FORWARDED_FOR')) {
				return getenv('HTTP_X_FORWARDED_FOR');
			} else if (getenv('HTTP_CLIENT_IP')) {
				return getenv('HTTP_CLIENT_IP');
			} else if (getenv('REMOTE_ADDR')) {
				return getenv('REMOTE_ADDR');
			} else {
				return 'no client ip';
			}
		}
	}

	// 获取所有的HEADER
	public static function getHeader($key = '') {
		$headers    = array();
		$extra_keys = array(
			'CONTENT_TYPE',
			'CONTENT_LENGTH',
		);
		foreach ($_SERVER as $name => $value) {
			if (substr($name, 0, 5) == 'HTTP_' || in_array($name, $extra_keys)) {
				$_key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
				if (!$key) {
					$headers[$_key] = $value;
				} elseif ($_key == $key) {
					return $value;
				}
			}
		}
		return $key ? '' : $headers;
	}

	// 获取输出格式
	private function getFormat() {
		//force_format
		$format = $this->get('_format');
		if (!$format) {
			$path   = parse_url($this->uri, PHP_URL_PATH);
			$format = pathinfo($path, PATHINFO_EXTENSION);
		}
		return $format ? strtolower($format) : 'html';
	}

	//获取Get数据
	public function get($key = null) {
		if (empty($key)) {
			return $_GET;
		}
		return isset($_GET[$key]) ? $_GET[$key] : null;
	}

	//获取POST数据
	public function post($key = null, $check_method = true) {
		if ($check_method && $this->method != 'POST') {
			throw new Exception("非POST请求方法");
		}
		if (empty($key)) {
			return $_POST;
		}
		return isset($_POST[$key]) ? $_POST[$key] : null;
	}

	//获取COOKIE数据
	public function cookie($key = null) {
		if (empty($key)) {
			return $_COOKIE;
		}
		return isset($_COOKIE[$key]) ? $_COOKIE[$key] : null;
	}

	//按$_GET, $_POST, $_COOKIE 顺序获取值
	public function gpc($key = null) {
		if (empty($key)) {
			return $_REQUEST;
		}
		foreach (array($_GET, $_POST, $_COOKIE) as $params) {
			if (isset($params[$key])) {
				return $params[$key];
			}
		}
		return null;
	}

	// 判断url是不是当前请求
	public function from($url, $strict_level = 1){
		$url = trim($url);
		if(!$url){
			return false;
		}
		$url_info2 = parse_url($url);
		if(!is_array($url_info2)){
			return false;
		}
		$url_info1 = parse_url($this->getCurrentUrl());
		$compare_keys = array(
			0 => array( // 松散模式
				'path',
			),
			1 => array( // 正常模式
				'host', 'port', 'path',
			),
			2 => array( // 严谨模式
				'host', 'port', 'path', 'query',
			),
			3 => array( // 严格模式
				'scheme', 'host', 'port', 'user', 'pass', 'path', 'query', 'fragment',
			),
		);
		if(!isset($compare_keys[$strict_level])){
			return false;
		}
		foreach($compare_keys[$strict_level] as $key){
			if(!isset($url_info1[$key]) || !isset($url_info2[$key])){
				return false;
			}
			if($url_info1[$key] != $url_info2[$key]){
				return false;
			}
		}
		return true;
	}

}