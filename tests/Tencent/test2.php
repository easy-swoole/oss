<?php
/**
 * Created by PhpStorm.
 * User: Tioncico
 * Date: 2019/12/3 0003
 * Time: 9:40
 */
include "../../vendor/autoload.php";
include "../../phpunit2.php";

$bucket = TX_BUCKET;
$region = TX_REGION;
$bucket2 = "tmp" . $bucket;

$cosClient = new \Qcloud\Cos\Client(
    [
        'region'      => $region,
        'credentials' => [
            'appId'  => TX_APP_ID,
            'secretId'  => TX_SECRETID,
            'secretKey' => TX_SECRETKEY
        ]
    ]
);
try {
    $data = $cosClient->createBucket(['Bucket' => $bucket]);
    var_dump($data);
} catch (\Exception $e) {
    var_dump((string)$e);
}