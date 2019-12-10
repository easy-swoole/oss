<?php
include "../../vendor/autoload.php";
include "../../phpunit2.php";
go(function (){

    $config = new \EasySwoole\Oss\Tencent\Config([
        'appId'     => TX_APP_ID,
        'secretId'  => TX_SECRETID,
        'secretKey' => TX_SECRETKEY,
        'region'    => TX_REGION,
        'bucket'    => TX_BUCKET,
    ]);
    $cosClient = new \EasySwoole\Oss\Tencent\OssClient($config);

    $key = '你好.txt';
    $body = generateRandomString(2 * 1024  + 1023);
    $md5 = base64_encode(md5($body, true));
    $cosClient->upload($bucket = TX_BUCKET,
        $key = $key,
        $body = $body,
        $options = ['PartSize' => 1024 + 1]);

    $rt = $cosClient->getObject(['Bucket' => TX_BUCKET, 'Key' => $key]);
    $download_md5 = base64_encode(md5($rt['Body'], true));
});


function generateRandomString($length = 10)
{
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $randomString;
}