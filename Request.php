<?php
// Copyright 2016 The PHP Tuner Authors. All rights reserved.
// Use of this source code is governed by a GPL-3.0
// license that can be found in the LICENSE file.

// HTTP 请求
class Request
{

    // Header头
    public $header = array();
    // 请求方法
    public $method = '';
    // 请求时间
    public $time = 0;
    // URI
    public $uri = '';
    // route URI
    public $route_uri = '';
    // base url
    public $base_url = '';
    // 要求的数据格式
    public $format = '';
    // 客户端IP
    public $client_ip = '';
    // 是否ajax请求
    public $is_ajax = false;

    // 构造方法
    public function __construct()
    {
        $this->header    = $this->getHeader();
        $this->method    = trim($_SERVER['REQUEST_METHOD']);
        $this->time      = intval($_SERVER['REQUEST_TIME']);
        $this->uri       = trim($_SERVER['REQUEST_URI']);
        $this->format    = $this->getFormat();
        $this->client_ip = static::getClientIp();
        $this->is_ajax   = static::isAjax();
        $this->route_uri = $this->uri;
        // 使用 PATH_INFO 路由
        if ($this->route_uri == '/index.php' && isset($_SERVER['PATH_INFO']) && $_SERVER['PATH_INFO']) {
            $this->route_uri = $_SERVER['PATH_INFO'];
        }
        // 将多个／替换成一个
        $this->route_uri = preg_replace('#/{1,}#', '/', $this->route_uri);
        $is_https       = self::isHttps();
        $this->base_url  = $is_https ? "https" : 'http';
        isset($this->header['Host']) && $this->base_url .= "://{$this->header['Host']}";
        if ($this->route_uri) {
            $base_pos = stripos($_SERVER['REQUEST_URI'], $this->route_uri);
            $this->base_url .= parse_url(substr($_SERVER['REQUEST_URI'], 0, $base_pos), PHP_URL_PATH);
        } else {
            $this->base_url .= $_SERVER['REQUEST_URI'];
        }
        // 解析 json 格式的 POST 体
        if (isset($this->header['Content-Type']) && preg_match('#^application/json;#i', $this->header['Content-Type'])) {
            $_POST = json_decode(file_get_contents("php://input"), true);
        }
    }
    
    // 构建URL
    protected function buildUrl($uri, $params = array())
    {
        $base_url = $this->base_url;
        $base_url = rtrim($base_url, '/');
        $uri      = ltrim($uri, '/');
        $url      = "{$base_url}/$uri";
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
    
    // get request input.
    public static function getInput()
    {
        return file_get_contents('php://input');
    }
    
    // 检测是否是https请求
    public static function isHttps()
    {
        if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') {
            return true;
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
            return true;
        } elseif (!empty($_SERVER['HTTP_FRONT_END_HTTPS']) && strtolower($_SERVER['HTTP_FRONT_END_HTTPS']) !== 'off') {
            return true;
        }
        return isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '443';
    }

    public static function __callStatic($method, $args)
    {
        static $obj = null;
        if ($obj == null) {
            $obj = new self();
        }
        return call_user_func_array(array($obj, $method), $args);
    }

