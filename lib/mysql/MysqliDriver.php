<?php
// Copyright 2016 The PHP Tuner Authors. All rights reserved.
// Use of this source code is governed by a GPL-3.0
// license that can be found in the LICENSE file.

class MysqliDriver
{
    private $config     = null;
    private $default_db = null;

    // useless ?
    private $master_link = null;
    private $slave_link  = null;
    
    private $last_link = null;
    private $transaction_link = null;

    private static $links = array();

    // 连接类型
    private $link_type = '';

    public function __construct($config, $dbname = '')
    {
        $this->config     = $config;
        $this->default_db = $dbname;
    }

    // Todo: implement
    public function __toString()
    {
        return print_r($this, true);
    }

    //关闭连接
    public function closeLinks($type = '')
    {
        if ($type && isset(self::$links[$type])) {
            if (is_object(self::$links[$type])) {
                self::$links[$type]->close();
            }
            self::$links[$type] = null;
        }
        foreach (self::$links as $link) {
            if (is_object($link)) {
                $link->close();
            }
            $link = null;
        }
        self::$links = array();
    }

    // 开启事务
    public function begin($flags, $name)
    {
        $this->transaction_link = $this->getRawLink('master', true, $options);
        if (!$this->transaction_link->beginTransaction($flags, $name)) {
            $this->panic("beginTransaction failed.");
        }
        // close autocommit.
        // if(!$this->transaction_link->autocommit(false)){
        //     $this->panic("close autocommit failed.");
        // }
    }

    // 提交事务
    public function commit()
    {
        if (!is_object($this->transaction_link)) {
            throw new Exception('you may forgot to call begin.');
        }
        $this->transaction_link->commit();
        $this->transaction_link = null;
    }

    // 回滚事务
    public function rollback()
    {
        if (!is_object($this->transaction_link)) {
            throw new Exception('you may forgot to call begin.');
        }
        $this->transaction_link->rollback();
        $this->transaction_link = null;
    }

    // 转义字符
    public function escape($v)
    {
        if (is_object($this->last_link)) {
            return mysqli_real_escape_string($this->last_link, $v);
        }
        // TODO consider encoding
        $search  = array("\\", "\x00", "\n", "\r", "'", '"', "\x1a");
        $replace = array("\\\\", "\\0", "\\n", "\\r", "\'", '\"', "\\Z");
        return str_replace($search, $replace, $v);
    }

    // 连接数据库
    public function getRawLink($type = 'slave', $force_new = false, $driver_options = array())
    {
        // $type || $type = $this->link_type;
        if (!isset($this->config[$type])) {
            throw new Exception("not found $type config");
        }
        $cfg      = $this->config[$type];
        $host     = isset($cfg['host']) ? $cfg['host'] : null;
        $user     = isset($cfg['user']) ? $cfg['user'] : '';
        $password = isset($cfg['password']) ? $cfg['password'] : '';
        $port     = isset($cfg['port']) ? $cfg['port'] : 3306;
        $db_name  = $this->default_db ? $this->default_db : $cfg['dbname'];
        $charset  = isset($cfg['charset']) ? $cfg['charset'] : 'utf8';
        $dsn      = "mysql:dbname={$db_name};host={$host};port={$port};charset={$charset}";
        $link_key = md5(json_encode($cfg) . $db_name);
        if (isset(self::$links[$link_key]) && !$force_new) {
            return self::$links[$link_key];
        }
        self::$links[$link_key] = null; // destory it
        $link = mysqli_init();
        if (!$link) {
            $this->panic("mysqli_init failed. dsn: $dsn");
        }
        // 设置超时时间
        if (!$link->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3)) {
            $this->panic("MYSQLI_OPT_CONNECT_TIMEOUT set failed.");
        }
        // real connect.
        if (!$link->real_connect($host, $user, $password, $db_name)) {
            $this->panic('Connect Error (' . mysqli_connect_errno() . ') '
            . mysqli_connect_error());
        }
        
        // set charset
        if (!$link->set_charset('utf8')) {
            $this->panic("Error loading character set utf8\n");
        }
        return self::$links[$link_key] = $link;
    }
    
    // 返回最近使用的链接
    public function lastLink()
    {
        return $this->last_link;
    }
    
    // 切换主从
    public function changeLinkType($type)
    {
        if (!in_array($type, array('slave', 'master'))) {
            throw new Exception("不支持此连接类型");
        }
        $this->link_type = $type;
    }

    // 抛出异常
    private function panic($msg, $code = 0)
    {
        $this->log($msg);
        throw new Exception('数据库错误', $code);
    }

    // 记录错误日志
    private function log($log_str, $type = "error")
    {
        $link = $this->last_link;
        if (is_object($link)) {
            $log_str = $link->error."($log_str)";
            //$info .= "Error: {$link->error}\t";
        }
        Log::file("{$type}\t{$log_str}", "mysql");
    }

    // 执行SQL
    public function query($sql, $force_new = false)
    {
        $sql = trim($sql);
        if ($this->transaction_link) {
            $link = $this->transaction_link;
        } else {
            $is_select = preg_match('/^SELECT\s+/i', $sql);
            // 非事务状态下自动切换主从
            $link_type = $this->link_type;
            if (!$link_type) {
                $link_type = $is_select ? 'slave' : 'master';
            }
            $link = $this->getRawLink($link_type, $force_new);
        }
        $this->last_link = $link;
        $start_time = microtime(true);
        
        $result = $link->query($sql);
        // should reconnect.
        if (in_array($link->errno, array(
            '2006', // MySQL server has gone away
            '2013', // Lost connection to MySQL server during query
        ))) {
            $this->log('try reconnect mysql(ping)');
            $link->ping();
            $result = $link->query($sql);
        }
        if ($result == false) {
            $this->panic("query failed: $sql");
        }
        
        $used_time  = microtime(true) - $start_time;
        Log::debug("sql: $sql, time: $used_time sec");
        
        return $result;
    }

    // 执行SQL返回一条记录
    public function queryRow($sql)
    {
        $result = $this->query($sql);
        $array = $result->fetch_array(MYSQLI_ASSOC);
        mysqli_free_result($result);
        return $array;
    }

    // 执行SQL返回多条记录
    public function queryRows($sql)
    {
        $result = $this->query($sql);
        $array = $result->fetch_all(MYSQLI_ASSOC);
        mysqli_free_result($result);
        return $array;
    }

    public function queryFirst($sql)
    {
        $row = $this->queryRow($sql);
        if (is_array($row)) {
            return current($row);
        }
        return 0;
    }
}
