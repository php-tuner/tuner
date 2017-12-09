<?php
// Copyright 2016 The PHP Tuner Authors. All rights reserved.
// Use of this source code is governed by a GPL-3.0
// license that can be found in the LICENSE file.

// PRC 操作
class RPC
{
    
    // 请求设置项
    private $request_opt = array();
    
    // 请求协议
    private $protocol = 'http';
    
    // 初始化
    public function __construct()
    {
    }
    
    public function __call($method, $args)
    {
    }
}
