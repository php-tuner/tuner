<?php
/**
 * HTTP 常用操作
 *
 * @package default
 * @author Heng Min Zhan
 */
class Http {
	// 默认User-Agent
	public static $UA = "HTTP CLIENT(PHP)";

	// 获取处理器
	public static function getHandler() {
		if (function_exists('curl_init')) {
			return 'curlRequest';
		}
		return 'socketRequest';
	}

	// 构建header, 最终形式
	// array("Content-type: text/html", "Key: Value");
	public static function buildHeader($headers) {
		$result = array();
		foreach ($headers as $key => $val) {
			if (!is_int($key)) {
				$val = "{$key}: {$val}";
			}
			$result[] = str_replace("\r\n", '', $val);
		}
		return $result;
	}

	// 生成规范化的key: Content-Type
	public static function canonicalHeaderKey($key) {
		$len    = strlen($key);
		$uppper = true;
		for ($i = 0; $i < $len; $i++) {
			if ($uppper) {
				$key[$i] = strtoupper($key[$i]);
			}
			$uppper = $key[$i] == '-' ? true : false;
		}
		return $key;
	}

	// 解析header头
	public static function parseHeaders($raw_headers) {
		$headers = array();
		$key     = '';
		foreach (explode("\n", $raw_headers) as $i => $h) {
			$h = explode(':', $h, 2);
			if (isset($h[1])) {
				//record it
				$key = self::canonicalHeaderKey($h[0]);
				if (!isset($headers[$key])) {
					$headers[$key] = trim($h[1]);
				} elseif (is_array($headers[$h[0]])) {
					$headers[$key] = array_merge($headers[$key], array(trim($h[1]))); // [+]
				} else {
					$headers[$key] = array_merge(array($headers[$key]), array(trim($h[1])));
				}
			} else {
				if (substr($h[0], 0, 1) == "\t") {
					$headers[$key] .= "\r\n\t" . trim($h[0]);
				} elseif (!$key) {
					$headers[0] = trim($h[0]);
				}
			}
		}
		return $headers;
	}

	// GET请求
	public static function get($url, $headers = array(), $connect_timeout = 1, $read_timeout = 1) {
		$handler = self::getHandler();
		return self::$handler($url, null, $headers, true, $connect_timeout, $read_timeout);
	}

	// POST请求
	public static function post($url, $params = array(), $headers = array(), $connect_timeout = 1, $read_timeout = 1) {
		$handler = self::getHandler();
		return self::$handler($url, $params, $headers, true, $connect_timeout, $read_timeout);
	}

	/**
	 *
	 * ASYNC REQUEST
	 *
	 */
	public static function asyncRequest($url, $params = array()) {
		if (function_exists('exec')) {
			if ($params) {
				$params_string = http_build_query($params, '', '&');
				$params        = " -d '$params_string' ";
			} else {
				$params = '';
			}
			$curl_cmd = "curl -s '$url' $params > /dev/null 2>&1 &";
			return exec($curl_cmd);
		}
		return self::socketRequest($url, $params, false);
	}

