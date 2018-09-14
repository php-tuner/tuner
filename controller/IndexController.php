<?php
// Copyright 2016 The PHP Tuner Authors. All rights reserved.
// Use of this source code is governed by a GPL-3.0
// license that can be found in the LICENSE file.

// 默认首页
class IndexController extends Controller {
	public function index() {
		$this->display('default/index.html', array(
			'project' => PROJECT,
		));
	}
}