<?php
// Copyright 2016 The PHP Tuner Authors. All rights reserved.
// Use of this source code is governed by a GPL-3.0
// license that can be found in the LICENSE file.

/**
 *
 * 模版引擎
 *
 */
class Tpl
{

    // 渲染模版数据
    public static function render($tpl_file, $data, $charset = 'utf8')
    {
        $template_config = Config::tpl();
        // 加载模版引擎
        Twig_Autoloader::register();
        if (preg_match('/\.(html|tpl|xml|js|css)*$/i', $tpl_file)) { //文件路径
            if (isset($template_config['path']) && !is_array($template_config['path']) && $template_config['path']) {
                $template_config['path'] = array($template_config['path']);
            }
            // 加载系统的模版路径
            $template_config['path'][] = __ROOT__ . '/view';
            $loader                    = new Twig_Loader_Filesystem($template_config['path']);
        } else { // 字符串模版
            $tpl_str  = $tpl_file;
            $tpl_file = md5($tpl_str);
            $loader   = new Twig_Loader_Array(array(
                $tpl_file => $tpl_str,
            ));
        }
        
        return self::twig($loader, $template_config, $charset)->render($tpl_file, $data);
    }

    // http://twig.sensiolabs.org/
    public static function twig($loader, $template_config, $charset = 'utf8')
    {
        $filter = new Twig_SimpleFilter('rot13', 'str_rot13');
        
        // 'debug' => false,
        // 'charset' => 'UTF-8',
        // 'base_template_class' => 'Twig_Template',
        // 'strict_variables' => false,
        // 'autoescape' => 'html',
        // 'cache' => false,
        // 'auto_reload' => null,
        // 'optimizations' => -1,
        $twig_e = new Twig_Environment($loader, array(
            'cache'       => $template_config['cache'],
            'auto_reload' => true,
            'charset'     => $charset,
            // 'debug'       => true,
        ));
        
        if(! empty($template_config['filters'])) {
            foreach($template_config['filters'] as $name => $filter)
            {
                $twig_e->addFilter($name, $filter);
            }
        }
        
        return $twig_e;
    }
}
