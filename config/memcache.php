<?php
// Copyright 2016 The PHP Tuner Authors. All rights reserved.
// Use of this source code is governed by a GPL-3.0
// license that can be found in the LICENSE file.

$__memcache_config__ = array(
	'servers' => array(
		array('127.0.0.1', 11211, 10),
	), //服务器配置列表，对memcached将作为addServers函数参数，对memcache将作为addServer的参数
);

if (class_exists('Memcached')) {
	//memcached的setOptions参数
	$__memcache_config__['options'] = array(
		//使用一致性hash
		Memcached::OPT_DISTRIBUTION => Memcached::DISTRIBUTION_CONSISTENT,
	);
}

return $__memcache_config__;