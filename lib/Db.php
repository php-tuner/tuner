<?php
class Db {

	//初始化一个mysql实例
	public static function mysql($config, $dbname = '') {
		static $mysql_obj = null;
		if ($mysql_obj == null) {
			$mysql_obj = new MysqlDb($config, $dbname);
		}
		return $mysql_obj;
	}

}