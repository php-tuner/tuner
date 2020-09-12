<?php
// Copyright 2016 The PHP Tuner Authors. All rights reserved.
// Use of this source code is governed by a GPL-3.0
// license that can be found in the LICENSE file.

// XML
class Xml
{

    // covert xml string to array
    // option https://github.com/gaarf/XML-string-to-PHP-array/blob/master/xmlstr_to_array.php ?
    public static function toArray($xml_string)
    {        
        $xml_string = trim($xml_string);
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

    /**
     * Encode an object as XML string
     *
     * @param Object $obj
     * @param string $root_node
     * @return string $xml
     */
    public static function encodeObj($obj, $root_node = 'response', $encoding = "utf-8")
    {
        $xml = "<?xml version=\"1.0\" encoding=\"{$encoding}\"?>" . PHP_EOL;
        $xml .= self::encode($obj, $root_node, $depth = 0);
        return $xml;
    }

    /**
     * Encode an object as XML string
     *
     * @param Object|array $data
     * @param string $root_node
     * @param int $depth Used for indentation
     * @return string $xml
     */
    public static function encode($data, $node, $depth = 0)
    {
        $xml = str_repeat("\t", $depth);
        $xml .= "<{$node}>" . PHP_EOL;
        
        if(is_array($data)) {
            foreach ($data as $key => $val) {
                if (is_array($val) || is_object($val)) {
                    $xml .= self::encode($val, $key, ($depth + 1));
                } else {
                    $xml .= str_repeat("\t", ($depth + 1));
                    if(is_numeric($val) || empty($val)) {
                        $xml .= "<{$key}>{$val}</{$key}>" . PHP_EOL;
                    }else{
                        $xml .= "<{$key}><![CDATA[{$val}]]></{$key}>" . PHP_EOL;
                    }
                }
            }    
        }
        
        $xml .= str_repeat("\t", $depth);
        $xml .= "</{$node}>" . PHP_EOL;
        
        return $xml;
    }
}
