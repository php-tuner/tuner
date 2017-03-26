<?php
// Copyright 2016 The PHP Tuner Authors. All rights reserved.
// Use of this source code is governed by a GPL-3.0
// license that can be found in the LICENSE file.

//模版配置
$cache_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'template_cache' . DIRECTORY_SEPARATOR . PROJECT . '_' . md5(APP_ROOT_DIR);
$path = APP_ROOT_DIR . DIRECTORY_SEPARATOR . 'view';
return array(
	'path' => file_exists($path) ? $path : NULL,
	'cache' => $cache_dir,
);