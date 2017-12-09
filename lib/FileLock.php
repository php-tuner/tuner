<?php
// Copyright 2016 The PHP Tuner Authors. All rights reserved.
// Use of this source code is governed by a GPL-3.0
// license that can be found in the LICENSE file.


// 锁服务(用File模拟锁)
class FileLock
{

    private $all_lock_handles = array();
    private $lock_dir         = "/tmp";

    public function __construct($lock_dir = 0)
    {
        if ($lock_dir) {
            $this->$lock_dir = $lock_dir;
        }
        if (!file_exists($this->lock_dir)) {
            mkdir($this->lock_dir, 0777, true);
        }
    }

    // 获取锁文件名
    private function getLockFile($name)
    {
        $name = trim($name);
        if (!$name) {
            throw new Exception("lock name should not empty");
        }
        return $lock_file = "{$this->lock_dir}/$name.php.lock";
    }

    /**
     * 捕获锁
     * @param  [type] $name [description]
     * @return [type]       [description]
     */
    public function begin($name, $block = false)
    {
        $lock_file = $this->getLockFile($name);
        $fp        = fopen($lock_file, "w+");
        $opt       = LOCK_EX;
        if (!$block) {
            $opt |= LOCK_NB;
        }
        if (!flock($fp, $opt)) { // acquire an exclusive lock
            throw new Exception("Couldn't get the lock $lock_file !\n");
        }
        //要将文件句柄保存到类变量中
        $this->all_lock_handles[$name] = $fp;
        return true;
    }

    /**
     * 释放锁
     */
    public function release($name)
    {
        $handle = $this->all_lock_handles[$name];
        if ($handle) {
            @flock($handle, LOCK_UN);
            @fclose($handle);
        }
        @unlink($this->getLockFile($name));
    }

    /**
     * 释放所有的锁
     */
    public function __destruct()
    {
        foreach ($this->all_lock_handles as $name => $handle) {
            # code...
            $this->release($name);
        }
    }
}
