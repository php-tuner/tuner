<?php
class Response {
	
	public  $charset = 'UTF-8';
	public  $twig = null;
	private $header = array();
	
	public static function __callStatic($method, $args){
		static $obj = null;
		if($obj == null){
			$obj = new self();
		}
		return call_user_func_array(array($obj, $method), $args);
	}
		
	//初始化
	public function __construct($charset = 'UTF-8'){
		$charset && $this->charset = $charset;
		//ob_start();(启用的时候要考虑，脚本程序)
	}
	
	//设置输出字符编码
	public function setCharset($charset){
		if(!$charset){
			throw new Exception('charset is empty');
		}
		$this->charset = $charset;
	}
	
	//添加header头
	public function addHeader() {
		$args = func_get_args();
		$args_len = count($args);
		switch(true){
			case $args_len == 1 && is_array($args[0]):
				foreach($args[0] as $k => $v){
					$this->header[] = "{$k}:{$v}";
				}
			break;
			case $args_len == 1 && is_string($args[0]):
				$this->header[] = $args[0];
			break;
			case $args_len > 1:
				$this->header[] = implode(':', array_slice($args, 0, 2));
			break;
		}
	}
	
	//跨站请求header 
	public function allowCors($domain= '*', $method = array('POST', 'GET'), $headers = array(), $credentials = true, $max_age = 86400){
		$domain && $this->addHeader('Access-Control-Allow-Origin', $domain);
		$method && $this->addHeader('Access-Control-Allow-Methods', is_array($method) ? implode(',', $method) : $method);
		$max_age && $this->addHeader('Access-Control-Max-Age', $max_age);
		$headers && $this->addHeader('Access-Control-Allow-Headers', is_array($headers) ? implode(',', $headers) : $headers);
		$credentials && $this->addHeader('Access-Control-Allow-Credentials', 'true');
	}
	
	// 重定向
	public function redirect($url, $code = 302) {
		// Log::file("$url", 'redirect');
		header("Location: $url", true, $code);
		$html = <<<HTML
<html>
	<head>
		<meta http-equiv="refresh" content="0;url=$url">
	</head>
	<body>
		<p>Please follow <a href="$url">this link</a>.</p>
	</body>
</html>

HTML;
		$this->html($html);
		exit(0);
	}
	
	//自动识别输出格式
	public function output($data, $format = 'html', $charset = 'UTF-8'){
		switch(strtolower($format)){
			case 'xml':
			case 'json':
			case 'text':
			break;
			default:
				$format = 'html';
		}
		$this->$format($data, $charset);
	}
	
	//输出JSON
	public function json($data, $charset = 'UTF-8'){
		$charset || $charset = $this->charset;
		$data = is_string($data) ? $data : json_encode($data);
		$this->addHeader("Content-Type: application/json; charset={$charset}"); 
		$this->addHeader("Cache-Control: no-cache, must-revalidate");
		$this->addHeader("Pragma: no-cache");		
		$this->_output($data);
	}
	
	//输出jsonp
	public function jsonp($data,  $callback = '', $charset = 'UTF-8'){
		$charset || $charset = $this->charset;
		$callback || $callback = trim($_GET['callback']);
		$data = is_string($data) ? $data : json_encode($data);
		$this->addHeader("Content-Type: application/javascript; charset={$charset}");
		$this->addHeader("Cache-Control: no-cache, must-revalidate");
		$this->addHeader("Pragma: no-cache");
		$data = "{$callback}({$data});";
		$this->_output($data);
	}
	
	//输出HTML
	public function html($data, $charset = 'UTF-8'){
		$charset || $charset = $this->charset;
		$this->addHeader("Content-Type: text/html; charset={$charset}");
		if(!is_string($data)){
			$data = '<pre>'.print_r($data, true).'</pre>';
		}
		$this->_output($data);		
	}
	
	//输出XML
	//Todo: should be implemented
	public function xml($data, $charset = 'UTF-8'){
		$charset || $charset = $this->charset;
		$this->addHeader("Content-Type: text/xml; charset={$charset}");
		$this->_output(XML::encodeObj($data));
	}
	
	//输出纯文本
	public function text($data, $charset = 'UTF-8'){
		$charset || $charset = $this->charset;
		if(!is_string($data)){
			$data = print_r($data, true);
		}
		$this->addHeader("Content-Type: text/txt; charset={$charset}");
		$this->_output($data);
	}
	
	//404 not found
	public function notFound(){
		$str = $_SERVER["SERVER_PROTOCOL"]." 404 Not Found";
		$this->addHeader($str);
		$this->_output($str);
	}
	
