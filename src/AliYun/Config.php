<?php

namespace EasySwoole\Oss\AliYun;

use EasySwoole\Oss\AliYun\Core\OssException;
use EasySwoole\Spl\SplBean;

class Config extends SplBean
{
    protected $accessKeyId;//appid
    protected $accessKeySecret;//key
    protected $region;
    protected $endpoint;//point
    protected $isCName = false;//是否对Bucket做了域名绑定，并且Endpoint参数填写的是自己的域名
    protected $timeout = 0;
    protected $connectionTimeout = 0;

    public function getRegion(): string
    {
        if ($this->region !== null && $this->region !== '') {
            return $this->region;
        }

        $endpoint = $this->getEndpoint();
        $host = parse_url(str_contains($endpoint, '://') ? $endpoint : 'https://' . $endpoint, PHP_URL_HOST);
        if ($host && preg_match('/^oss-([a-z0-9]+(?:-[a-z0-9]+)+?)(?:-internal)?\.aliyuncs\.com$/i', $host, $matches)
            && !in_array(strtolower($matches[1]), ['accelerate-overseas'], true)) {
            return strtolower($matches[1]);
        }

        throw new OssException('Set region explicitly when it cannot be derived from the OSS endpoint');
    }

    public function setRegion(string $region): void
    {
        $this->region = $region;
    }

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
