<?php
// Copyright 2016 The PHP Tuner Authors. All rights reserved.
// Use of this source code is governed by a GPL-3.0
// license that can be found in the LICENSE file.

class Model {

	protected $db    = null;
	protected $table = '';

	public function __construct() {

	}

	// 魔法__call
	public function __call($func, $args) {
		if (!method_exists($this->db, $func)) {
			$class_name = get_class($this);
			throw new Exception("not found({$class_name}::{$func}).");
		}
		return call_user_func_array(array($this->db, $func), $args);
	}

	// 带缓存的 queryRow
	public function queryCacheRow($sql, $cache_time = 300) {
		$cache_key  = md5("queryCacheRow_{$sql}");
		$cache_data = Cache::getData($cache_key);
		if ($cache_data) {
			return $cache_data;
		}
		$re = $this->db->queryRow($sql);
		Cache::setData($cache_key, $re, $cache_time);
		return $re;
	}

	// 带缓存的 queryRows
	public function queryCacheRows($sql, $cache_time = 300) {
		$cache_key  = md5("queryCacheRow_{$sql}");
		$cache_data = Cache::getData($cache_key);
		if ($cache_data) {
			return $cache_data;
		}
		$re = $this->db->queryRows($sql);
		Cache::setData($cache_key, $re, $cache_time);
		return $re;
	}

	// 带缓存的 getRow
	public function getCacheRow($where_array, $table = '', $cache_time = 300) {
		$sql = $this->buildSql($where_array, $table);
		return $this->queryCacheRow($sql, $cache_time);
	}

	// 带缓存的 getRows
	public function getCacheRows($where_array, $table = '', $cache_time = 300) {
		$sql = $this->buildSql($where_array, $table);
		return $this->queryCacheRows($sql, $cache_time);
	}
	
	// 构建 SQL 语句
	protected function buildSql($where_array, $table = '') {
		$table || $table = $this->table;
		$table           = $this->escape($table);
		$where_str       = $this->getWhereStr($where_array);
		$sql             = "SELECT * FROM `$table` $where_str";
		return $sql;
	}

	// 构建 count SQL 语句
	protected function buildCountSql($where_array, $table = '') {
		$table || $table = $this->table;
		$table           = $this->escape($table);
		$where_str       = $this->getWhereStr($where_array);
		$sql             = "SELECT count(*) FROM `$table` $where_str";
		return $sql;
	}

	// 获取单条记录
	public function getRow($where_array, $table = '') {
		$sql = $this->buildSql($where_array, $table);
		$sql .= " LIMIT 1";
		return $this->queryRow($sql);
	}

	// 获取多条记录
	public function getRows($where_array, $table = '') {
		$sql = $this->buildSql($where_array, $table);
		return $this->queryRows($sql);
	}

	// 分页获取
	public function getPageRows($where_array, $order = '', $limit = '', $table = '') {
		$sql       = $this->buildSql($where_array, $table);
		$count_sql = $this->buildCountSql($where_array, $table);
		$result    = array(
			'count' => $this->queryFirst($count_sql),
			'rows'  => array(),
		);
		if (!$result['count']) {
			return $result;
		}
		if ($order) {
			$sql .= " $order ";
		}
		if ($limit) {
			$sql .= " $limit ";
		}
		$result['rows'] = $this->queryRows($sql);
		return $result;
	}

	// 转义字符
	public function escape($v) {
		$search  = array("\\", "\x00", "\n", "\r", "'", '"', "\x1a");
		$replace = array("\\\\", "\\0", "\\n", "\\r", "\'", '\"', "\\Z");
		return str_replace($search, $replace, $v);
	}

	// 构建 where 字串
	public function getWhereStr($where_array) {
		$str = $this->buildCondStr($where_array);
		return $str ? " WHERE $str " : $str;
	}

