<?php
// Copyright 2016 The PHP Tuner Authors. All rights reserved.
// Use of this source code is governed by a GPL-3.0
// license that can be found in the LICENSE file.

class Session
{

    // 启动session
    public static function start()
    {
        if (!isset($_SESSION)) {
            session_start();
        }
    }

    // 更新 session
    public static function mergeUpdate($key, $value)
    {
        self::start();
        $old_value = $_SESSION[$key];
        if (is_array($old_value) && is_array($value) && $old_value != $value) {
            $value = array_merge_recursive($old_value, $value);
        }
        return $_SESSION[$key] = $value;
    }

    // 设置session
    public static function set($key, $value)
    {
        self::start();
        return $_SESSION[$key] = $value;
    }

    // 获取session值
    public static function get($key)
    {
        self::start();
        return isset($_SESSION[$key]) ? $_SESSION[$key] : null;
    }

    //获取之后删除
    public static function getOnce($key)
    {
        self::start();
        if (isset($_SESSION[$key])) {
            $val = $_SESSION[$key];
            unset($_SESSION[$key]);
            return $val;
        }
        return null;
    }
    
    // 设置session cookie 超时时间
    // http://php.net/session_set_cookie_params comment by final dot wharf at gmail dot com
    public static function setExpire($lifetime = 0)
    {
        $params = session_get_cookie_params();
        session_set_cookie_params($lifetime, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        //session_regenerate_id(true);
        //setcookie(session_name(), session_id(), time() + $lifetime);
        //throw new Exception('====='.print_r(session_get_cookie_params(), true), 2);
    }
}
