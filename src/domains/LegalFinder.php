<?php

namespace water\domains;

use water\api\Aylien;

class LegalFinder{
    public static $legalwords = [
        "terms",
        "term",
        "privacy",
        "policy",
        //"policies", Bad idea as it usually gives a page with links
        "conditions",
        "condition",
        "agreement",
        "legal",
        " tos ", //Don't match "photos"

    ];
    public static function getLegalDomain($domain){
        $file = @file_get_contents("http://" . $domain);
        return ($file !== false ? LegalFinder::getLegal($file, "http://" . $domain) : false);
    }
    /*
     * Do NOT call this function to get a URL, use getLegalURL
     */
    public static function getLegal($text, $url = null){
        $dom = new \DOMDocument();
        @$dom->loadHTML($text);
        $links = $dom->getElementsByTagName('a');
        $final = [];
        foreach($links as $link){
            if($link instanceof \DOMNode) {
                $path = $link->attributes->getNamedItem("href");
                if($path === null) continue;
                $path = $path->value;
                foreach (LegalFinder::$legalwords as $word) {
                    if (strpos(strtolower($link->textContent), $word) !== false) {
                        $path = strpos($path, '/') === 0 ? $url . $path : $path; //Handle relative links
                        $text = LegalFinder::getTextURL($path);
                        if ($text === false) continue;
                        $params = array(
                            'text' => $text,
                            'title' => $link->textContent,
                        );
                        $summary = LegalFinder::prepare_sentence_array(Aylien::call_api('summarize', $params)["sentences"]);
                        $final[] = [
                            "name" => $link->textContent,
                            "url" => $path,
                            "updated" => time(),
                            "summary" => $summary, //Hopefully works
                            "active" => true,
                            "words" => LegalFinder::getWordFrequency($text)
                        ];
                        break;
                    }
                }
            }
        }
        return $final;

    }
    public static function getUpdatedDoc($url, $name){
        //TODO:Implement actual title
        $text = LegalFinder::getTextURL($url);
        $params = array(
            'text' => $text,
            'title' => $name
        );
        //var_dump(Aylien::call_api('summarize',$params));
        $summary = LegalFinder::prepare_sentence_array(Aylien::call_api('summarize', $params)["sentences"]);
        if($text !== false){
            return [
                "summary" => $summary, //Hopefully works
                "updated" => time(),
                "active" => true,
                "words" => LegalFinder::getWordFrequency($text)
            ];
        }
        else{
            return false;
        }
    }
    public static function getTextURL($url){
        $params = array(
            'url' => $url,
        );
        $text = Aylien::call_api('extract', $params);
        if($text["article"] !== null){
            $finaltext = str_replace("\n", "", $text["article"]);
            return $finaltext;
        }else{
            $html = file_get_contents($url);
            if(($pos = strpos($html, "</head>")) !== false){
                $html = substr($html, $pos+7);
            }
            $html = preg_replace("`<a\b[^>]*>(.*?)</a>`", "", $html);
            $html = preg_replace("`<script\b[^>]*>(.*?)</script>`", "", $html);
            $html = preg_replace("`<select\b[^>]*>(.*?)</select>`", "", $html);
            $html = strip_tags($html);
            $html = LegalFinder::decode($html);
            return $html;
        }
    }
    public static function getWordFrequency($string){
        $n_words = preg_match_all('/([a-zA-Z]|\xC3[\x80-\x96\x98-\xB6\xB8-\xBF]|\xC5[\x92\x93\xA0\xA1\xB8\xBD\xBE]){4,}/', $string, $match_arr);
        $arr = $match_arr[0];
        $ret =  [];
        foreach($arr as $word){
            if(isset($ret[$word])){
                $ret[$word]++;
            }
            else{
                $ret[$word] = 1;
            }
        }
        return $ret;
    }
    public static function decode($string){
        return utf8_encode(html_entity_decode(htmlentities($string, ENT_QUOTES, 'UTF-8'), ENT_QUOTES , 'ISO-8859-15'));
    }
    public static function prepare_sentence_array(array $in){
        $out = [];
        foreach($in as $item){
            $out[] = ["up" => [], "down" => [], "sentence" => htmlentities($item)];
        }
        return $out;
    }
}
