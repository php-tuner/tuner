<?php
// Copyright 2016 The PHP Tuner Authors. All rights reserved.
// Use of this source code is governed by a GPL-3.0
// license that can be found in the LICENSE file.

/*
https://gist.github.com/pokeb/10590
https://github.com/guzzle/guzzle/blob/master/src/Cookie/SetCookie.php
https://github.com/guzzle/guzzle3/blob/master/src/Guzzle/Parser/Cookie/CookieParser.php
*/
class Cookie
{
    
    // 属性列表和默认值
    private static $defaults = array(
        'Name'     => null, // 名称
        'Value'    => null, // 值
        'Domain'   => null, // 所属域
        'Path'     => '/',  // 路径
        'Max-Age'  => null, // 有效时间
        'Expires'  => null, // 过期时间
        'Secure'   => false, // 安全
        'Discard'  => false,
        'HttpOnly' => false,
    );
    
    // 解析 cookie 字符串
    public static function parse($cookie)
    {
        if (is_array($cookie)) {
            $result = array();
            foreach ($cookie as $c) {
                $result[] = self::parse($c);
            }
            return $result;
        }
        // Create the default return array
        $data = self::$defaults;
        // Explode the cookie string using a series of semicolons
        $pieces = array_filter(array_map('trim', explode(';', $cookie)));
        // The name of the cookie (first kvp) must include an equal sign.
        if (empty($pieces) || !strpos($pieces[0], '=')) {
            return new self($data);
        }
        // Add the cookie pieces into the parsed data array
        foreach ($pieces as $part) {
            $cookieParts = explode('=', $part, 2);
            $key = trim($cookieParts[0]);
            $value = isset($cookieParts[1]) ? trim($cookieParts[1], " \n\r\t\0\x0B") : true;
            // Only check for non-cookies when cookies have been found
            if (empty($data['Name'])) {
                $data['Name'] = $key;
                $data['Value'] = $value;
            } else {
                foreach (array_keys(self::$defaults) as $search) {
                    if (!strcasecmp($search, $key)) {
                        $data[$search] = $value;
                        continue 2;
                    }
                }
                $data[$key] = $value;
            }
        }
        return $data;
    }
}