	// build sql condition str
	// cond_array 支持的条件表达方式
	// $cond_array = array(
	//         'status' => 1,
	//         'id' => array(1, 2, 3),
	//         'status' => array('!=' => 1),
	//         'title' => array('like' => '%hello%'),
	// );
	private function buildCondStr($cond_array, $concate_str = 'AND') {
		if (!is_array($cond_array) || !$cond_array) {
			return '';
		}
		$first_key = key($cond_array);
		$first_val = current($cond_array);
		$op        = is_string($first_val) ? strtoupper($first_val) : '';
		if (is_int($first_key) && in_array($op, array('OR', 'AND'))) {
			$_conds = array();
			foreach (array_slice($cond_array, 1) as $val) {
				if (!$val) {
					continue;
				}
				$_conds[] = $this->buildCondStr($val);
			}
			return count($_conds) > 1 ? '( ' . implode(" ) $op ( ", $_conds) . ' )' : implode($op, $_conds);
		}
		$conds = array();
		foreach ($cond_array as $key => $value) {
			//Todo be more safe check
			if (stripos($key, '.') === false) {
				$key = " `$key` ";
			}
			if (is_array($value)) {
				$in_value = array();
				foreach ($value as $k => $v) {
					if (is_int($k)) {
						$v          = $this->escape($v);
						$in_value[] = $v;
					} else { //key作为操作符使用
						$k = $this->escape($k);
						if (is_array($v)) {
							$v       = implode("','", array_map(array($this, 'escape'), $v));
							$conds[] = " $key $k ('$v') ";
						} else {
							$v       = $this->escape($v);
							$conds[] = " $key $k '$v' ";
						}
					}
				}
				if ($in_value) {
					$in_value = implode("','", $in_value);
					$conds[]  = " $key in( '$in_value' ) ";
				}
			} else {
				$value   = $this->escape($value);
				$conds[] = " $key = '$value' ";
			}
		}
		return implode($concate_str, $conds);
	}

	// get sql set str
	public function getSetStr($data_array) {
		if (!is_array($data_array) || !$data_array) {
			throw new Exception("set array invalid");
		}
		$set_array = array();
		foreach ($data_array as $key => $value) {
			$value       = $this->escape($value);
			$key         = $this->escape($key);
			$set_array[] = " `$key` = '{$value}' ";
		}
		return ' SET ' . implode(',', $set_array);
	}

	// 插入记录
	public function insertOne($sets, $table = '') {
		$table || $table = $this->table;
		$table           = $this->escape($table);
		$set_str         = $this->getSetStr($sets);
		$sql             = "INSERT INTO `$table` $set_str";
		$re              = $this->query($sql);
		return $this->lastLink()->lastInsertId();
	}

	// 更新单条记录
	public function updateOne($sets, $wheres, $table = '') {
		$table || $table = $this->table;
		$table           = $this->escape($table);
		$set_str         = $this->getSetStr($sets);
		$where_str       = $this->getWhereStr($wheres);
		$sql             = "UPDATE `$table` $set_str $where_str LIMIT 1";
		$re              = $this->query($sql);
		return $re ? $re->rowCount() : false;
	}

	// 更新多条记录
	public function updateBatch($sets, $wheres, $table = '') {
		$table || $table = $this->table;
		$table           = $this->escape($table);
		$set_str         = $this->getSetStr($sets);
		$where_str       = $this->getWhereStr($wheres);
		$sql             = "UPDATE `$table` $set_str $where_str";
		$re              = $this->query($sql);
		return $re ? $re->rowCount() : false;
	}

	// 删除多条记录
	public function deleteBatch($wheres, $table = '') {
		$table || $table = $this->table;
		$table           = $this->escape($table);
		$where_str       = $this->getWhereStr($wheres);
		$sql             = "DELETE FROM  `$table` $where_str";
		$re              = $this->query($sql);
		return $re ? $re->rowCount() : false;
	}

	// 删除单条记录
	public function deleteOne($wheres, $table = '') {
		$table || $table = $this->table;
		$table           = $this->escape($table);
		$where_str       = $this->getWhereStr($wheres);
		$sql             = "DELETE FROM `$table` $where_str LIMIT 1";
		$re              = $this->query($sql);
		return $re ? $re->rowCount() : false;
	}

	// 获取多条记录中制定字段的结果
	public function getValues($rows, $fields = array()) {
		if (!is_array($fields)) {
			$fields = array($fields);
		}
		$re = array();
		foreach ($rows as $row) {
			foreach ($fields as $f) {
				if (!is_string($f) || !isset($row[$f])) {
					continue;
				}
				$re[$f][] = $row[$f];
			}
		}
		return count($re) > 1 ? $re : current($re);
	}

	// 格式化多条记录为以指定字段为 key 的形式
	public function formatRows($rows, $field) {
		if (!is_array($rows)) {
			return $rows;
		}
		$re = array();
		foreach ($rows as $row) {
			if (!isset($row[$field])) {
				//skip
				continue;
			}
			$re[$row[$field]] = $row;
		}
		return $re;
	}
}
