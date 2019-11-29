<?php
/**
 * Created by PhpStorm.
 * User: Tioncico
 * Date: 2019/11/26 0026
 * Time: 10:26
 */

namespace EasySwoole\Oss\Tencent\Http;


use EasySwoole\Oss\Tencent\Config;
use EasySwoole\Oss\Tencent\Service;
use EasySwoole\Oss\Tencent\Signature;

class HttpClient extends \EasySwoole\HttpClient\HttpClient
{
    protected $api;
    protected $cosConfig;
    protected $signature;
    protected $extraData;
    protected $service;

    public function __construct(Config $cosConfig,$extraData)
    {
        $this->cosConfig = $cosConfig;
        $this->extraData = $extraData;
        $this->service = Service::getService();
        $this->setHeader('User-Agent', $cosConfig->getUserAgent());
//        if ($this->cosConfig['anonymous'] != true) {
//            $handler->push($this::handleSignature($this->cosConfig['secretId'], $this->cosConfig['secretKey']));
//        }
        $this->setHeader('x-cos-security-token', $cosConfig->getToken());

        $this->signature = new Signature($this->cosConfig->getSecretId(), $this->cosConfig->getSecretKey());
        $this->setUrl($cosConfig->getSchema() . '://cos.' . $this->cosConfig->getRegion() . '.myqcloud.com/');
        $this->setTimeout($cosConfig->getTimeout());
//        $this->setProxyHttp($cosConfig->getProxy());
    }

    function __call($name,array $arguments)
    {
        $operations = $this->service['operations'][$name];
        var_dump($operations);



    }

    /**
     * 操作数据基础的处理
     * operationBaseHandel
     * @param $operations
     * @author Tioncico
     * Time: 10:53
     */
    function operationBaseHandel($operations){
        $this->setMethod($operations['httpMethod']);
//        $this->setUrl();

    }


}