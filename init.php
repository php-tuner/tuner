<?php
// Copyright 2016 The PHP Tuner Authors. All rights reserved.
// Use of this source code is governed by a GPL-3.0
// license that can be found in the LICENSE file.

// 框架初始化（但是并未开始运行）

if (defined('TUNER_VERSION')) {
    // 框架已经加载，直接返回。
    return true;
} else {
    // tunner 版本号
    define('TUNER_VERSION', 1);
}

// TUNER_MODE 模式
defined('TUNER_MODE') || define('TUNER_MODE', getenv('TUNER_MODE') ? getenv('TUNER_MODE') : 'dev');

// 运行模式
define('RUN_MODEL', isset($_SERVER['REQUEST_METHOD']) || isset($_SERVER['HTTP_HOST']) ? 'CGI' : 'CLI');

$app_root_dir = dirname(realpath($_SERVER['SCRIPT_FILENAME']));

// 公开目录(此目录文件可以被外部请求访问到)
defined('APP_PUBLIC_DIR') || define('APP_PUBLIC_DIR', $app_root_dir);

// 应用程序的根目录
defined('APP_ROOT_DIR') || define('APP_ROOT_DIR', TUNER_VERSION >= 1 ? dirname($app_root_dir) : $app_root_dir);

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

// Enable display errors.
if (TUNER_MODE != 'online' || RUN_MODEL == 'CLI') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}else{
    ini_set('display_errors', 0);
}

// 类的默认加载
spl_autoload_register(_createLoader_(array(
    'controller' => array(
        __ROOT__ . DIRECTORY_SEPARATOR . 'controller', // 框架控制钱目录
    ),
    'model' => array(
        __ROOT__ . DIRECTORY_SEPARATOR . 'model', // 模型寻找目录
    ),
    'final' => array(// 其它类寻找目录
        APP_LIB_DIR,
        APP_ROOT_DIR,
        __ROOT_LIB_DIR__,
        __ROOT__
    ),
)));

// 创建加载器
function _createLoader_($dir_list)
{
    //print_r($dir_list);
    return function ($class) use ($dir_list) {
        // 限制类名仅能由字母数字组成
        if (!preg_match('/[\d\w]/i', $class)) {
            throw new Exception("class 含非法字符");
        }
        $type = 'final';
        if (preg_match('/.+(Controller|Model)$/i', $class, $match)) {
            $type = strtolower($match[1]);
            $scan_dirs = $dir_list[$type];
        }
        $scan_dirs = $dir_list[$type];
        if (!is_array($scan_dirs)) {
            $scan_dirs = array($scan_dirs);
        }
        foreach ($scan_dirs as $dir) {
            $filepath = "$dir/{$class}.php";
            // echo $filepath.PHP_EOL;
            if (file_exists($filepath)) {
                require $filepath;
                break;
            }
        }
    };
}

// composer autoload
require 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

// page cache
