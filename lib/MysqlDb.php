<?php
// Copyright 2016 The PHP Tuner Authors. All rights reserved.
// Use of this source code is governed by a GPL-3.0
// license that can be found in the LICENSE file.

class MysqlDb {

	private $config     = null;
	private $default_db = null;

	// useless ?
	private $master_link = null;
	private $slave_link  = null;
	
	private $last_link = null;
	private $transaction_link = null;

	private static $links = array();

	//连接类型
	private $link_type = '';

	public function __construct($config, $dbname = '') {
		$this->config     = $config;
		$this->default_db = $dbname;
	}

	//Todo: implement
	public function __toString() {
		return print_r($this, true);
	}

	//  获取 PDO 所有的属性
	private function getAllAttributes($conn = NULL){
		if(!$conn){
			$conn = $this->last_link;
		}
		if(!($conn instanceof PDO)){
			return array();
		}
		$attributes = array(
		    "AUTOCOMMIT", 
			"CASE", 
			"CLIENT_VERSION", 
			"CONNECTION_STATUS", // Oracle does not have the attributes
			"DRIVER_NAME",
			"ERRMODE", 
		   //"ORACLE_NULLS", 
			"PERSISTENT", 
			//"PREFETCH", // Oracle  and MySQL does not have the attributes
			"SERVER_INFO", 
			"SERVER_VERSION",
		    //"TIMEOUT" // Oracle  and MySQL does not have the attributes
		);
		$result = array();
		foreach ($attributes as $attr) {
			$attr_name = "PDO::ATTR_$attr";
			$result[$attr_name] = defined($attr_name) ? $conn->getAttribute(constant($attr_name)) : null;
		}
		return $result;
	}

	//关闭连接
	public function closeLinks($type = '') {
		if ($type && isset(self::$links[$type])) {
			self::$links[$type] = null;
		}
		foreach (self::$links as $link) {
			$link = null;
		}
		self::$links = array();
	}

	//开启事务
	public function begin() {
		$options = Config::pdo();
		$options[PDO::ATTR_AUTOCOMMIT] = 0;
		$this->transaction_link = $this->getRawLink('master', true, $options);
		$this->transaction_link->beginTransaction();
		if($this->transaction_link->inTransaction() !== true){
			throw new Exception('事务启动失败～');
		}
	}

	//提交事务
	public function commit() {
		$this->transaction_link->commit();
		$this->transaction_link = null;
	}

	// 回滚事务
	public function rollback() {
		$this->transaction_link->rollback();
		$this->transaction_link = null;
	}

	// 转义字符
	public function escape($v) {
		$search  = array("\\", "\x00", "\n", "\r", "'", '"', "\x1a");
		$replace = array("\\\\", "\\0", "\\n", "\\r", "\'", '\"', "\\Z");
		return str_replace($search, $replace, $v);
	}

	// 连接数据库
	public function getRawLink($type = 'slave', $force_new = false, $driver_options = array()) {
		//$type || $type = $this->link_type;
		if (!isset($this->config[$type])) {
			throw new Exception("not found $type config");
		}
		$cfg      = $this->config[$type];
		$host     = $cfg['host'];
		$user     = $cfg['user'];
		$password = $cfg['password'];
		$port     = isset($cfg['port']) ? $cfg['port'] : 3306;
		$db_name  = $this->default_db ? $this->default_db : $cfg['dbname'];
		$charset  = isset($cfg['charset']) ? $cfg['charset'] : 'utf8';
		$dsn      = "mysql:dbname={$db_name};host={$host};port={$port};charset={$charset}";
		$link_key = md5(json_encode($cfg) . $db_name);
		if (isset(self::$links[$link_key]) && !$force_new) {
			return self::$links[$link_key];
		}
		self::$links[$link_key] = null; //destory it
		try {
			$link = new PDO($dsn, $user, $password, $driver_options ? $driver_options : Config::pdo());
		} catch (Exception $e) {
			$this->log("erorr:{$e->getMessage()}, dsn: $dsn", "info");
			throw new $e;
		}
		return self::$links[$link_key] = $link;
	}
	
	//返回最近使用的链接
	public function lastLink() {
		return $this->last_link;
	}
	
	//切换主从
	public function changeLinkType($type) {
		if (!in_array($type, array('slave', 'master'))) {
			throw new Exception("不支持此连接类型");
		}
		$this->link_type = $type;
	}

	//记录错误日志
	private function log($log_str, $type = "error") {
		$link = $this->last_link;
		if($link){
			$version_info = "server_version:".$link->getAttribute(PDO::ATTR_SERVER_VERSION);
			$version_info .= "\tclient_version:".$link->getAttribute(PDO::ATTR_CLIENT_VERSION);
		}
		Log::file("{$type}\t{$log_str}\t$version_info", "mysql");
	}

	//执行SQL
	public function query($sql, $params = array(), $options = array(), $force_new = false) {
		try{
			$sql = trim($sql);
			if($this->transaction_link){
				$link = $this->transaction_link;
			}else{
				$is_select = preg_match('/^SELECT\s+/i', $sql);
				// 非事务状态下自动切换主从
				$link_type = $this->link_type;
				if (!$link_type) {
					$link_type = $is_select ? 'slave' : 'master';
				}
				$link = $this->getRawLink($link_type, $force_new);
			}
			$this->last_link = $link;
			//Log::debug($link);
			$start_time = microtime(true);
			$error_info = array();
			if($params){
				$sth = $link->prepare($sql, $options);
				if(!$sth->execute($params)){
					$error_info = $sth->errorInfo();
					$sth = false;
				}
			}else{
				$sth = $link->query($sql);
				if($sth === false){
					$error_info = $link->errorInfo();
				}
			}
			$used_time  = microtime(true) - $start_time;
			Log::debug("sql: $sql, time: $used_time sec");
		}catch(PDOException $e){
			// 相当于PDO::errorInfo() 或 PDOStatement::errorInfo()
			$error_info = $e->errorInfo;
		}
		if ($error_info) {
			$err_msg = "sql:$sql\terror_info:{$error_info[0]},{$error_info[1]},{$error_info[2]}";
			Log::debug($err_msg);
			//记录错误日志
			$this->log($err_msg);
			if (in_array($error_info[1], array(
				'2006', //MySQL server has gone away
				'2013', //Lost connection to MySQL server during query
			)) && !$force_new) {
				$this->log("reconnect", "info");
				return $this->query($sql, $params, $options, true);
			} else {
				//是否要抛出异常
				throw new Exception("数据库操作发生错误");
			}
		}
		return $sth;
	}

	//执行SQL返回一条记录
	public function queryRow($sql) {
		$result = $this->query($sql);
		return $result->fetch(PDO::FETCH_ASSOC);
	}

	//执行SQL返回多条记录
	public function queryRows($sql) {
		$result = $this->query($sql);
		return $result->fetchAll(PDO::FETCH_ASSOC);
	}

	public function queryFirst($sql) {
		$row = $this->queryRow($sql);
		if (is_array($row)) {
			return current($row);
		}
		return 0;
	}
}
