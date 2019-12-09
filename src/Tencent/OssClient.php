<?php

namespace EasySwoole\Oss\Tencent;

use EasySwoole\Oss\Tencent\Exception\OssException;
use EasySwoole\Oss\Tencent\Http\Command;
use EasySwoole\Oss\Tencent\Http\HttpClient;


use EasySwoole\HttpClient\Bean\Response;
use EasySwoole\HttpClient\Bean\Url;
use EasySwoole\Oss\Tencent\Config;
use EasySwoole\Oss\Tencent\CosTransformer;
use EasySwoole\Oss\Tencent\Http\Result;
use EasySwoole\Oss\Tencent\Request\RequestHandel;
use EasySwoole\Oss\Tencent\Service;
use EasySwoole\Oss\Tencent\Signature;
use EasySwoole\Spl\SplStream;
use phpDocumentor\Reflection\DocBlock\Serializer;

class OssClient
{

    protected $cosConfig;
    protected $signature;
    protected $extraData;
    protected $service;
    /**
     * @var $request HttpClient
     */
    protected $request;

    public function __construct(Config $cosConfig)
    {
        $this->cosConfig = $cosConfig;
        $this->service = Service::getService();

//        if ($this->cosConfig['anonymous'] != true) {
//            $handler->push($this::handleSignature($this->cosConfig['secretId'], $this->cosConfig['secretKey']));
//        }

        $this->signature = new Signature($this->cosConfig->getSecretId(), $this->cosConfig->getSecretKey());

    }

    public function commandToRequestTransformer(Command $command)
    {
        $this->request = new HttpClient();
        $cosConfig = $this->cosConfig;
        $request = $this->request;
        $request->setHeader('User-Agent', $cosConfig->getUserAgent());
        if ($cosConfig->getToken() != null) {
            $request->setHeader('x-cos-security-token', $cosConfig->getToken());
        }
        $request->setTimeout($cosConfig->getTimeout());
        $url = $this->cosConfig->getSchema() . '://cos.' . $this->cosConfig->getRegion() . '.myqcloud.com';
        $request->setUrl($url);

        $action = $command->getName();
        $operation = $this->service['operations'][$action];
        $requestHandel = new RequestHandel($request,$operation,$command->data);
        $requestHandel->handel();

        $transformer = new CosTransformer($this->cosConfig, $operation);
        $transformer->bucketStyleTransformer($command, $request);
        $transformer->uploadBodyTransformer($command, $request);
        $transformer->md5Transformer($command, $request);
        $transformer->addContentLength($request);
        $request = $transformer->specialParamTransformer($command, $request);
        //这里进行setBody
        $request->getClient()->setData($this->request->getRequestBody());

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
        $result->Location = $this->request->getUrl()->getHost() . $this->request->getUrl()->getPath();
//        var_dump($response);
        return $result;
    }

    function __call($name, array $args)
    {
        $name = ucfirst($name);
        $args = isset($args[0]) ? $args[0] : [];
//        var_dump($args);
        $command = $this->getCommand($name, $args);
        $this->commandToRequestTransformer($command);

        //请求数据生成加密
        $this->signature->signRequest($this->request);
//        var_dump($this->request);
//        $this->request->setHeader('a',1);
        $response = $this->request->request();
        $this->checkResponse($response);
        return $this->responseToResultTransformer($response, $name);
    }

