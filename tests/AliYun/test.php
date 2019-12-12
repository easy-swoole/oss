<?php
/**
 * Created by PhpStorm.
 * User: Tioncico
 * Date: 2019/11/20 0020
 * Time: 15:28
 */
include "../../vendor/autoload.php";
include "../../phpunit2.php";

go(function (){

    $config = new \EasySwoole\Oss\AliYun\Config([
        'accessKeyId'     => ACCESS_KEY_ID,
        'accessKeySecret' => ACCESS_KEY_SECRET,
        'endpoint'        => END_POINT,
    ]);
    $client = new \EasySwoole\Oss\AliYun\OssClient($config);
    $data = $client->uploadFile('tioncicoxyz','test1112.jpg','/www/easyswoole/tioncico_oss/tests/Img/test.jpg');
    var_dump($data);
});