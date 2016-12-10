<?php
// Copyright 2016 The PHP Tuner Authors. All rights reserved.
// Use of this source code is governed by a GPL-3.0
// license that can be found in the LICENSE file.

// 配置
class Config {

	// 模式
	public static $mode = 'dev';
	// 配置缓存数组
	private static $cache = array();

	// 初始化
	public function __construct() {

	}

	public static function load($filename, $ext = 'php') {
		if (isset(self::$cache[$filename])) {
			return self::$cache[$filename];
		}
		$cfg = array();
		foreach (array(__ROOT__ . '/config', APP_CONFIG_DIR, APP_CONFIG_DIR . '/' . self::$mode) as $dir) {
			$filepath = "$dir/{$filename}.$ext";
			if (file_exists($filepath)) {
				$_cfg = require $filepath;
				if(!$cfg){
					$cfg = $_cfg;
					continue;
				}
				switch (gettype($_cfg)) {
				case 'object':
					$cfg = Helper::mergeObject($cfg, $_cfg);
					break;
				case 'array':
					$cfg = array_merge($cfg, $_cfg);
					break;
				default:
					$cfg = $_cfg;
				}
			}
		}
		self::$cache[$filename] = $cfg;
		return $cfg;
	}

	//加载文件配置
	public static function __callStatic($method, $args) {
		$conf = self::load($method);
		//Todo: 配置合并
		if (count($args) > 0 && is_string($args[0])) {
			return isset($conf[$args[0]]) ? $conf[$args[0]] : null;
		}
		return $conf;
	}

	//加载文件配置
	public function __call($method, $args) {
		return self::__callStatic($method, $args);
	}

	//获取变量
	public function __get($name) {

	}
}