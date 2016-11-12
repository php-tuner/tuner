<?php
// Copyright 2016 The PHP Tuner Authors. All rights reserved.
// Use of this source code is governed by a GPL-3.0
// license that can be found in the LICENSE file.

class ProjectController extends CLIController {
	// 打印信息
	private function println($str){
		echo $str.PHP_EOL;
	}

	// 创建一个新项目
	public function new() {
		try {
			$old_cwd = getcwd();
			$init_file_path = Helper::dir(APP_ROOT_DIR, 'init.php');
			// 项目路径
			$path = trim($this->req->post('path'));
			// 创建项目所需目录
			if (!file_exists($path)) {
				if (!mkdir($path, 0777, true)) {
					throw new Exception("create path($path) failed.");
				}
				$this->println("create path($path)");
			}
			chdir($path);
			// 创建入口文件
			$entry_tpl = <<<TPL
<?php
require ('$init_file_path');
App::run();

TPL;
			$entry_file = 'index.php';
			if (!file_exists($entry_file)) {
				file_put_contents($entry_file, $entry_tpl);
				$this->println("write entry file($entry_file)");
			}
			// 创建子目录
			foreach (array(
				'controller', // 控制器目录
				'model', // 模型目录
				'view', // 模版目录
				'config', // 配置目录
			) as $sub_dir) {
				if (file_exists($sub_dir)) {
					continue;
				}
				if (!mkdir($sub_dir)) {
					throw new Exception("create path($sub_dir) failed.");
				}
				$this->println("create sub dir($entry_file)");
			}
			$this->println("done, all things go well~");
		} catch (Exception $e) {
			exit($e->getMessage());
		}
	}
}