<?php

namespace EasySwoole\Oss\Tencent;

use EasySwoole\Oss\Tencent\Http\HttpClient;

class Signature
{
    public function __construct(private $accessKey, private $secretKey)
    {
    }

    public function signRequest(HttpClient $request)
    {
        return $request->setHeader('Authorization', $this->createAuthorization($request), false);
    }

    public function createAuthorization(HttpClient $request, $expires = null, array $headerNames = []): string
    {
        $now = time();
        $end = match (true) {
            $expires === null => $now + 1800,
            $expires instanceof \DateTimeInterface => $expires->getTimestamp(),
            is_int($expires) => $now + $expires,
            default => strtotime($expires, $now),
        };
        if ($end === false || $end <= $now) {
            throw new \InvalidArgumentException('COS signature expiration must be in the future');
        }
        $signTime = ($now - 60) . ';' . $end;
        $url = $request->getUrl();
        $query = [];
        foreach (explode('&', $url->getQuery() ?? '') as $parameter) {
            if ($parameter === '') {
                continue;
            }
            [$name, $value] = array_pad(explode('=', $parameter, 2), 2, '');
            $query[rawurlencode(strtolower(rawurldecode($name)))] = rawurlencode(rawurldecode($value));
        }
        $headers = ['host' => rawurlencode($url->getHost())];
        $requestHeaders = $request->getSigningHeaders();
        foreach ($headerNames as $name) {
            $name = strtolower($name);
            if (isset($requestHeaders[$name])) {
                $headers[rawurlencode($name)] = rawurlencode(trim($requestHeaders[$name]));
            }
        }
        ksort($query, SORT_STRING);
        ksort($headers, SORT_STRING);
        $httpString = implode("\n", [
            strtolower($request->getClient()->requestMethod),
            rawurldecode($url->getPath() ?: '/'),
            implode('&', array_map(fn($name, $value) => "$name=$value", array_keys($query), $query)),
            implode('&', array_map(fn($name, $value) => "$name=$value", array_keys($headers), $headers)),
            '',
        ]);
        $signKey = hash_hmac('sha1', $signTime, $this->secretKey);
        $signature = hash_hmac('sha1', "sha1\n$signTime\n" . sha1($httpString) . "\n", $signKey);
        return 'q-sign-algorithm=sha1&q-ak=' . rawurlencode($this->accessKey)
            . "&q-sign-time=$signTime&q-key-time=$signTime&q-header-list=" . implode(';', array_keys($headers))
            . '&q-url-param-list=' . implode(';', array_keys($query)) . '&q-signature=' . $signature;
    }

    public function createPresignedUrl(HttpClient $request, $expires = null, array $headerNames = []): string
    {
        $authorization = $this->createAuthorization($request, $expires, $headerNames);
        $url = $request->getUrl();
        $query = ltrim($url->getQuery() . '&' . $authorization, '&');
        $token = $request->getSigningHeaders()['x-cos-security-token'] ?? null;
        if ($token !== null) {
            $query .= '&x-cos-security-token=' . rawurlencode($token);
        }
        $port = $url->getPort();
        $authority = $url->getHost() . (in_array($port, [0, 80, 443], true) ? '' : ':' . $port);
        return $url->getScheme() . '://' . $authority . ($url->getPath() ?: '/') . '?' . $query;
    }
}
