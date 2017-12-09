<?php
// Copyright 2016 The PHP Tuner Authors. All rights reserved.
// Use of this source code is governed by a GPL-3.0
// license that can be found in the LICENSE file.

// 过滤器
class Filter
{

    //过滤xss
    public static function xss($html_content)
    {
        static $security = null;
        if ($security == null) {
            $security = new Security();
        }
        return $security->xss_clean($html_content);
    }
}
