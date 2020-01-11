<?php
/**
 * Created by PhpStorm.
 * User: Tioncico
 * Date: 2019/11/20 0020
 * Time: 15:28
 */
include "../../vendor/autoload.php";
include "../../phpunit2.php";

go(function () {

    $config = new \EasySwoole\Oss\AliYun\Config([
        'accessKeyId'     => ACCESS_KEY_ID,
        'accessKeySecret' => ACCESS_KEY_SECRET,
        'endpoint'        => END_POINT,
    ]);
    $client = new \EasySwoole\Oss\AliYun\OssClient($config);
//    $data = $client->signUrl(OSS_BUCKET,'oss-test.jpg');
//    var_dump($data);
    try{
        var_dump($client->listBuckets());

    }catch (\EasySwoole\Oss\AliYun\Core\OssException $throwable){
        var_dump($throwable->getMessage());
        var_dump($throwable->getErrorCode());
    }


//    $ossClient = new \OSS\OssClient(
//        ACCESS_KEY_ID,
//        ACCESS_KEY_SECRET,
//        END_POINT, false);
//    $timeout = 3600;
//    $options = array(
//        \OSS\OssClient::OSS_PROCESS => "image/resize,m_lfit,h_100,w_100" );
//    $data = $ossClient->signUrl(OSS_BUCKET,'oss-test.jpg');
//    var_dump($data);

});