<?php
/**
 * Created by PhpStorm.
 * User: Tioncico
 * Date: 2019/11/26 0026
 * Time: 10:26
 */

namespace EasySwoole\Oss\Tencent\Http;

use EasySwoole\HttpClient\Bean\Url;

class HttpClient extends \EasySwoole\HttpClient\HttpClient
{

    /**
     * 发起请求
     * request
     * @return mixed
     * @author Tioncico
     * Time: 9:32
     */
    public function request()
    {
//        var_dump($this->getUrl());
        $method = strtolower($this->getClient()->requestMethod);
//        var_dump($method);
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