<?php
// Copyright 2016 The PHP Tuner Authors. All rights reserved.
// Use of this source code is governed by a GPL-3.0
// license that can be found in the LICENSE file.

// HTTP 常用操作
class Http
{
    // 默认User-Agent
    // public static $UA = "HTTP CLIENT(PHP)";
    public static $UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130 Safari/537.36';

    // build url
    public static function buildUrl($url, $params = array(), $fragment = '')
    {
        $url = trim($url);
        $fragment = trim($fragment);
        if(empty($params)){
            return $url;
        }
        if(!is_array($params)){
            throw new Exception('params is not array.');
        }
        $url = rtrim($url, '?');
        $query_string = http_build_query($params, '', '&');
        if(!empty($fragment)){
            $query_string .= "#{$fragment}";
        }
        if(strpos($url, '?') === false){
            return $url.'?'.$query_string;
        }
        return $url.'&'.$query_string;
    }

    // 获取处理器
    public static function getHandler()
    {
        if (function_exists('curl_init')) {
            return 'curlRequest';
        }
        return 'socketRequest';
    }

    // 构建header, 最终形式
    // array("Content-type: text/html", "Key: Value");
    public static function buildHeader($headers)
    {
        $result = array();
        foreach ($headers as $key => $val) {
            if (!is_int($key)) {
                $val = "{$key}: {$val}";
            }
            $result[] = str_replace("\r\n", '', $val);
        }
        return $result;
    }

    // 生成规范化的key: Content-Type
    public static function canonicalHeaderKey($key)
    {
        $len    = strlen($key);
        $uppper = true;
        for ($i = 0; $i < $len; $i++) {
            if ($uppper) {
                $key[$i] = strtoupper($key[$i]);
            }
            $uppper = $key[$i] == '-' ? true : false;
        }
        return $key;
    }

    // 解析header头
    public static function parseHeaders($raw_headers)
    {
        $headers = array();
        $key     = '';
        foreach (explode("\n", $raw_headers) as $i => $h) {
            $h = explode(':', $h, 2);
            if (isset($h[1])) {
                //record it
                $key = self::canonicalHeaderKey($h[0]);
                if (isset($headers[$key])) {
                    if (!is_array($headers[$key])) {
                        $headers[$key] = array($headers[$key]);
                    }
                    $headers[$key] = array_merge($headers[$key], array(trim($h[1])));
                } else {
                    $headers[$key] = trim($h[1]);
                }
            } else {
                if (substr($h[0], 0, 1) == "\t") {
                    $headers[$key] .= "\r\n\t" . trim($h[0]);
                } elseif (!$key) {
                    $headers[0] = trim($h[0]);
                }
            }
        }
        return $headers;
    }

    // GET请求
    public static function get($url, $headers = array(), $connect_timeout = 5, $read_timeout = 5)
    {
        $handler = self::getHandler();
        return self::$handler($url, null, $headers, true, $connect_timeout, $read_timeout);
    }

    // POST请求
    public static function post($url, $params = array(), $headers = array(), $connect_timeout = 5, $read_timeout = 5)
    {
        $handler = self::getHandler();
        return self::$handler($url, $params, $headers, true, $connect_timeout, $read_timeout);
    }

    /**
     *
     * ASYNC REQUEST
     *
     */
    public static function asyncRequest($url, $params = array())
    {
        if (function_exists('exec')) {
            if ($params) {
                $params_string = http_build_query($params, '', '&');
                $params        = " -d '$params_string' ";
            } else {
                $params = '';
            }
            $curl_cmd = "curl -s '$url' $params > /dev/null 2>&1 &";
            return exec($curl_cmd);
        }
        
        return self::socketRequest($url, $params, false);
    }