	/**
	 * SOCKET 请求
	 *
	 * @return void
	 * @author Heng Min Zhan
	 */
	public static function socketRequest($url, $params = array(), $headers = array(), $wait_result = true, $connect_timeout = 1, $read_timeout = 3, $max_redirect = 5) {
		$crlf   = "\r\n";
		$method = 'GET';
		if ($params) {
			$method = 'POST';
		}
		$parts = parse_url($url);
		if (!isset($parts['path'])) {
			$parts['path'] = '/';
		}
		if (!isset($headers['User-Agent'])) {
			$headers['User-Agent'] = self::$UA;
		}
		$out   = array();
		$out[] = "$method {$parts['path']}?{$parts['query']} HTTP/1.1";
		$out[] = "Host: {$parts['host']}";
		$out[] = "Connection: Close";

		if (function_exists('gzinflate')) {
			$headers["Accept-Encoding"] = "gzip,deflate";
		}
		$post_string = '';
		if ($method == 'POST') {
			if (is_array($params)) {
				$headers['Content-Type'] = "application/x-www-form-urlencoded";
				$post_string             = http_build_query($params, '', '&');
			} else {
				$post_string = trim($params);
			}
			//must have it
			$headers['Content-Length'] = strlen($post_string);
		}
		if ($headers) {
			$out = array_merge($out, self::buildHeader($headers));
		}
		//header end
		$out[] = '';
		$out[] = $post_string;
		if (!isset($parts['port'])) {
			$parts['port'] = $parts['scheme'] == 'https' ? 443 : 80;
		}
		if ($parts['scheme'] == 'https') {
			$hostname = "ssl://{$parts['host']}";
		} else {
			$hostname = $parts['host'];
		}
		$fp = fsockopen($hostname, $parts['port'], $errno, $errstr, $connect_timeout);
		if (!$fp) {
			return new HttpResponse('', '', '', new Exception("url: $url, error: $errstr", $errno));
		}
		$string  = implode($crlf, $out);
		$str_len = strlen($string);
		for ($written = 0, $fwrite = 0; $written < $str_len; $written += $fwrite) {
			$fwrite = fwrite($fp, substr($string, $written));
			if ($fwrite === false) {
				break;
			}
		}
		if ($fwrite != $str_len) {
			fclose($fp);
			return new HttpResponse('', '', '', new Exception("url: $url, error: $fwrite, $str_len "));
		}
		//fwrite($fp, implode($crlf, $out));
		//repsonse header
		$headers     = array();
		$body        = '';
		$http_status = array();
		if ($wait_result) {
			stream_set_timeout($fp, $read_timeout);
			//read and parse header
			list($http_status['version'], $http_status['code'], $http_status['desc']) = explode(' ', trim(fgets($fp, 256)));
			$header_lines                                                             = '';
			while (!feof($fp)) {
				$line = fgets($fp, 256);
				if (!trim($line)) {
					break;
				}
				$header_lines .= $line;
				//list($key, $val) = explode(':', $line, 2);
				//$headers[$key] = trim($val);
			}
			$headers = self::parseHeaders($header_lines);
			//read body
			//分块传输编码只在HTTP协议1.1版本（HTTP/1.1）中提供
			if (isset($headers['Transfer-Encoding']) && $headers['Transfer-Encoding'] == 'chunked') {
				while (!feof($fp)) {
					$line = fgets($fp, 1024);
					if ($line && preg_match('/^([0-9a-f]+)/i', $line, $matches)) {
						$len = hexdec($matches[1]);
						if ($len == 0) {
							break; //maybe have some other header
						}
						while ($len > 0) {
							$tmp_data = fread($fp, $len);
							$body .= $tmp_data;
							$len = $len - strlen($tmp_data);
						};
					}
				}
			} else if (isset($headers['Content-Length']) && $len = $headers['Content-Length']) {
				while ($len > 0) {
					$tmp_data = fread($fp, $len);
					$body .= $tmp_data;
					$len = $len - strlen($tmp_data);
				};
			} else {
				while (!feof($fp)) {
					$body .= fread($fp, 1024 * 8);
				}
			}
			if ($body && isset($headers['Content-Encoding']) && $headers['Content-Encoding'] == 'gzip') {
				$body = gzinflate(substr($body, 10));
			}
		}
		$info = stream_get_meta_data($fp);
		fclose($fp);
		if ($info['timed_out']) {
			return new HttpResponse($http_status, $headers, $body, new Exception("Connection timed out!"));
		}
		// 重定向处理
		if (substr($http_status['code'], 0, 1) == 3 && $max_redirect > 0 && isset($headers['Location']) && filter_var($headers['Location'], FILTER_VALIDATE_URL)) {
			return self::socketRequest($headers['Location'], $params = array(), $headers = array(), $wait_result, $connect_timeout, $read_timeout, --$max_redirect);
		}
		//$uri = $info['uri'];
		//$result = array('http_status' => $http_status, 'header' => $headers, 'body' => $body);
		return new HttpResponse($http_status, $headers, $body);
	}

	/**
	 *
	 * CURL REQUEST
	 *
	 */
	public static function curlRequest($url, $params = array(), $headers = array(), $wait_result = true, $connect_timeout = 1, $read_timeout = 3, $max_redirect = 5) {
		$re = self::multiCurl(array(
			array(
				'url'     => $url,
				'params'  => $params,
				'headers' => $headers,
			),
		), true, $connect_timeout, $read_timeout, $max_redirect);
		//$result = is_array($re) && isset($re[$url]) ? $re[$url]['result'] : '';
		return current($re);
	}

