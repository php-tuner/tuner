<?php

class MysqlDb {

	private $config = null;
	private $default_db = null;

	private $master_link = null;
	private $slave_link = null;

	private static $links = array();
	
	//连接类型
	private $link_type = 'slave';
	
	private $in_transaction = false;
	
	public function __construct($config, $dbname = ''){
		$this->config = $config;
		$this->default_db = $dbname;
	}
	
	//Todo: implement
	public function __toString(){
		return print_r($this, true);
	}
	
	//关闭连接
	public function closeLinks($type = ''){
		if($type && isset(self::$links[$type])){
			self::$links[$type] = null;
		}
		foreach(self::$links as $link){
			$link = null;
		}
		self::$links = array();
	}
	
	//开启事务
	public function begin() {
		$this->changeLinkType('master');
		$link = $this->getRawLink();
		$link->beginTransaction();
		$this->in_transaction = true;
	}
	
	//提交事务
	public function commit(){
		$link = $this->getRawLink();
		$link->commit();
		$this->in_transaction = false;
	}
	
	//回滚事务
	public function rollback(){
		$link = $this->getRawLink();
		$link->rollback();
		$this->in_transaction = false;
	}
	
	// 转义字符
	public function escape($v) {
		if(function_exists('mysql_escape_string')){
			return mysql_escape_string($v);
		}
		$search = array("\\",  "\x00", "\n",  "\r",  "'",  '"', "\x1a");
		$replace = array("\\\\","\\0","\\n", "\\r", "\'", '\"', "\\Z");
		return str_replace($search, $replace, $v);
	}
	
	//连接数据库
	public function getRawLink($type = '', $force_new = false){
		$type || $type = $this->link_type;
		if(isset(self::$links[$type]) && !$force_new){
			return self::$links[$type];
		}
		if(!isset($this->config[$type])){
			throw new Exception("not found $type config");
		}
		self::$links[$type] = null;//destory it
		$cfg = $this->config[$type];
		$host = $cfg['host'];
		$user = $cfg['user'];
		$port = isset($cfg['port']) ? $cfg['port'] : 3306;
		$password = $cfg['password'];
		$db_name = $this->default_db ? $this->default_db : $cfg['dbname'];
		$charset = isset($cfg['charset']) ? $cfg['charset'] : 'utf8';
		$dsn = "mysql:dbname={$db_name};host={$host};port={$port};charset={$charset}";
		try{
			$link = new PDO($dsn, $user, $password, Config::pdo());
		}catch(Exception $e){
			$this->log("erorr:{$e->getMessage()}, dsn: $dsn", "info");
			throw new $e;
		}
		return self::$links[$type] = $link;
	}

	//切换主从
	public function changeLinkType($type) {
		if(!in_array($type, array('slave', 'master'))){
			throw new Exception("不支持此连接类型");
		}
		$this->link_type = $type;
	}
	
	//记录错误日志
	private function log($log_str, $type = "error"){
		Log::file("{$type}\t{$log_str}", "mysql");
	}

	//执行SQL
	public function query($sql, $force_new = false) {
		$sql = trim($sql);
		$is_select = preg_match('/^SELECT\s+/i', $sql);
		//非事务状态下自动切换主从
		if(!$this->in_transaction){
			if($is_select){
				$this->changeLinkType('slave');
			}else{
				$this->changeLinkType('master');
			}	
		}
		$link = $this->getRawLink('', $force_new);
		Log::debug($link);
		Log::debug($sql);
		$result = $link->query($sql);
		if(!$result){
			$info = $link->errorInfo();
			$err_msg = "{$info[0]}:{$info[1]}:{$info[2]}\t sql:$sql";
			Log::debug($err_msg);
			//记录错误日志
			$this->log($err_msg);
			//MySQL server has gone away
			if($info[1] == '2006' && !$force_new){
				$this->log("reconnect".print_r($link, true), "info");
				//wait a moment
				sleep(1);
				return $this->query($sql, true);
			}else{
				//是否要抛出异常
				throw new Exception("数据库操作发生错误");
			}
		}
		return $is_select ? $result : $link->lastInsertId();
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
		if(is_array($row)){
			return current($row);
		}
		return 0;
	}
}