    /**
     * SOCKET 请求
     *
     * @return void
     * @author Heng Min Zhan
     */
    public static function socketRequest($url, $params = array(), $headers = array(), $wait_result = true, $connect_timeout = 1, $read_timeout = 3, $max_redirect = 5)
    {
        $crlf   = "\r\n";
        $method = 'GET';
        
        if ($params) {
            $method = 'POST';
        }
        
        $parts = parse_url($url);
        if (!isset($parts['path'])) {
            $parts['path'] = '/';
        }
        
        if (!isset($headers['User-Agent'])) {
            $headers['User-Agent'] = self::$UA;
        }
        
        $out   = array();
        $out[] = "$method {$parts['path']}?{$parts['query']} HTTP/1.1";
        $out[] = "Host: {$parts['host']}";
        $out[] = "Connection: Close";

        if (function_exists('gzinflate')) {
            $headers["Accept-Encoding"] = "gzip,deflate";
        }
        
        $post_string = '';
        if ($method == 'POST') {
            if (is_array($params)) {
                $headers['Content-Type'] = "application/x-www-form-urlencoded";
                $post_string             = http_build_query($params, '', '&');
            } else {
                $post_string = trim($params);
            }
            //must have it
            $headers['Content-Length'] = strlen($post_string);
        }
        
        if ($headers) {
            $out = array_merge($out, self::buildHeader($headers));
        }
        
        //header end
        $out[] = '';
        $out[] = $post_string;
        if (!isset($parts['port'])) {
            $parts['port'] = $parts['scheme'] == 'https' ? 443 : 80;
        }
        
        if ($parts['scheme'] == 'https') {
            $hostname = "ssl://{$parts['host']}";
        } else {
            $hostname = $parts['host'];
        }
        
        $fp = fsockopen($hostname, $parts['port'], $errno, $errstr, $connect_timeout);
        if (!$fp) {
            return new HttpResponse('', '', '', new Exception("url: $url, error: $errstr", $errno));
        }
        
        $string  = implode($crlf, $out);
        $str_len = strlen($string);
        for ($written = 0, $fwrite = 0; $written < $str_len; $written += $fwrite) {
            $fwrite = fwrite($fp, substr($string, $written));
            if ($fwrite === false) {
                break;
            }
        }
        
        if ($fwrite != $str_len) {
            fclose($fp);
            return new HttpResponse('', '', '', new Exception("url: $url, error: $fwrite, $str_len "));
        }
        
        //fwrite($fp, implode($crlf, $out));
        //repsonse header
        $headers     = array();
        $body        = '';
        $http_status = array();
        
        if ($wait_result) {
            
            stream_set_timeout($fp, $read_timeout);
            //read and parse header
            list($http_status['version'], $http_status['code'], $http_status['desc']) = explode(' ', trim(fgets($fp, 256)));
            $header_lines                                                             = '';
            while (!feof($fp)) {
                $line = fgets($fp, 256);
                if (!trim($line)) {
                    break;
                }
                $header_lines .= $line;
                //list($key, $val) = explode(':', $line, 2);
                //$headers[$key] = trim($val);
            }
            
            $headers = self::parseHeaders($header_lines);
            
            //read body
            //分块传输编码只在HTTP协议1.1版本（HTTP/1.1）中提供
            if (isset($headers['Transfer-Encoding']) && $headers['Transfer-Encoding'] == 'chunked') {
                while (!feof($fp)) {
                    $line = fgets($fp, 1024);
                    
                    if ($line && preg_match('/^([0-9a-f]+)/i', $line, $matches)) {
                        
                        $len = hexdec($matches[1]);
                        
                        if ($len == 0) {
                            break; //maybe have some other header
                        }
                        
                        while ($len > 0) {
                            $tmp_data = fread($fp, $len);
                            $body .= $tmp_data;
                            $len = $len - strlen($tmp_data);
                        };
                    }
                }
            } elseif (isset($headers['Content-Length']) && $len = $headers['Content-Length']) {
                while ($len > 0) {
                    $tmp_data = fread($fp, $len);
                    $body .= $tmp_data;
                    $len = $len - strlen($tmp_data);
                };
            } else {
                while (!feof($fp)) {
                    $body .= fread($fp, 1024 * 8);
                }
            }
            
            if ($body && isset($headers['Content-Encoding']) && $headers['Content-Encoding'] == 'gzip') {
                $body = gzinflate(substr($body, 10));
            }
        }
        
        $info = stream_get_meta_data($fp);
        fclose($fp);
        if ($info['timed_out']) {
            return new HttpResponse($http_status, $headers, $body, new Exception("Connection timed out!"));
        }
        
        // 重定向处理
        if (substr($http_status['code'], 0, 1) == 3 && $max_redirect > 0 && isset($headers['Location']) && filter_var($headers['Location'], FILTER_VALIDATE_URL)) {
            return self::socketRequest($headers['Location'], $params = array(), $headers = array(), $wait_result, $connect_timeout, $read_timeout, --$max_redirect);
        }
        
        //$uri = $info['uri'];
        //$result = array('http_status' => $http_status, 'header' => $headers, 'body' => $body);
        return new HttpResponse($http_status, $headers, $body);
    }

