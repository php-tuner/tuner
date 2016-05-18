<?php
/**
 *
 * 模版引擎
 *
 */
class Tpl {
	
	//渲染模版数据
	public static function render($tpl_file, $data, $charset = 'utf8'){
		switch(Config::common('tpl')){
			default:
				return self::twig(Config::tpl(), $charset)->render($tpl_file, $data);
		}
	}
	
	//http://twig.sensiolabs.org/
	public static function twig($template_config, $charset = 'utf8'){
		if(!is_array($template_config['path'])){
			$template_config['path'] = array($template_config['path']);
		}
		//加载系统的模版路径
		$template_config['path'][] = __ROOT__.'/view';
		//加载模版引擎
		Twig_Autoloader::register();
		$loader = new Twig_Loader_Filesystem($template_config['path']);
		return new Twig_Environment($loader, array(
		    'cache' => $template_config['cache'],
			'auto_reload' => true,
			'charset' => $charset,
			//'debug' => true,
		));
	}
	
}