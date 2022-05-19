<?php

namespace EasySwoole\Oss\AliYun;

use EasySwoole\Spl\SplBean;

class Config extends SplBean
{
    protected $accessKeyId;//appid
    protected $accessKeySecret;//key
    protected $endpoint;//point
    protected $isCName = false;//是否对Bucket做了域名绑定，并且Endpoint参数填写的是自己的域名
    protected $timeout = 0;
    protected $connectionTimeout = 0;

    /**
     * @return float|int
     */
    public function getTimeout()
    {
        return $this->timeout;
    }

    /**
     * @param float|int $timeout
     */
    public function setTimeout($timeout): void
    {
        $this->timeout = $timeout;
    }

    /**
     * @return float|int
     */
    public function getConnectionTimeout()
    {
        return $this->connectionTimeout;
    }

    /**
     * @param float|int $connectionTimeout
     */
    public function setConnectionTimeout($connectionTimeout): void
    {
        $this->connectionTimeout = $connectionTimeout;
    }

    /**
     * @return mixed
     */
    public function getAccessKeyId()
    {
        return $this->accessKeyId;
    }

    /**
     * @param mixed $accessKeyId
     */
    public function setAccessKeyId($accessKeyId): void
    {
        $this->accessKeyId = $accessKeyId;
    }

    /**
     * @return mixed
     */
    public function getAccessKeySecret()
    {
        return $this->accessKeySecret;
    }

    /**
     * @param mixed $accessKeySecret
     */
    public function setAccessKeySecret($accessKeySecret): void
    {
        $this->accessKeySecret = $accessKeySecret;
    }

    /**
     * @return mixed
     */
    public function getEndpoint()
    {
        return $this->endpoint;
    }

    /**
     * @param mixed $endpoint
     */
    public function setEndpoint($endpoint): void
    {
        $this->endpoint = $endpoint;
    }

    /**
     * @return bool
     */
    public function isCName(): bool
    {
        return $this->isCName;
    }

    /**
     * @param bool $isCName
     */
    public function setIsCName(bool $isCName): void
    {
        $this->isCName = $isCName;
    }


}