    /**
     *
     * CURL REQUEST
     *
     */
    public static function curlRequest($url, $params = array(), $headers = array(), $wait_result = true, $connect_timeout = 1, $read_timeout = 3, $max_redirect = 5)
    {
        $re = self::multiCurl(array(
            array(
                'url'     => $url,
                'params'  => $params,
                'headers' => $headers,
            ),
        ), true, $connect_timeout, $read_timeout, $max_redirect);
        //$result = is_array($re) && isset($re[$url]) ? $re[$url]['result'] : '';
        return current($re);
    }

    public static function getCurlInstance($url, $connect_timeout = 2, $read_timeout = 3, $max_redirect = 2)
    {
        $ch = curl_init($url);
        
        if (!is_resource($ch)) {
            return false;
        }
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        //curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, $connect_timeout * 1000);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, ($read_timeout + $connect_timeout) * 1000);
        curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
        
        if ($max_redirect >= 0) {
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_AUTOREFERER, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, $max_redirect);
        }
        
        return $ch;
    }

    /**
    * @purpose: 使用curl并行处理url
    * @return: array 每个url获取的数据
    * @param: $urls array url列表
    * @param: $callback string 需要进行内容处理的回调函数。示例：func(array)
	*/
    public static function multiCurl($request_list, $callback = '', $connect_timeout = 1, $read_timeout = 3, $max_redirect = 2)
    {
        $response = array();
        
        if (empty($request_list)) {
            return array(new HttpResponse('', '', '', new Exception("无效请求(request_list empty)")));
        }
        
        $chs = curl_multi_init();
        
        // 使用HTTP长连接(启用后用时反而会增长！)
        /*if (function_exists("curl_multi_setopt")) {
			curl_multi_setopt($chs, CURLMOPT_PIPELINING, 1);
		}*/
            
        $curl_list = array();
        foreach ($request_list as $req) {
            
            // list($url, $params, $headers) = array_values($req);
            $req_arr = array_values($req);
            $url = empty($req_arr[0]) ? '' : $req_arr[0];
            $params = empty($req_arr[1]) ? array() : $req_arr[1];
            $headers = empty($req_arr[2]) ? array() : $req_arr[2];
            
            $ch = self::getCurlInstance($url, $connect_timeout, $read_timeout, $max_redirect);
            
            // disable expect header, some server not surpport it
            $headers[] = 'Expect:';
            curl_setopt($ch, CURLOPT_HTTPHEADER, self::buildHeader($headers));
            curl_setopt($ch, CURLOPT_USERAGENT, self::$UA);
            
            // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            // 禁用 @ 前缀在 CURLOPT_POSTFIELDS 中发送文件(php >= 5.5.0)
            if (defined('CURLOPT_SAFE_UPLOAD')) {
                curl_setopt($ch, CURLOPT_SAFE_UPLOAD, true);
            }
            
            $force_urlencoded = true;
            // 没有上传文件是强制 application/x-www-form-urlencoded 编码
            if (class_exists('CURLFile') && is_array($params)) {
                foreach ($params as $_v) {
                    if ($_v instanceof CURLFile) {
                        $force_urlencoded = false;
                        break;
                    }
                }
            }
            
            if ($force_urlencoded && !is_string($params) && $params) {
                // 如果有子段是@开头, php curl 会解析成需要上传文件，而且如果没有严格的用户输入过滤，可能会带来安全问题。
                // 所以我们转换成字符串，禁止用@方式上传文件。
                $params = http_build_query((array) $params, '', '&');
            }
            
            if ($params) {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
            }
            
            curl_multi_add_handle($chs, $ch);
            $curl_list[] = $ch;
            $response[] = array(); // 初始化空，保证后边排序正确
        }
        // $callback = trim($callback);
        do {
            $status = curl_multi_exec($chs, $active);
            // Solve CPU 100% usage, a more simple and right way:
            curl_multi_select($chs); //default timeout 1.
        } while ($status === CURLM_CALL_MULTI_PERFORM || $active);
        
        if ($callback && $status == CURLM_OK) {
            
            while ($done = curl_multi_info_read($chs)) {
                // http://php.net/curl_getinfo
                $handle = $done["handle"];
                $info  = curl_getinfo($done["handle"]);
                
                $error = curl_error($done["handle"]);
                // wrong may be still have body data
                $result = curl_multi_getcontent($done["handle"]);
                
                $http_status  = array();
                $body = null;
                $headers = array();
                
                if(!empty($info['header_size']) && $result){
                    $body   = substr($result, $info['header_size']);
                    list($http_line, $header_lines) = explode("\n", substr($result, 0, $info['header_size']), 2);
                    $headers      = self::parseHeaders($header_lines);
                    $http_info = explode(' ', trim($http_line));
                    $http_status['version'] = empty($http_info[0]) ? '' : $http_info[0];
                    $http_status['code']    = empty($http_info[1]) ? '' : $http_info[1];
                    $http_status['desc']    = empty($http_info[2]) ? '' : $http_info[2];
                }
                
                if ($error || !in_array($info['http_code'], array(200))) {
                    $rtn = new HttpResponse(
                        $http_status,
                        $headers,
                        $body, 
                        new Exception("url:{$info['url']}, error:$error, info:" . print_r($info, true))
                    );
                    // throw new Exception($error);
                } else {
                    // compact('info', 'error', 'result');
                    $rtn = new HttpResponse($http_status, $headers, $body, null, $info['url']);
                }
                
                if (is_callable($callback)) {
                    $callback($rtn);
                } else {                    
                    
                    // 返回值保持 request_list 的顺序
                    $index = array_search($handle, $curl_list);
                    $response[$index] = $rtn;
                }
            }
        }
        
        // remove and close all sub curl instanc
        foreach ($curl_list as $ch) {
            curl_multi_remove_handle($chs, $ch);
            curl_close($ch);
        }
        
        curl_multi_close($chs);
        return $response;
    }
    
    // 解析 conent_type header val
    public static function parseContentType($content_type)
    {
        $info = array(
            'mine_type' => '',
        );
        
        foreach (explode(';', $content_type) as $v) {
            switch (true) {
                case stripos($v, '=') > 0:
                    $va = explode('=', $v, 2);
                    $info[trim($va[0])] = trim($va[1]);
                    break;
                case preg_match('#.*/.*#i', $v, $match):
                    $info['mine_type'] = trim($v);
                    break;
                default:
                    // dirty val
                    $info[] = $v;
            }
        }
        
        return $info;
    }
}

