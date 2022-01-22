<?php

namespace App\Classes;

use SimpleXMLElement;

class Parser {

    public static function array_to_xml($data){
        $xml = new SimpleXMLElement('<root/>');
        array_walk_recursive($data, array ($xml, 'addChild'));
        return $xml->asXML();
    }

}