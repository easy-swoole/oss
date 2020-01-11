<?php
/**
 * Created by PhpStorm.
 * User: Tioncico
 * Date: 2019/11/29 0029
 * Time: 9:30
 */
include "../../vendor/autoload.php";
include "../../phpunit2.php";

$auth = new \Qiniu\Auth(QINIU_ACCESS_KEY,QINIU_SECRET_KEY);

$bucketManager = new \Qiniu\Storage\BucketManager($auth);
list($list, $error) = $bucketManager->buckets();
var_dump($list,$error);