class HttpResponse
{

    public $status = array();
    public $header = array();
    public $body   = '';
    public $error  = null;
    private $url    = '';

    public function __construct($status, $header, $body, $error = null, $url = '')
    {
        $charset = '';
        if (isset($header['Content-Type'])) {
            // Todo: use $this->getHeader ?
            $content_type = is_array($header['Content-Type']) ? end($header['Content-Type']) : $header['Content-Type'];
            if (preg_match('/charset=([^;]*)/i', $content_type, $match)) {
                $charset = $match[1];
            }
        }
        
        if ($charset && strtoupper($charset) != 'UTF-8') {
            $body = mb_convert_encoding($body, 'UTF-8', $charset);
        }
        
        $this->status = $status;
        $this->header = $header;
        $this->body   = $body;
        $this->error  = $error;
        $this->url    = $url;
        
        if ($error != null) {
            Log::file($this->error(), 'http_request');
        }
    }

    // 获取 header 信息
    public function getHeader($key, $single = true)
    {
        $key = Http::canonicalHeaderKey($key);
        if (!isset($this->header[$key])) {
            return '';
        }
        
        $val = $this->header[$key];
        if (is_array($val) && $single) {
            return end($val);
        }
        
        return $val;
    }
    
    public function result()
    {
        $content_type = strtolower($this->getHeader('Content-Type'));
        
        if(preg_match('/^application\/json;/i', $content_type)) {
            return $this->json();
        }
        
        return $this->raw();
    }

    // get status
    public function getStatus()
    {
        return $this->status;
    }
    
    // get http code
    public function getHttpCode()
    {
        return empty($this->status['code']) ? '' : $this->status['code'];
    }

    // 获取错误信息
    public function error()
    {
        if (is_object($this->error) && get_class($this->error) == 'Exception') {
            return $this->error->getMessage();
        }
        // maybe wrong
        return @strval($this->error);
    }

    // 将返回内容解析为 json 数组
    public function json()
    {
        return json_decode($this->body, true);
    }
    
    // 将返回内容解析为 xml 数组
    public function xmlToArray()
    {
        $xml_string = trim($this->body);
        if(empty($xml_string)){
            return array();
        }
        libxml_disable_entity_loader(true);
        $xml = simplexml_load_string($xml_string, "SimpleXMLElement", LIBXML_NOCDATA);
        if($xml === false){
            throw new Exception('covert faild.');
        }
        $json = json_encode($xml);
        return json_decode($json, true);
    }
    
    // 获取原始返回内容
    public function raw()
    {
        return $this->body;
    }

    public function _toString()
    {
        return $this->body;
    }
    
    public function getUrl()
    {
        return $this->url;
    }
}
