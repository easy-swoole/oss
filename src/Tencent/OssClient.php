<?php

namespace EasySwoole\Oss\Tencent;

use EasySwoole\Oss\Tencent\Exception\OssException;


class OssClient extends Http\HttpClient
{

    public function commandToRequestTransformer(CommandInterface $command)
    {
        $action = $command->GetName();
        $opreation = $this->api[$action];
        $transformer = new CosTransformer($this->cosConfig, $opreation);
        $seri = new Serializer($this->desc);
        $request = $seri($command);
        $request = $transformer->bucketStyleTransformer($command, $request);
        $request = $transformer->uploadBodyTransformer($command, $request);
        $request = $transformer->md5Transformer($command, $request);
        $request = $transformer->specialParamTransformer($command, $request);
        return $request;
    }

    public function responseToResultTransformer(ResponseInterface $response, RequestInterface $request, CommandInterface $command)
    {
        $action = $command->getName();
        if ($action == "GetObject") {
            if (isset($command['SaveAs'])) {
                $fp = fopen($command['SaveAs'], "wb");
                fwrite($fp, $response->getBody());
                fclose($fp);
            }
        }
        $deseri = new Deserializer($this->desc, true);
        $response = $deseri($response, $request, $command);
        if ($command['Key'] != null && $response['Key'] == null) {
            $response['Key'] = $command['Key'];
        }
        if ($command['Bucket'] != null && $response['Bucket'] == null) {
            $response['Bucket'] = $command['Bucket'];
        }
        $response['Location'] = $request->getUri()->getHost() . $request->getUri()->getPath();

        return $response;
    }

    public function __call($method, array $args)
    {
        try {
            parent::__call(ucfirst($method), $args);
        } catch (OssException $e) {
            $previous = $e->getPrevious();
            if ($previous !== null) {
                throw $previous;
            } else {
                throw $e;
            }
        }
    }

    public function getApi()
    {
        return $this->api;
    }

    private function getCosConfig()
    {
        return $this->cosConfig;
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
        $body = Psr7\stream_for($body);
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

    public static function handleSignature($secretId, $secretKey)
    {
        return function (callable $handler) use ($secretId, $secretKey) {
            return new SignatureMiddleware($handler, $secretId, $secretKey);
        };
    }

    public static function handleErrors()
    {
        return function (callable $handler) {
            return new ExceptionMiddleware($handler);
        };
    }
}
