<?php
namespace EasySwoole\Oss\QiNiu\Http;

use EasySwoole\HttpClient\HttpClient;
use EasySwoole\Oss\QiNiu\Config;
use EasySwoole\Oss\QiNiu\Http\Request;
use EasySwoole\Oss\QiNiu\Http\Response;

final class Client
{
    public static function get($url, array $headers = array())
    {
        $httpClient = new HttpClient($url);
        $httpClient->setHeaders($headers,true,false);
        self::clientHandel($httpClient);
        $response = $httpClient->get();
        $response = new \EasySwoole\Oss\QiNiu\Http\Response($response);
        return $response;
    }

    public static function delete($url, array $headers = array())
    {
        $httpClient = new HttpClient($url);
        $httpClient->setHeaders($headers,true,false);
        self::clientHandel($httpClient);
        $response = $httpClient->delete();
        $response = new \EasySwoole\Oss\QiNiu\Http\Response($response);
        return $response;
    }

    public static function post($url, $body, array $headers = array())
    {
        $httpClient = new HttpClient($url);
        $headers = array_change_key_case($headers) + ['content-type' => 'application/x-www-form-urlencoded'];
        $httpClient->setHeaders($headers, true, false);

        self::clientHandel($httpClient);
        $response = $httpClient->post($body);
        $response = new \EasySwoole\Oss\QiNiu\Http\Response($response);
        return $response;
    }

    public static function put($url, $body, array $headers = array())
    {
        $httpClient = new HttpClient($url);
        self::clientHandel($httpClient);
        return new Response($httpClient->put($body, $headers));
    }

    public static function multipartPost(
        $url,
        $fields,
        $name,
        $fileName,
        $fileBody,
        $mimeType = null,
        array $headers = array()
    ) {
        $data = array();
        $mimeBoundary = md5(microtime());

        foreach ($fields as $key => $val) {
            array_push($data, '--' . $mimeBoundary);
            array_push($data, "Content-Disposition: form-data; name=\"$key\"");
            array_push($data, '');
            array_push($data, $val);
        }

        array_push($data, '--' . $mimeBoundary);
        $finalMimeType = empty($mimeType) ? 'application/octet-stream' : $mimeType;
        $finalFileName = self::escapeQuotes($fileName);
        array_push($data, "Content-Disposition: form-data; name=\"$name\"; filename=\"$finalFileName\"");
        array_push($data, "Content-Type: $finalMimeType");
        array_push($data, '');
        array_push($data, $fileBody);

        array_push($data, '--' . $mimeBoundary . '--');
        array_push($data, '');

        $body = implode("\r\n", $data);
        $headers['content-type'] = 'multipart/form-data; boundary=' . $mimeBoundary;
        return self::post($url, $body, $headers);
    }

    private static function userAgent()
    {
        $sdkInfo = "QiniuPHP/" . Config::SDK_VER;

        $systemInfo = php_uname("s");
        $machineInfo = php_uname("m");

        $envInfo = "($systemInfo/$machineInfo)";

        $phpVer = phpversion();

        $ua = "$sdkInfo $envInfo PHP/$phpVer";
        return $ua;
    }

    private static function parseHeaders($raw)
    {
        $headers = array();
        $headerLines = explode("\r\n", $raw);
        foreach ($headerLines as $line) {
            $headerLine = trim($line);
            $kv = explode(':', $headerLine);
            if (count($kv) > 1) {
                $kv[0] =self::ucwordsHyphen($kv[0]);
                $headers[$kv[0]] = trim($kv[1]);
            }
        }
        return $headers;
    }

    private static function escapeQuotes($str)
    {
        $find = array("\\", "\"");
        $replace = array("\\\\", "\\\"");
        return str_replace($find, $replace, $str);
    }
    
    private static function ucWordsHyphen($str)
    {
        return str_replace('- ', '-', ucwords(str_replace('-', '- ', $str)));
    }

    protected static function clientHandel(HttpClient $httpClient){
        $httpClient->setTimeout(Config::getTimeout());
        $httpClient->setConnectTimeout(Config::getConnectTimeout());
        $httpClient->setHeader('User-Agent',self::userAgent(),false);
    }
}
