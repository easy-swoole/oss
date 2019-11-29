<?php
/**
 * Created by PhpStorm.
 * User: Tioncico
 * Date: 2019/11/29 0029
 * Time: 9:30
 */
include "../../vendor/autoload.php";
include "../../phpunit.php";
go(function (){
    $auth = new \EasySwoole\Oss\QiNiu\Auth(QINIU_ACCESS_KEY,QINIU_SECRET_KEY);

    $key = 'formPutFileTest';
    $token = $auth->uploadToken('tioncico', $key);
    $upManager = new \EasySwoole\Oss\QiNiu\Storage\UploadManager();
    list($ret, $error) = $upManager->putFile($token, $key, __file__, null, 'text/plain', null);
    var_dump($ret,$error);
});