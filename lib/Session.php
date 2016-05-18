<?php

class Session {
	
	//启动session
	public static function start(){
		if(!isset($_SESSION)){
			session_start();
		}
	}
	
	// 设置session
	public static function set($key, $value){
		self::start();
		return $_SESSION[$key] = $value;
	}
	
	// 获取session值
	public static function get($key){
		self::start();
		return isset($_SESSION[$key]) ? $_SESSION[$key] : null;
	}
	
	//获取之后删除
	public static function getOnce($key){
		self::start();
		if(isset($_SESSION[$key])){
			unset($_SESSION[$key]);
			return $_SESSION[$key];
		}
		return null;		
	}
}