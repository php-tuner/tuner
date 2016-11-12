<?php
// Copyright 2016 The PHP Tuner Authors. All rights reserved.
// Use of this source code is governed by a GPL-3.0
// license that can be found in the LICENSE file.

class CliController extends Controller {
	
	public function __construct($req, $res, $cfg){
		parent::__construct($req, $res, $cfg);
		// 限制仅能在框架根目录访问
		if(APP_ROOT_DIR != __ROOT__){
			echo "APP_ROOT_DIR:".APP_ROOT_DIR.", __ROOT__:".__ROOT__.PHP_EOL;
			throw new Exception("access deny!");
		}
	}
}