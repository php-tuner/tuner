<?php
// Copyright 2016 The PHP Tuner Authors. All rights reserved.
// Use of this source code is governed by a GPL-3.0
// license that can be found in the LICENSE file.

/**
 *
 * 前端处理器
 * 请仅将需要http 访问的方法定义为public
 *
 */
class Controller {
	protected $req         = null;
	protected $res         = null;
	protected $cfg         = array();
	//模版数据

	public function __construct($req, $res, $cfg) {
		$this->req = $req; //请求
		$this->res = $res; //响应
		$this->cfg = $cfg; //配置
	}

	//默认首页
	public function index() {
		$this->res->html("<h1>Not found default action(index).</h1>");
	}

	//call other controller action
	public function callAction($action, $controller = null) {

		if ($controller == null) {
			$controller = $this;
		} else {
			$pathinfo   = pathinfo($controller);
			$controller = new $pathinfo['basename']();
		}
		//打开输出缓冲
		ob_start();
		call_user_func_array(array($controller, $action));
		$re = ob_get_contents();
		ob_end_clean();
		return $re;
	}

	//获取模版
	protected function getTpl($template_config, $charset = 'utf8') {
		//加载模版引擎
		Twig_Autoloader::register();
		$loader = new Twig_Loader_Filesystem($template_config['path']);
		return new Twig_Environment($loader, array(
			'cache'       => $template_config['cache'],
			'auto_reload' => true,
			'charset'     => $charset,
			//'debug' => true,
		));
	}

	//重定向
	protected function redirect($url, $code = 302) {
		//相对链接转换成绝对链接
		if (!preg_match('/^https?:\/\//', $url)) {
			$url = $this->buildUrl($url);
		}
		$this->res->redirect($url, $code, false);
		//显示一段 html
		$this->display('redirect.html', array(
			'url' => $url,
		));
	}

	//直接输出
	protected function output() {
		$format = $this->req->format;
		if (!method_exists($this->res, $format)) {
			$format = 'html';
		}
		call_user_func_array(array($this->res, $format), func_get_args());
	}

	//构建URL
	protected function buildUrl($uri, $params = array()) {
		if (preg_match('#^https?://#', $uri)) {
			$url = $uri; //本身是绝对地址
		} else {
			$base_url = $this->req->base_url ? $this->req->base_url : Config::site('base_url');
			$base_url = rtrim($base_url, '/');
			$uri      = ltrim($uri, '/');
			$url      = "{$base_url}/$uri";
		}
		if (!$params) {
			return $url;
		}
		if (stripos($url, '?') === false) {
			$url .= "?";
		}
		if (substr($url, -1) != '?') {
			$url .= "&";
		}
		$query_string = http_build_query($params, '', '&');
		return $url . $query_string;
	}

	//渲染数据
	protected function render($template_file, $data = array()) {
		return Tpl::render($template_file, array_merge(array(
			'_cfg_' => $this->cfg,
		), $data), $this->res->charset);
	}

	//模版引擎渲染输出(如果仅需要渲染数据不需要输出，请使用render函数)
	protected function display($template_file, $data = array(), $return = false) {

		$detect = new MobileDetect();
		if ($detect->isMobile() || $this->req->get('_version') == 'mobile') {
			$pinfo    = pathinfo($template_file);
			$filename = $pinfo['filename'];
			$ext      = $pinfo['extension'];
			$tpl_path = Config::tpl('path');
			$tpl_file = Helper::dir($pinfo['dirname'], "{$filename}_mobile.{$ext}");
			if (file_exists(Helper::dir($tpl_path, $tpl_file))) {
				$template_file = $tpl_file;
			}
		}

		$output = $this->render($template_file, $data);
		//直接返回
		if ($return) {
			return $output;
		}
		//按json结构输出
		$format = $this->req->format;
		if (in_array($this->req->format, array('xml', 'json'))) {
			$output = array(
				'html_content' => $output,
			);
		}
		$this->res->output($output, $format);
	}

	//操作成功
	protected function success($data = array(), $msg = '操作成功', $halt = true) {
		$re = array(
			'error_code' => 0,
			'tip_msg'    => $msg,
			'data'       => $data,
		);
		$this->output($re);
		$halt && exit();
	}

	//异常处理(Todo: 支持backtrace ?)
	public function _handleException($e) {
		$format = $this->req->format;
		$code   = $e->getCode() ? $e->getCode() : 1;
		$msg    = $e->getMessage();
		switch ($format) {
		case 'json':
		case 'xml':
			$data = array(
				'error_msg'  => $msg,
				'error_code' => $code,
			);
			break;
		default: //html
			$back_url = $this->req->gpc('_back_url');
			if (!$back_url && isset($_SERVER['HTTP_REFERER'])) {
				$back_url = $_SERVER['HTTP_REFERER'];
			}
			$data = $this->render('message/error.html', array(
				'error_msg'  => $msg,
				'error_code' => $code,
				'back_url'   => $back_url,
			));
		}
		//avoid call child method implemention
		self::output($data);
	}

	//404 not found(Todo: 优化)
	public function notFound() {
		$this->res->notFound();
	}
}
