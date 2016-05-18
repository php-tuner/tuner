<?php
/**
 *
 * 过滤器
 * @author zhanhengmin@vread.cn
 * @date 2015-12-23
 *
 */
class Filter {

	//过滤xss
	public static function xss($html_content) {
		static $security = null;
		if ($security == null) {
			$security = new Security();
		}
		return $security->xss_clean($html_content);
	}

}