    // 获取原始的请求体
    public static function getRawBody()
    {
        $body = null;
        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            //echo "{$_SERVER['REQUEST_METHOD']} != 'POST'";
            return $body;
        }
        $content_type = $_SERVER['CONTENT_TYPE'];
        switch (true) {
            case 'application/x-www-form-urlencoded' == $content_type:
                $body = file_get_contents("php://input");
                break;
            case preg_match('#^multipart/form-data; boundary=(.*)#', $content_type, $match):
                //rebuild it according $_POST, $_FILE
                $body = self::buildMultipartFormData($_POST, $_FILES, $match[1]);
                break;
            default:
                //throw new Exception('expected');
        }
        return $body;
    }

    // 构建请求
    public static function buildMultipartFormData($assoc, $files, &$boundary = '')
    {
        // invalid characters for "name" and "filename"
        static $disallow = array("\0", "\"", "\r", "\n");
        $body            = array();
        // build normal parameters
        foreach ($assoc as $k => $v) {
            $k      = str_replace($disallow, "_", $k);
            $body[] = implode("\r\n", array(
                "Content-Disposition: form-data; name=\"{$k}\"",
                "",
                filter_var($v),
            ));
        }
        // build file parameters
        foreach ($files as $k => $v) {
            if (is_array($v)) {
                $filepath = $v['tmp_name'];
                $filename = $v['name'];
                $type     = $v['type'];
            } else {
                $type     = 'application/octet-stream';
                $filepath = $filepath = $v;
            }
            switch (true) {
                case false === $filepath = realpath(filter_var($filepath)):
                case !is_file($filepath):
                case !is_readable($filepath):
                    continue; // or return false, throw new InvalidArgumentException
            }
            $data     = file_get_contents($filepath);
            $filename = call_user_func("end", explode(DIRECTORY_SEPARATOR, $filename));
            $k        = str_replace($disallow, "_", $k);
            $filename = str_replace($disallow, "_", $filename);
            $body[]   = implode("\r\n", array(
                "Content-Disposition: form-data; name=\"{$k}\"; filename=\"{$filename}\"",
                "Content-Type: $type",
                "",
                $data,
            ));
        }

        // generate safe boundary
        if (!$boundary) {
            do {
                $boundary = "---------------------" . md5(mt_rand() . microtime());
            } while (preg_grep("/{$boundary}/", $body));
        }

        // add boundary for each parameters
        array_walk($body, function (&$part) use ($boundary) {
            $part = "--{$boundary}\r\n{$part}";
        });

        // add final boundary
        $body[] = "--{$boundary}--";
        $body[] = ""; //append last \r\n
        return implode("\r\n", $body);
    }

    //判断是否是ajax请求
    public static function isAjax()
    {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            return true;
        }
    }

    //获取当前URL
    public function getCurrentUrl($with_host = true)
    {
        $uri = $this->uri;
        $host = self::getHeader('Host');
        $scheme = self::isHttps() ? 'https' : 'http';
        if ($with_host) {
            return "{$scheme}://{$host}{$uri}";
        } else {
            return $uri;
        }
    }

    // 获取客户端IP
    public static function getClientIp()
    {
        $ip = 'no client ip';
        if (!empty($_SERVER["HTTP_X_FORWARDED_FOR"])) {
            $ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
        } elseif (!empty($_SERVER["HTTP_CLIENT_IP"])) {
            $ip = $_SERVER["HTTP_CLIENT_IP"];
        } elseif (!empty($_SERVER["REMOTE_ADDR"])) {
            $ip = $_SERVER["REMOTE_ADDR"];
        } elseif ($ip = getenv('HTTP_X_FORWARDED_FOR')) {
        } elseif ($ip = getenv('HTTP_CLIENT_IP')) {
        } elseif ($ip = getenv('REMOTE_ADDR')) {}
        return $ip;
    }

    // 获取所有的HEADER
    public static function getHeader($key = '')
    {
        $headers    = array();
        $extra_keys = array(
            'CONTENT_TYPE',
            'CONTENT_LENGTH',
        );
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_' || in_array($name, $extra_keys)) {
                $_key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))));
                if (!$key) {
                    $headers[$_key] = $value;
                } elseif ($_key == $key) {
                    return $value;
                }
            }
        }
        return $key ? '' : $headers;
    }

    // 获取输出格式
    private static function getFormat()
    {
        // force_format
        $format = self::get('_format');
        if (empty($format) && isset($_SERVER['REQUEST_URI'])) {
            $path   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            $format = pathinfo($path, PATHINFO_EXTENSION);
        }
        // detect HTTP_ACCEPT.
        if (empty($format) && isset($_SERVER['HTTP_ACCEPT'])) {
            // text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8
            $accept = trim($_SERVER['HTTP_ACCEPT']);
            if (preg_match('#[^/,]*/([^/,]*)#', $accept, $match)) {
                $index = stripos($match[1], '+');
                $format = trim($index === false ? $match[1] : substr($match[1], 0, $index));
            }
        }
        
        return $format ? strtolower($format) : 'html';
    }

    //获取Get数据
    public static function get($key = null)
    {
        if (empty($key)) {
            return $_GET;
        }
        return isset($_GET[$key]) ? $_GET[$key] : null;
    }

    //获取POST数据
    public static function post($key = null, $check_method = true)
    {
        if ($check_method && $_SERVER['REQUEST_METHOD'] != 'POST') {
            throw new Exception("非POST请求方法");
        }
        if (empty($key)) {
            return $_POST;
        }
        return isset($_POST[$key]) ? $_POST[$key] : null;
    }

    //获取COOKIE数据
    public static function cookie($key = null)
    {
        if (empty($key)) {
            return $_COOKIE;
        }
        return isset($_COOKIE[$key]) ? $_COOKIE[$key] : null;
    }

    //按$_GET, $_POST, $_COOKIE 顺序获取值
    public static function gpc($key = null)
    {
        if (empty($key)) {
            return $_REQUEST;
        }
        foreach (array($_GET, $_POST, $_COOKIE) as $params) {
            if (isset($params[$key])) {
                return $params[$key];
            }
        }
        return null;
    }
}