	public static function getCurlInstance($url, $connect_timeout = 1, $read_timeout = 3, $max_redirect = 2) {
		$ch = curl_init($url);
		if (!is_resource($ch)) {
			return false;
		}
		//bool
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HEADER, 1);
		//curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		//integer
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, $connect_timeout * 1000);
		curl_setopt($ch, CURLOPT_TIMEOUT_MS, ($read_timeout + $connect_timeout) * 1000);
		curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
		if ($max_redirect >= 0) {
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
			curl_setopt($ch, CURLOPT_AUTOREFERER, true);
			curl_setopt($ch, CURLOPT_MAXREDIRS, $max_redirect);
		}
		return $ch;
	}

	/*
		* @purpose: 使用curl并行处理url
		* @return: array 每个url获取的数据
		* @param: $urls array url列表
		* @param: $callback string 需要进行内容处理的回调函数。示例：func(array)
	*/
	public static function multiCurl($request_list, $callback = '', $connect_timeout = 1, $read_timeout = 3, $max_redirect = 2) {
		$response = array();
		if (empty($request_list)) {
			return array(new HttpResponse('', '', '', new Exception("无效请求(request_list empty)")));
		}
		$chs = curl_multi_init();
		//使用HTTP长连接(启用后用时反而会增长！)
		/*if (function_exists("curl_multi_setopt")) {
			curl_multi_setopt($chs, CURLMOPT_PIPELINING, 1);
		}*/
		$curl_list = array();
		foreach ($request_list as $req) {
			list($url, $params, $headers) = array_values($req);
			$ch                           = self::getCurlInstance($url, $connect_timeout, $read_timeout, $max_redirect);
			//disable expect header, some server not surpport it
			$headers[] = 'Expect:';
			curl_setopt($ch, CURLOPT_HTTPHEADER, self::buildHeader($headers));
			curl_setopt($ch, CURLOPT_USERAGENT, self::$UA);
			//curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			//禁用 @ 前缀在 CURLOPT_POSTFIELDS 中发送文件(php >= 5.5.0)
			if (defined('CURLOPT_SAFE_UPLOAD')) {
				curl_setopt($ch, CURLOPT_SAFE_UPLOAD, true);
			}
			$force_urlencoded = true;
			//没有上传文件是强制 application/x-www-form-urlencoded 编码
			if (class_exists('CURLFile') && is_array($params)) {
				foreach ($params as $_v) {
					if ($_v instanceof CURLFile) {
						$force_urlencoded = false;
						break;
					}
				}
			}
			if ($force_urlencoded && !is_string($params) && $params) {
				//如果有子段是@开头, php curl 会解析成需要上传文件，而且如果没有严格的用户输入过滤，可能会带来安全问题。
				//所以我们转换成字符串，禁止用@方式上传文件。
				$params = http_build_query((array) $params, '', '&');
			}
			if ($params) {
				curl_setopt($ch, CURLOPT_POST, true);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
			}
			curl_multi_add_handle($chs, $ch);
			$curl_list[] = $ch;
		}
		//$callback = trim($callback);
		do {
			$status = curl_multi_exec($chs, $active);
			//Solve CPU 100% usage, a more simple and right way:
			curl_multi_select($chs); //default timeout 1.
		} while ($status === CURLM_CALL_MULTI_PERFORM || $active);
		if ($callback && $status == CURLM_OK) {
			while ($done = curl_multi_info_read($chs)) {
				//http://php.net/curl_getinfo
				$info  = curl_getinfo($done["handle"]);
				$error = curl_error($done["handle"]);
				//wrong may be still have body data
				$result = curl_multi_getcontent($done["handle"]);
				$body   = $info['header_size'] && $result ? substr($result, $info['header_size']) : null;
				if ($error || !in_array($info['http_code'], array(200))) {
					$rtn = new HttpResponse('', '', $body, new Exception("url:{$info['url']}, error:$error, info:" . print_r($info, true)));
					//throw new Exception($error);
				} else {
					$header_lines = substr($result, 0, $info['header_size']);
					$http_status  = array();
					$headers      = self::parseHeaders($header_lines);
					//it must have set key 0
					list($http_status['version'], $http_status['code'], $http_status['desc']) = explode(' ', $headers[0]);
					$rtn                                                                      = new HttpResponse($http_status, $headers, $body); //compact('info', 'error', 'result');
				}
				if (is_callable($callback)) {
					$callback($rtn);
				} else {
					$response[] = $rtn;
				}
			}
		}
		//remove and close all sub curl instanc
		foreach ($curl_list as $ch) {
			curl_multi_remove_handle($chs, $ch);
			curl_close($ch);
		}
		curl_multi_close($chs);
		return $response;
	}
}

class HttpResponse {

	public $status = array();
	public $header = array();
	public $body   = '';
	public $error  = null;

	public function __construct($status, $header, $body, $error = null) {
		$this->status = $status;
		$this->header = $header;
		$this->body   = $body;
		$this->error  = $error;
		if ($error != null) {
			Log::file($this->error(), 'http_request');
		}
	}

	public function error() {
		if (get_class($this->error) == 'Exception') {
			return $this->error->getMessage();
		}
		//maybe wrong
		return @strval($this->error);
	}

	public function json() {
		return json_decode($this->body, true);
	}

	public function raw() {
		return $this->body;
	}

	public function _toString() {
		return $this->body;
	}
}
