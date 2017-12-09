<?php
// Copyright 2016 The PHP Tuner Authors. All rights reserved.
// Use of this source code is governed by a GPL-3.0
// license that can be found in the LICENSE file.

// 配置
class Config
{

    // 模式(已废弃不要再使用)
    public static $mode = 'dev';
    
    // 配置缓存数组
    private static $cache = array();

    // 初始化
    public function __construct()
    {
    }

    public static function load($filename, $ext = 'php')
    {
        if (isset(self::$cache[$filename])) {
            return self::$cache[$filename];
        }
        $cfg = array();
        $cfg_dirs = array(__ROOT__ . '/config', APP_CONFIG_DIR);
        if (TUNER_MODE) {
            $cfg_dirs[] = APP_CONFIG_DIR . '/' . TUNER_MODE;
        }
        foreach ($cfg_dirs as $dir) {
            $filepath = "$dir/{$filename}.$ext";
            if (file_exists($filepath)) {
                $_cfg = require $filepath;
                if (!$cfg) {
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

    // 更新运行时配置
    public static function update($filename, $new_cfg)
    {
        $filename = trim($filename);
        $cfg = self::load($filename);
        switch (gettype($new_cfg)) {
            case 'object':
                $cfg = Helper::mergeObject($cfg, $new_cfg);
                break;
            case 'array':
                $cfg = array_merge($cfg, $new_cfg);
                break;
            default:
                $cfg = $new_cfg;
        }
        self::$cache[$filename] = $cfg;
        return $cfg;
    }

    // 获取配置信息
    public static function __callStatic($method, $args)
    {
        $conf = self::load($method);
        if (count($args) == 0) {
            return $conf;
        }
        $result = array();
        foreach ($args as $arg) {
            $val = null;
            if (isset($conf[$arg])) {
                $val = $conf[$arg];
            } elseif (strpos($arg, '.') !== false) {
                $val = $conf;
                foreach (explode('.', $arg) as $key) {
                    if (!isset($val[$key])) {
                        $val = null;
                        break;
                    }
                    $val = $val[$key];
                }
            }
            $result[$arg] = $val;
        }
        return count($result) == 1 ? current($result) : $result;
    }

    //加载文件配置
    public function __call($method, $args)
    {
        return self::__callStatic($method, $args);
    }

    //获取变量
    public function __get($name)
    {
    }
}
