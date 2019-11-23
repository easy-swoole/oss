# oss

## 阿里云调用
根据阿里云官方sdk修改,全部方法都一致,走通了所有官方的client请求类单元测试,全部调用方法都和阿里云一直
文档可查看阿里云官方文档:https://help.aliyun.com/document_detail/32099.html?spm=a2c4g.11186623.2.17.de715d26YNLCah#concept-32099-zh
```php
<?php
/**
 * Created by PhpStorm.
 * User: Tioncico
 * Date: 2019/11/20 0020
 * Time: 15:28
 */
include "./vendor/autoload.php";
include "./phpunit.php";

go(function (){

    $config = new \EasySwoole\Oss\AliYun\Config([
        'accessKeyId'     => ACCESS_KEY_ID,
        'accessKeySecret' => ACCESS_KEY_SECRET,
        'endpoint'        => END_POINT,
    ]);
    $client = new \EasySwoole\Oss\AliYun\OssClient($config);
    $data = $client->putObject('tioncicoxyz','test',__FILE__);
    var_dump($data);
});
```