<?php
//模版配置
$cache_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'template_cache' . DIRECTORY_SEPARATOR . PROJECT . '_' . md5(APP_ROOT_DIR);
return array(
	'path'  => APP_ROOT_DIR . DIRECTORY_SEPARATOR . 'view',
	'cache' => $cache_dir,
);