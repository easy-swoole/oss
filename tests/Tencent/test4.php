<?php
/**
 * Created by PhpStorm.
 * User: Tioncico
 * Date: 2019/12/6 0006
 * Time: 15:18
 */

include "../../vendor/autoload.php";
include "../../phpunit2.php";

go(function (){
   $client = new \EasySwoole\Oss\Tencent\Http\HttpClient();
   $client->setUrl("http://tioncico-test-1253459008.cos.ap-beijing-1.myqcloud.com");
  $client->put();



});