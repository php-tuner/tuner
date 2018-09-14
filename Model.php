<?php
// Copyright 2016 The PHP Tuner Authors. All rights reserved.
// Use of this source code is governed by a GPL-3.0
// license that can be found in the LICENSE file.

class Model
{

    protected $db    = null;
    protected $table = '';

    // 初始化
    public function __construct($table = '', $db_name = '', $config_cate = '')
    {
        $this->db = Db::mysql(Config::mysql($config_cate ? $config_cate : 'default'), $db_name);
        $this->table = $table;
    }

    // 魔法__call
    public function __call($func, $args)
    {
        if (!method_exists($this->db, $func)) {
            $class_name = get_class($this);
            throw new Exception("not found({$class_name}::{$func}).");
        }
        return call_user_func_array(array($this->db, $func), $args);
    }

    // 带缓存的 queryRow
    public function queryCacheRow($sql, $cache_time = 300)
    {
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
    public function queryCacheRows($sql, $cache_time = 300)
    {
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
    public function getCacheRow($where_array, $table = '', $cache_time = 300)
    {
        $sql = $this->buildSql($where_array, $table);
        return $this->queryCacheRow($sql, $cache_time);
    }

    // 带缓存的 getRows
    public function getCacheRows($where_array, $table = '', $cache_time = 300)
    {
        $sql = $this->buildSql($where_array, $table);
        return $this->queryCacheRows($sql, $cache_time);
    }
    
    // 构建 SQL 语句
    protected function buildSql($where_array, $fields = array(), $table = '')
    {
        $args = func_get_args();
        // 兼容旧版支持传递两个参数
        if (count($args) == 2) {
            // 第二个参数是数组的话就代表是字段数组
            if (is_array($args[1])) {
                $fields = $args[1];
            } else {
                $table = $args[1];
            }
        }
        $table || $table = $this->table;
        $table           = $this->escape($table);
        $where_str       = $this->getWhereStr($where_array);
        $fields_str = '*';
        if ($fields) {
            // build it.
            $fields_str = implode(', ', array_map(array($this, 'escape'), $fields));
        }
        $sql             = "SELECT $fields_str FROM `$table` $where_str";
        return $sql;
    }

    // 获取多条记录
    // getRows($where_array, $table)
    // or getRows($where_array, $fields, $table)
    public function getRows()
    {
        $sql = call_user_func_array(array($this, 'buildSql'), func_get_args());
        return $this->queryRows($sql);
    }

    // 获取单条记录
    // getRow($where_array, $table)
    // getRow($where_array, $fields = array())
    // or getRow($where_array, $fields, $table)
    public function getRow()
    {
        $sql = call_user_func_array(array($this, 'buildSql'), func_get_args());
        $sql .= " LIMIT 1";
        return $this->queryRow($sql);
    }
    
    // escape like value.
    // escape _ (underscore) and % (percent) signs, which have special meanings in LIKE clauses.
    public function escapeLike($v)
    {
        return str_replace(array('_', '%'), array('\_', '\%'), $v);
    }

    // 构建 where 字串
    public function getWhereStr($where_array)
    {
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
    private function buildCondStr($cond_array, $concate_str = 'AND')
    {
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
            // Todo be more safe check
            if (stripos($key, '.') === false) {
                $key = " `$key` ";
            }
            if (is_array($value)) {
                $in_value = array();
                foreach ($value as $k => $v) {
                    if (is_int($k)) {
                        $v          = $this->escape($v);
                        $in_value[] = $v;
                    } else { // key 作为操作符使用
                        $k = $this->escape($k);
                        if (is_array($v)) {
                            $v       = implode("','", array_map(array($this, 'escape'), $v));
                            $conds[] = " $key $k ('$v') ";
                        } else {
                            $v       = $this->escape($v);
                            // escape LIKE value.
                            if (strtolower($k) == 'like') {
                                $v = $this->escapeLike($v);
                            }
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
    public function getSetStr($data_array)
    {
        if (!is_array($data_array) || !$data_array) {
            throw new Exception("set array invalid");
        }
        $set_array = array();
        foreach ($data_array as $key => $value) {
            if (is_scalar($value) == false) {
                throw new Exception("{$key} is not scalar.");
            }
            $value       = $this->escape($value);
            $key         = $this->escape($key);
            $set_array[] = " `$key` = '{$value}' ";
        }
        return ' SET ' . implode(',', $set_array);
    }

    // 插入记录
    public function insertOne($sets, $table = '')
    {
        $table || $table = $this->table;
        $table           = $this->escape($table);
        $set_str         = $this->getSetStr($sets);
        $sql             = "INSERT INTO `$table` $set_str";
        $re              = $this->query($sql);
        return $this->getInsertId();
    }
    
    // 插入多条记录
    public function insertBatch($sets, $table = '')
    {
        $table || $table = $this->table;
        $table           = $this->escape($table);
        
        if (!is_array($sets)) {
            throw new Exception("insertBatch 参数错误");
        }
        $first_set = current($sets);
        if (!is_array($first_set)) {
            throw new Exception("insertBatch 参数错误");
        }
        $sql = "INSERT INTO `$table` ";
        $fields = array();
        foreach (array_keys($first_set) as $field) {
            $fields[] = $this->escape($field);
        }
        foreach ($sets as $key => $set) {
            foreach ($set as $k => $v) {
                $set[$k] = $this->escape($v);
            }
            $sets[$key] = implode("','", $set);
        }
        $fields = implode('`,`', $fields);
        $sets = implode("'),('", $sets);
        $sql             = "INSERT INTO `$table` (`$fields`) values ('$sets')";
        $re              = $this->query($sql);
        return $this->getInsertId();
    }

    // 更新单条记录
    public function updateOne($sets, $wheres, $table = '')
    {
        $table || $table = $this->table;
        $table           = $this->escape($table);
        $set_str         = $this->getSetStr($sets);
        $where_str       = $this->getWhereStr($wheres);
        $sql             = "UPDATE `$table` $set_str $where_str LIMIT 1";
        $re              = $this->query($sql);
        return $this->affectedCount();
    }

    // 更新多条记录
    public function updateBatch($sets, $wheres, $table = '')
    {
        $table || $table = $this->table;
        $table           = $this->escape($table);
        $set_str         = $this->getSetStr($sets);
        $where_str       = $this->getWhereStr($wheres);
        $sql             = "UPDATE `$table` $set_str $where_str";
        $re              = $this->query($sql);
        return $re ? $re->rowCount() : false;
    }

    // 删除多条记录
    public function deleteBatch($wheres, $table = '')
    {
        $table || $table = $this->table;
        $table           = $this->escape($table);
        $where_str       = $this->getWhereStr($wheres);
        $sql             = "DELETE FROM  `$table` $where_str";
        $re              = $this->query($sql);
        return $this->affectedCount();
    }

    // 删除单条记录
    public function deleteOne($wheres, $table = '')
    {
        $table || $table = $this->table;
        $table           = $this->escape($table);
        $where_str       = $this->getWhereStr($wheres);
        $sql             = "DELETE FROM `$table` $where_str LIMIT 1";
        $re              = $this->query($sql);
        return $this->affectedCount();
    }

    // 获取多条记录中制定字段的结果
    public function getValues($rows, $fields = array())
    {
        if (empty($rows)) {
            return array();
        }
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
    public function formatRows($rows, $field)
    {
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
    
    // 获取分页列表
    public function getPageRows($cond_array, $order_array = array(), $page = 1, $page_size = 50, $fields = array('*'))
    {
        $page = intval($page);
        $page_size = intval($page_size);
        $offset = ($page - 1) * $page_size;
        if ($page_size < 1) {
            throw new Exception('page_size 参数异常～');
        }
        if ($page < 1) {
            throw new Exception('page 参数异常～');
        }
        // TODO maybe limit $page_size ?
        $where_str = $this->getWhereStr($cond_array);
        $order_str = $this->buildOrderStr($order_array);
        $count_sql = "SELECT count(*) FROM `{$this->table}` $where_str ";
        $fields = implode(',', $fields);
        $sql = "SELECT $fields FROM `{$this->table}` $where_str $order_str LIMIT $offset, $page_size ";
        $count = intval($this->queryFirst($count_sql));
        $result = array(
            'page' => $page,
            'page_size' => $page_size,
            'page_count' => ceil($count / $page_size),
            'offset' => $offset,
            'count' => $count,
            'rows' => array(),
        );
        if ($result['count']) {
            $result['rows'] = $this->queryRows($sql);
        }
        return $result;
    }

    // 构建排序语句
    protected function buildOrderStr($order_array)
    {
        if (!$order_array) {
            return '';
        }
        return " ORDER BY ".implode(',', $order_array);
    }
}
