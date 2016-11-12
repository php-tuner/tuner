<?php
// Copyright 2016 The PHP Tuner Authors. All rights reserved.
// Use of this source code is governed by a GPL-3.0
// license that can be found in the LICENSE file.

// 限制控制器仅能在CLI模式下运行
class CLIController extends Controller {
	
	public function __construct($req, $res, $cfg){
		parent::__construct($req, $res, $cfg);
		// 限制仅能在CLI模式下运行
		if(php_sapi_name() !== 'cli'){
			$res->notFound();// 404 for cgi
		}
	}
}