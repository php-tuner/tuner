<?php
// Copyright 2016 The PHP Tuner Authors. All rights reserved.
// Use of this source code is governed by a GPL-3.0
// license that can be found in the LICENSE file.

class Db
{
    //初始化一个mysql实例
    public static function mysql($config, $dbname = '')
    {
        // TODO create a loader class.
        require_once(__ROOT_LIB_DIR__.'/mysql/MysqliDriver.php');
        static $mysql_objs = array();
        $key               = md5(json_encode(func_get_args()));
        if (!isset($mysql_objs[$key])) {
            $mysql_objs[$key] = new MysqliDriver($config, $dbname);
        }
        return $mysql_objs[$key];
    }
}
