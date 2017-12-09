<?php
// Copyright 2016 The PHP Tuner Authors. All rights reserved.
// Use of this source code is governed by a GPL-3.0
// license that can be found in the LICENSE file.

/**
 *
 * 缓存
 *
 */
class Cache
{

    //获取默认的缓存
    public static function getDefault()
    {
        $cache_handler = ucwords(strtolower(Config::common('cache')));
        $servers       = Config::memcache('servers');
        switch (true) {
            case ($cache_handler == 'Auto' && class_exists('Memcached')) || $cache_handler == 'Memcached':
                return self::memcached($servers['cache']);
            break;
            case ($cache_handler == 'Auto' && class_exists('Memcache')) || $cache_handler == 'Memcache':
                return self::memcache($servers['cache']);
            break;
        }
        throw new Exception("没有找到默认的cache");
    }

    public static function getMetaKey($key)
    {
        return "__META__" . md5($key);
    }

    //缓存函数执行结果
    public static function call($callback, $params, $cache_time = 300)
    {
        $cache_key  = md5(serialize($callback) . serialize($params));
        $cache_data = self::getData($cache_key);
        if ($cache_data) {
            return $cache_data;
        }
        $re = call_user_func_array($callback, $params);
        Cache::setData($cache_key, $re, $cache_time);
        return $re;
    }

    //设置缓存数据(防雪崩)
    public static function setData($key, $value, $expire_time = 300)
    {
        $now_time         = time();
        $fake_expire_time = $now_time + $expire_time * 2;
        $meta_key         = self::getMetaKey($key);
        $meta_data        = array(
            'real_expire_time' => $now_time + $expire_time,
            'status'           => 'cache',
        );
        //echo $fake_expire_time;
        self::set($meta_key, $meta_data, $fake_expire_time);
        return self::set($key, $value, $fake_expire_time);
    }

    //缓存数据(防雪崩)
    public static function getData($key)
    {
        $meta_key  = self::getMetaKey($key);
        $meta_data = self::get($meta_key);
        $now_time  = time();
        //需要加锁
        if (is_array($meta_data) && $meta_data['real_expire_time'] < $now_time && $meta_data['status'] == 'cache') {
            $meta_data['status'] = 'fresh';
            self::set($meta_key, $meta_data); //shold be never expire
            return false;
        }
        return self::get($key);
    }

    //设置缓存数据
    public static function set($key, $value, $expire_time = 300)
    {
        $cache = self::getDefault();
        switch (get_class($cache)) {
            case 'Redis':
            case 'Memcached':
                return $cache->set($key, $value, $expire_time);
            break;
            case "Memcache":
                return $cache->set($key, $value, MEMCACHE_COMPRESSED, $expire_time);
            break;
        }
        throw new Exception("没有可用的缓存服务");
    }

    //获取缓存数据
    public static function get($key)
    {
        $cache = self::getDefault();
        switch (get_class($cache)) {
            case 'Redis':
            case 'Memcached':
                return $cache->get($key);
            break;
            case "Memcache":
                return $cache->get($key, MEMCACHE_COMPRESSED);
            break;
        }
        return false;
    }

    //memcached
    public static function memcached($servers = array())
    {
        static $mcd_list = array();
        if (!$servers) {
            throw new Exception("未配置memcache服务器列表");
        }
        if (!class_exists('Memcached')) {
            throw new Exception("Memcached not found");
        }
        $key = md5(serialize($servers));
        if (!isset($mcd_list[$key])) {
            $mcd = new Memcached();
            $re  = $mcd->addServers($servers);
            isset($config['options']) && $mcd->setOptions($config['options']);
            $mcd_list[$key] = $mcd;
        }
        return $mcd_list[$key];
    }

    //memcache
    public static function memcache($servers = array())
    {
        static $mc_list = array();
        if (!$servers) {
            throw new Exception("未配置memcache服务器列表");
        }
        if (!class_exists('Memcache')) {
            throw new Exception("Memcache not found");
        }
        $key = md5(serialize($servers));
        if (!isset($mc_list[$key])) {
            $mc = new Memcache();
            foreach ($servers as $server) {
                $re = call_user_func_array(array($mc, 'addServer'), $server); //$mcd->addServer($servers);
            }
            $mc_list[$key] = $mc;
        }
        return $mc_list[$key];
    }
}
