<?php
// Copyright 2016 The PHP Tuner Authors. All rights reserved.
// Use of this source code is governed by a GPL-3.0
// license that can be found in the LICENSE file.

// 应用程序管理类
// 负责框架内部外部数据流动传递
class App
{

    // 初始化
    public function __construct($opt = array())
    {
        if (is_array($opt)) {
            $is_debug = isset($opt['debug']) ? $opt['debug'] : false;
        } else {
            $is_debug = $opt; // TODO: remove it in the future.
        }
        // 初始化开始
        Log::init($is_debug);
    }

    // must run in cgi mode
    public static function runGGI($is_debug = false)
    {
        if (RUN_MODEL !== 'CGI') {
            exit('must run in CGI mode');
        }
        self::run($is_debug);
    }
    
    // must run in cli mode
    public static function runCLI($is_debug = false)
    {
        if (RUN_MODEL !== 'CLI') {
            exit('must run in CLI mode');
        }
        self::run($is_debug);
    }

    // 执行处理流程
    public static function run($is_debug = false)
    {
        // 如果用cli方式运行(不去改变$_SERVER变量)
        $app = new self($is_debug);
        
        if (RUN_MODEL == 'CLI') {
            // use $_SERVER['argv'] instead of $argv(not available)
            $req = new RequestCLI();
        } else {
            $req = new Request();
        }
        
        $res = new Response(Config::common('charset'));
        $cfg = new Config();
        
        try {
            // 默认处理器
            Route::addHandler(function ($req, $res, $cfg) {
                
                // 开始查找控制器
                $route_path = parse_url($req->route_uri, PHP_URL_PATH);
                if (!$route_path) {
                    $route_path = preg_replace('/\?(.*)/i', '', $req->route_uri);
                }
                
                $route_file_path = realpath(Helper::dir(APP_PUBLIC_DIR, $route_path));
                
                // 静态文件目录，直接输出内容
                // TODO 限制扩展名
                if (is_file($route_file_path)) {
                    $res->file($route_file_path);
                    return true;
                }
                
                $pathinfo = pathinfo($route_path);
                // $pathinfo = pathinfo(parse_url($req->uri, PHP_URL_PATH));
                $ext    = isset($pathinfo['extension']) ? $pathinfo['extension'] : '';
                $path = '/';
                
                if (isset($pathinfo['dirname'])) {
                    $pathinfo['dirname'] = ltrim($pathinfo['dirname'], './');
                    $path = $pathinfo['dirname'] ? "{$pathinfo['dirname']}/{$pathinfo['filename']}" : $pathinfo['filename'];
                    $path   = str_replace(array('//'), '', $path);
                }
                
                $params = explode("/", trim($path, '/'));

                // 附加上最后的文件名
                // $pathinfo['filename'] && $params[] = $pathinfo['filename'];
                $len = count($params);
                // 默认倒数第二个是 controller, 倒数第一个是 action
                $controller_pos = $len > 1 ? $len - 2 : 0;
                // 从 DOCUMENT_ROOT 开始找起
                $controller_dir = APP_CONTROLLER_DIR;
                
                // 尝试匹配子目录(大于三层目录时)
                for ($i = 0; $i < $len - 2; $i++) {
                    $value          = $params[$i];
                    $controller_pos = $i;
                    $controller_dir = Helper::dir($controller_dir, $value);
                    if (!file_exists($controller_dir)) {
                        $controller_dir = dirname($controller_dir);
                        break;
                    }
                    $controller_pos++;
                }
                
                // 子目录
                $sub_dir = call_user_func_array('Helper::dir', array_slice($params, 0, $controller_pos));

                $controller = isset($params[$controller_pos]) && preg_match('/^([a-zA-Z_\-0-9]+)$/i', $params[$controller_pos]) ? $params[$controller_pos] : Config::common('defaultController');
                $controller = str_replace(' ', '', ucwords(str_replace(CAMEL_CLASS_SEP, ' ', $controller)));
                $class      = ucwords($controller) . "Controller";
                
                // 添加自动加载目录
                $autoload_dir = array();
                $tmp_sub_dir = $sub_dir;
                do {
                    $autoload_dir['controller'][] = Helper::dir(APP_ROOT_DIR, 'controller', $tmp_sub_dir);
                    $autoload_dir['model'][] = Helper::dir(APP_ROOT_DIR, 'model', $tmp_sub_dir);
                    $autoload_dir['final'][] = Helper::dir(APP_ROOT_DIR, 'lib', $tmp_sub_dir);
                    if (!$tmp_sub_dir) {
                        break;
                    }
                    
                    $index = strrpos($tmp_sub_dir, '/');
                    $tmp_sub_dir = $index === false ? '' : substr($tmp_sub_dir, 0, $index);
                } while (true);
                spl_autoload_register(_createLoader_($autoload_dir), true, true);
                
                if (!class_exists($class)) {
                    throw new Exception("not found(controller: $class)", 404);
                }
                
                Log::debug($class);
                // 实例化控制器
                $c = new $class($req, $res, $cfg);
                
                // 捕获应用层异常，交给controller 处理
                try {
                    
                    // 寻找控制器方法
                    if (isset($params[$controller_pos + 1])) {
                        $action = $params[$controller_pos + 1];
                        if (!preg_match('/^([a-zA-Z_\-0-9]+)$/i', $action)) {
                            throw new Exception("action is illegal", 500);
                        }
                        $action_args = array_slice($params, $i + 2);
                    } else { // 目录请求
                        $action = Config::common('defaultAction');
                        $action_args = array_slice($params, $i + 1);
                    }
                    
                    // 分隔符也许可以定制
                    // TODO 不要调用 $c 上的任何方法！！
                    if(method_exists($c, 'setTplFile')){
                        $c->setTplFile(Helper::dir($sub_dir, $controller, $action).'.'.(empty($ext) ? 'html' : $ext));
                    }
                    
                    $action = '_'.str_replace(CAMEL_CLASS_SEP, ' ', $action);
                    $action = str_replace(' ', '', ucwords($action));
                    $action = ltrim($action, '_');
                    if (!method_exists($c, $action)) {
                        throw new Exception("not found(action: $action)", 404);
                    }
                    
                    // TODO WHY urldecode?
                    $action_args = array_map('urldecode', $action_args);
                    // action 后的做为参数
                    call_user_func_array(array($c, $action), $action_args);
                } catch (Exception $e) {
                    // TODO check _handleException exists ?
                    $c->_handleException($e);
                }
            });
            Route::dispatch($req, $res, $cfg);
        } catch (Exception $e) {
            // TODO 需要优化
            (new Controller($req, $res, $cfg))->_handleException($e);
        }
        return $app;
    }

    // all done
    public function __destruct()
    {
        Log::show();
    }
}
