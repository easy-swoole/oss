<?php
/**
 * Created by PhpStorm.
 * User: Tioncico
 * Date: 2019/11/26 0026
 * Time: 10:26
 */

namespace EasySwoole\Oss\Tencent\Http;


use EasySwoole\HttpClient\Bean\Response;
use EasySwoole\HttpClient\Bean\Url;
use EasySwoole\Oss\Tencent\Config;
use EasySwoole\Oss\Tencent\CosTransformer;
use EasySwoole\Oss\Tencent\Exception\OssException;
use EasySwoole\Oss\Tencent\Http\Result;
use EasySwoole\Oss\Tencent\Service;
use EasySwoole\Oss\Tencent\Signature;
use phpDocumentor\Reflection\DocBlock\Serializer;

class HttpClient extends \EasySwoole\HttpClient\HttpClient
{
    protected $cosConfig;
    protected $signature;
    protected $extraData;
    protected $service;

    public function __construct(Config $cosConfig, $extraData)
    {
        $this->cosConfig = $cosConfig;
        $this->extraData = $extraData;
        $this->service = Service::getService();
        $this->setHeader('User-Agent', $cosConfig->getUserAgent());
//        if ($this->cosConfig['anonymous'] != true) {
//            $handler->push($this::handleSignature($this->cosConfig['secretId'], $this->cosConfig['secretKey']));
//        }
        if ($cosConfig->getToken() != null) {
            $this->setHeader('x-cos-security-token', $cosConfig->getToken());
        }

        $this->signature = new Signature($this->cosConfig->getSecretId(), $this->cosConfig->getSecretKey());
        $this->setTimeout($cosConfig->getTimeout());
        $url = $this->cosConfig->getSchema().'://cos.' . $this->cosConfig->getRegion() . '.myqcloud.com';
        $this->setUrl($url);

    }

    public function commandToRequestTransformer(Command $command)
    {
        $action = $command->getName();
        $operation = $this->service['operations'][$action];
        $transformer = new CosTransformer($this->cosConfig, $operation);
        $transformer->bucketStyleTransformer($command, $this);
        $transformer->uploadBodyTransformer($command, $this);
        $transformer->md5Transformer($command, $this);
        $request = $transformer->specialParamTransformer($command, $this);
        return $request;
    }

    public function responseToResultTransformer(Response $response, $name)
    {
        $operations = $this->service['operations'][$name];
        $operationsResult = $this->service['models'][$operations['responseClass']];
        $action = $name;
        if ($action == "GetObject") {
            if (isset($command['SaveAs'])) {
                //写入文件
//                $fp = fopen($command['SaveAs'], "wb");
//                fwrite($fp, $response->getBody());
//                fclose($fp);
            }
        }

        $result = new Result($response, $operationsResult);
//        if ($command['Key'] != null && $response['Key'] == null) {
//            $response['Key'] = $command['Key'];
//        }
//        if ($command['Bucket'] != null && $response['Bucket'] == null) {
//            $response['Bucket'] = $command['Bucket'];
//        }
        $result->Location = $this->url->getHost() . $this->url->getPath();
//        var_dump($response);
        return $result;
    }


    function __call($name, array $args)
    {
        $args = isset($args[0]) ? $args[0] : [];
        $command = $this->getCommand($name, $args);
        $this->commandToRequestTransformer($command);

        //请求数据生成加密
        $this->signature->signRequest($this);
        $response = $this->request();
        $this->checkResponse($response);
        return $this->responseToResultTransformer($response, $name);
    }

    function checkResponse(Response $response)
    {
        $xmlBody = simplexml_load_string($response->getBody());
        $jsonData = json_encode($xmlBody);
        $body = json_decode($jsonData, true);
        if ($response->getStatusCode() !== 200) {
            $exception = new OssException($body['Message']);
            $exception->setExceptionCode($body['Code']);
            $exception->setResponse($response);
            $exception->setExceptionType($body['Code']);
            $exception->setRequestId($body['RequestId']);
            throw  $exception;
        }
        return true;
    }

    public function getCommand($name, array $params = [])
    {
        return new Command($name, $params);
    }

    /**
     * 发起请求
     * request
     * @return mixed
     * @author Tioncico
     * Time: 9:32
     */
    public function request()
    {
        $method = strtolower($this->getClient()->requestMethod);
        $response = $this->$method();
        return $response;
    }


    /**
     * @return Url
     */
    public function getUrl(): Url
    {
        return $this->url;
    }

    function setUrl($url): \EasySwoole\HttpClient\HttpClient
    {
        if (is_string($url)) {
            return parent::setUrl($url);
        } elseif ($url instanceof Url) {
            $this->url = $url;
            return $this;
        }
    }


}