	//输出文件
	public function file($file_path, $charset = 'UTF-8') {
		$charset || $charset = $this->charset;
		$mime_type = $this->getMimeType($file_path);
		$content = file_get_contents($file_path);
		$content_type = "Content-type: $mime_type";
		$etag = md5($content);
		$cache_valid = isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] === $etag ? true : false;
		//需要制定编码
		if(in_array($mime_type, array(
			'text/plain',
			'text/html',
			'text/css',
			'application/javascript',
			'application/xml',
		))){
			$encoding = mb_detect_encoding($content, "UTF-8, GBK");
			if($charset && $encoding != $this->charset){
				$content = mb_convert_encoding($content, $charset, $encoding);
			}else{
				$charset = $encoding;
			}
			$content_type .= ", charset={$charset} ";
		}
		$expire_time = 31536000;
		$now_time = time();
		$expire_date = gmdate('D, d M Y H:i:s ', $now_time + $expire_time) . 'GMT';
		$this->addHeader("Expires: $expire_date");
		//fake it
		//$last_modify_date = gmdate('D, d M Y H:i:s ', 0) . 'GMT';
		//cache policy
		//https://developers.google.com/web/fundamentals/performance/optimizing-content-efficiency/http-caching?hl=zh-cn
		$this->addHeader("Cache-Control: public, max-age=$expire_time");
		//$this->addHeader("Last-Modified: $last_modify_date");
		$this->addHeader("ETag: $etag");
		$this->addHeader($content_type);
		// 304 NOT MODIFY
		if($cache_valid){
			$this->addHeader("HTTP/1.1 304 Not Modified");
			$content = '';
		}
		$this->_output($content);
	}
	
	//输出内容
	private function _output($str, $halt = true){
		foreach($this->header as $header){
			header($header, true);
		}
		echo $str;
		$halt && exit(0);
	}
	
	//原生输出
	public function raw($raw_data){
		return $this->_output($raw_data);
	}
	
	//get getMimeType
	private function getMimeType($filename) {
		$mime_types = array(

			'txt' => 'text/plain',
			'htm' => 'text/html',
			'html' => 'text/html',
			'php' => 'text/html',
			'css' => 'text/css',
			'js' => 'application/javascript',
			'json' => 'application/json',
			'xml' => 'application/xml',
			'swf' => 'application/x-shockwave-flash',
			'flv' => 'video/x-flv',

			// images
			'png' => 'image/png',
			'jpe' => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'jpg' => 'image/jpeg',
			'gif' => 'image/gif',
			'bmp' => 'image/bmp',
			'ico' => 'image/vnd.microsoft.icon',
			'tiff' => 'image/tiff',
			'tif' => 'image/tiff',
			'svg' => 'image/svg+xml',
			'svgz' => 'image/svg+xml',

			// archives
			'zip' => 'application/zip',
			'rar' => 'application/x-rar-compressed',
			'exe' => 'application/x-msdownload',
			'msi' => 'application/x-msdownload',
			'cab' => 'application/vnd.ms-cab-compressed',

			// audio/video
			'mp3' => 'audio/mpeg',
			'qt' => 'video/quicktime',
			'mov' => 'video/quicktime',

			// adobe
			'pdf' => 'application/pdf',
			'psd' => 'image/vnd.adobe.photoshop',
			'ai' => 'application/postscript',
			'eps' => 'application/postscript',
			'ps' => 'application/postscript',

			// ms office
			'doc' => 'application/msword',
			'rtf' => 'application/rtf',
			'xls' => 'application/vnd.ms-excel',
			'ppt' => 'application/vnd.ms-powerpoint',

			// open office
			'odt' => 'application/vnd.oasis.opendocument.text',
			'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
		);

		$ext = strtolower(array_pop(explode('.',$filename)));
		if (array_key_exists($ext, $mime_types)) {
			return $mime_types[$ext];
		}elseif (function_exists('finfo_open')) {
			$finfo = finfo_open(FILEINFO_MIME);
			$mimetype = finfo_file($finfo, $filename);
			finfo_close($finfo);
			return $mimetype;
		}else {
			return 'application/octet-stream';
		}
	}
	
	//异常处理(Todo: 调整参数顺序)
	public function handleException($e, $halt = true, $req = null){
		$req == null && $req = new Request();
		$format = $req->format;
		$code = $e->getCode() ? $e->getCode() : 1;
		$msg = $e->getMessage();
		$data = array(
			'error_msg' => $msg,
			'error_code' => $code,
		);
		//$format = $req->format;
		if(!method_exists('Response', $format)){
			$format = 'html';
		}
		//$res = new Response();
		$this->$format($data);
		$halt && exit();
	}
	
}