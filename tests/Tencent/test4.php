<?php
/**
 * Created by PhpStorm.
 * User: Tioncico
 * Date: 2019/12/6 0006
 * Time: 15:18
 */

include "../../vendor/autoload.php";
include "../../phpunit2.php";

go(function () {
    $client = new \EasySwoole\Oss\Tencent\Http\HttpClient();
    $client->setUrl("http://www.php20.cn");
    $client->getClient();
    $client->setUrl('http://tioncico-test-1253459008.cos.ap-beijing-1.myqcloud.com');
    $client->getClient();
    $client->getClient()->host='http://tioncico-test-1253459008.cos.ap-beijing-1.myqcloud.com';
    $response = $client->put();
    var_dump($response);



//PUT /?cors HTTP/1.1
//Host: www.php20.cn
//user-agent: EasySwooleHttpClient/0.1
//accept: */*
//accept-encoding: gzip
//pragma: no-cache
//cache-control: no-cache
//Connection: keep-alive
//Cookie:


});

