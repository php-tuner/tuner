<?php
// Copyright 2016 The PHP Tuner Authors. All rights reserved.
// Use of this source code is governed by a GPL-3.0
// license that can be found in the LICENSE file.

class DbDriver
{
    // 转义字符
    public function escape($v)
    {
        // If you wonder why (besides \, ' and ")
        // NUL (ASCII 0), \n, \r, and Control-Z are escaped:
        // it is not to prevent sql injection, but to
        // prevent your sql logfile to get unreadable.
        // \，NUL （ASCII 0），\n，\r，'，" 和 Control-Z.
        $search  = array("\\", "\x00", "\n", "\r", "'", '"', "\x1a");
        $replace = array("\\\\", "\\0", "\\n", "\\r", "\'", '\"', "\\Z");
        return str_replace($search, $replace, $v);
    }
}
