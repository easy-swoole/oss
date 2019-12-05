<?php
/**
 * Created by PhpStorm.
 * User: Tioncico
 * Date: 2019/12/3 0003
 * Time: 9:40
 */
include "../../vendor/autoload.php";
include "../../phpunit2.php";

$url = 'http://www.php20.cn/aaa/aaa?ddd=1&aaa=2';

var_dump(setUrl($url));

function setUrl(string $url)
{
    $info = parse_url($url);
    if (empty($info['scheme'])) {
        $info = parse_url('//' . $url); // 防止无scheme导致的host解析异常 默认作为http处理
    }
    return new \EasySwoole\HttpClient\Bean\Url($info);
}