    function checkResponse(Response $response)
    {
        if ((int)(intval($response->getStatusCode()) / 100) != 2) {
            $xmlBody = simplexml_load_string($response->getBody());
            $jsonData = json_encode($xmlBody);
            $body = json_decode($jsonData, true);
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

    public function handelRequestOperation(HttpClient $request,$operation){

    }





    private function createPresignedUrl(RequestInterface $request, $expires)
    {
        return $this->signature->createPresignedUrl($request, $expires);
    }

    public function getPresignetUrl($method, $args, $expires = null)
    {
        $command = $this->getCommand($method, $args);
        $request = $this->commandToRequestTransformer($command);
        return $this->createPresignedUrl($request, $expires);
    }

    public function getObjectUrl($bucket, $key, $expires = null, array $args = array())
    {
        $command = $this->getCommand('GetObject', $args + array('Bucket' => $bucket, 'Key' => $key));
        $request = $this->commandToRequestTransformer($command);
        return $this->createPresignedUrl($request, $expires);
    }

    public function upload($bucket, $key, $body, $options = array())
    {
        $body = new SplStream($body);
        $options['PartSize'] = isset($options['PartSize']) ? $options['PartSize'] : MultipartUpload::MIN_PART_SIZE;
        if ($body->getSize() < $options['PartSize']) {
            $rt = $this->putObject(array(
                    'Bucket' => $bucket,
                    'Key'    => $key,
                    'Body'   => $body,
                ) + $options);
        } else {
            $multipartUpload = new MultipartUpload($this, $body, array(
                    'Bucket' => $bucket,
                    'Key'    => $key,
                ) + $options);

            $rt = $multipartUpload->performUploading();
        }
        return $rt;
    }

    public function resumeUpload($bucket, $key, $body, $uploadId, $options = array())
    {
        $body = Psr7\stream_for($body);
        $options['PartSize'] = isset($options['PartSize']) ? $options['PartSize'] : MultipartUpload::DEFAULT_PART_SIZE;
        $multipartUpload = new MultipartUpload($this, $body, array(
                'Bucket'   => $bucket,
                'Key'      => $key,
                'UploadId' => $uploadId,
            ) + $options);

        $rt = $multipartUpload->resumeUploading();
        return $rt;
    }

    public function copy($bucket, $key, $copySource, $options = array())
    {

        $options['PartSize'] = isset($options['PartSize']) ? $options['PartSize'] : Copy::DEFAULT_PART_SIZE;

        // set copysource client
        $sourceConfig = $this->rawCosConfig;
        $sourceConfig['region'] = $copySource['Region'];
        $cosSourceClient = new Client($sourceConfig);
        $copySource['VersionId'] = isset($copySource['VersionId']) ? $copySource['VersionId'] : "";
        try {
            $rt = $cosSourceClient->headObject(
                array('Bucket'    => $copySource['Bucket'],
                      'Key'       => $copySource['Key'],
                      'VersionId' => $copySource['VersionId'],
                )
            );
        } catch (\Exception $e) {
            throw $e;
        }

        $contentLength = $rt['ContentLength'];
        // sample copy
        if ($contentLength < $options['PartSize']) {
            $rt = $this->copyObject(array(
                    'Bucket'     => $bucket,
                    'Key'        => $key,
                    'CopySource' => $copySource['Bucket'] . '.cos.' . $copySource['Region'] .
                        ".myqcloud.com/" . $copySource['Key'] . "?versionId=" . $copySource['VersionId'],
                ) + $options
            );
            return $rt;
        }
        // multi part copy
        $copySource['ContentLength'] = $contentLength;
        $copy = new Copy($this, $copySource, array(
                'Bucket' => $bucket,
                'Key'    => $key
            ) + $options
        );
        return $copy->copy();
    }

    public function doesBucketExist($bucket, array $options = array())
    {
        try {
            $this->HeadBucket(array(
                'Bucket' => $bucket));
            return True;
        } catch (\Exception $e) {
            return False;
        }
    }

    public function doesObjectExist($bucket, $key, array $options = array())
    {
        try {
            $this->HeadObject(array(
                'Bucket' => $bucket,
                'Key'    => $key));
            return True;
        } catch (\Exception $e) {
            return False;
        }
    }

    public static function explodeKey($key)
    {

        // Remove a leading slash if one is found
        $split_key = explode('/', $key && $key[0] == '/' ? substr($key, 1) : $key);
        // Remove empty element

        $split_key = array_filter($split_key, function ($var) {
            return !($var == '' || $var == null);
        });
        return implode("/", $split_key);
    }


}
