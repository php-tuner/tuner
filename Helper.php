<?php
// 常用方法封装

class Helper {

	// $string： 明文 或 密文
	// $operation：DECODE表示解密,其它表示加密
	// $key： 密匙
	// $expiry：密文有效期
	public function authcode($string, $operation = 'DECODE', $key, $expiry = 0) {
		// 动态密匙长度，相同的明文会生成不同密文就是依靠动态密匙
		$ckey_length = 4;

		// 密匙
		$key = md5($key);

		// 密匙a会参与加解密
		$keya = md5(substr($key, 0, 16));
		// 密匙b会用来做数据完整性验证
		$keyb = md5(substr($key, 16, 16));
		// 密匙c用于变化生成的密文
		$keyc = $ckey_length ? ($operation == 'DECODE' ? substr($string, 0, $ckey_length) : substr(md5(microtime()), -$ckey_length)) : '';
		// 参与运算的密匙
		$cryptkey   = $keya . md5($keya . $keyc);
		$key_length = strlen($cryptkey);
		// 明文，前10位用来保存时间戳，解密时验证数据有效性，10到26位用来保存$keyb(密匙b)，解密时会通过这个密匙验证数据完整性
		// 如果是解码的话，会从第$ckey_length位开始，因为密文前$ckey_length位保存 动态密匙，以保证解密正确
		$string        = $operation == 'DECODE' ? base64_decode(substr($string, $ckey_length)) : sprintf('%010d', $expiry ? $expiry + time() : 0) . substr(md5($string . $keyb), 0, 16) . $string;
		$string_length = strlen($string);
		$result        = '';
		$box           = range(0, 255);
		$rndkey        = array();
		// 产生密匙簿
		for ($i = 0; $i <= 255; $i++) {
			$rndkey[$i] = ord($cryptkey[$i % $key_length]);
		}
		// 用固定的算法，打乱密匙簿，增加随机性，好像很复杂，实际上对并不会增加密文的强度
		for ($j = $i = 0; $i < 256; $i++) {
			$j       = ($j + $box[$i] + $rndkey[$i]) % 256;
			$tmp     = $box[$i];
			$box[$i] = $box[$j];
			$box[$j] = $tmp;
		}
		// 核心加解密部分
		for ($a = $j = $i = 0; $i < $string_length; $i++) {
			$a       = ($a + 1) % 256;
			$j       = ($j + $box[$a]) % 256;
			$tmp     = $box[$a];
			$box[$a] = $box[$j];
			$box[$j] = $tmp;
			// 从密匙簿得出密匙进行异或，再转成字符
			$result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));
		}
		if ($operation == 'DECODE') {
			// substr($result, 0, 10) == 0 验证数据有效性
			// substr($result, 0, 10) - time() > 0 验证数据有效性
			// substr($result, 10, 16) == substr(md5(substr($result, 26).$keyb), 0, 16) 验证数据完整性
			// 验证数据有效性，请看未加密明文的格式
			if ((substr($result, 0, 10) == 0 || substr($result, 0, 10) - time() > 0) && substr($result, 10, 16) == substr(md5(substr($result, 26) . $keyb), 0, 16)) {
				return substr($result, 26);
			} else {
				return '';
			}
		} else {
			// 把动态密匙保存在密文里，这也是为什么同样的明文，生产不同密文后能解密的原因
			// 因为加密后的密文可能是一些特殊字符，复制过程可能会丢失，所以用base64编码
			return $keyc . str_replace('=', '', base64_encode($result));
		}
	}

	public static function getMemInfo() {
		$data    = explode("\n", file_get_contents("/proc/meminfo"));
		$meminfo = array();
		foreach ($data as $line) {
			list($key, $val) = explode(":", $line);
			$meminfo[$key]   = trim($val);
		}
		return $meminfo;
	}

	// 创建兼容平台的目录结构
	public static function dir() {
		$dirs = func_get_args();
		return rtrim(implode(DIRECTORY_SEPARATOR, $dirs), DIRECTORY_SEPARATOR);
	}

	// 添加自动包含路径
	public static function addIncludePath() {
		$old_path_str = get_include_path();
		$add_path     = func_get_args();
		$new_path     = array_merge(explode(PATH_SEPARATOR, $old_path_str), $add_path);
		$new_path_str = implode(PATH_SEPARATOR, array_unique($new_path));
		return set_include_path($new_path_str);
	}

	//检查IP是否是内网地址
	public static function isInternalIP($ip_str) {
		$ip_str = trim($ip_str);
		$ip     = explode('.', $ip_str);
		return in_array("{$ip[0]}", array('10', '172', '127'));
	}

	/**
	 * @file
	 * Defines a function wrapper for HTML Purifier for quick use.
	 * @note ''HTMLPurifier()'' is NOT the same as ''new HTMLPurifier()''
	 */

	/**
	 * Purify HTML.
	 * @param string $html String HTML to purify
	 * @param mixed $config Configuration to use, can be any value accepted by
	 *        HTMLPurifier_Config::create()
	 * @return string
	 */
	function HTMLPurifier($html, $config = null) {
		static $purifier = false;
		if (!$purifier) {
			$purifier = new HTMLPurifier();
		}
		return $purifier->purify($html, $config);
	}

	//合并对象
	public static function mergeObject($obj, $obj2) {
		if (!is_object($obj2)) {
			throw new Exception('obj2 must be object');
		}
		if (!is_object($obj)) {
			return $obj2;
		}
		foreach (get_object_vars($obj2) as $name) {
			switch (gettype($obj2->$name)) {
			case 'object':
				$obj->$name = self::mergeObject($obj->$name, $obj2->$name);
				break;
			case 'array':
				$obj->$name = array_merge($obj->$name, $obj2->$name);
				break;
			default:
				$obj->$name = $obj2->$name;
			}
		}
		return $obj;
	}

}