<?php

namespace EasySwoole\Oss\AliYun;

use EasySwoole\Oss\AliYun\Core\OssException;

final class SignatureV4
{
    private const ALGORITHM = 'OSS4-HMAC-SHA256';
    private const UNSIGNED_PAYLOAD = 'UNSIGNED-PAYLOAD';

    public function __construct(private Config $config)
    {
    }

    public function sign(string $url, array $options, array &$headers, int $timestamp): string
    {
        $headers['x-oss-date'] = gmdate('Ymd\THis\Z', $timestamp);
        $headers['x-oss-content-sha256'] ??= self::UNSIGNED_PAYLOAD;
        $query = $this->canonicalQuery($url);
        [$credential, $signature, $stringToSign] = $this->calculate($options, $headers, $query, $timestamp);
        $headers['Authorization'] = self::ALGORITHM . ' Credential=' . $credential . ',Signature=' . $signature;
        return $stringToSign;
    }

    public function presign(string $url, array $options, array $headers, ?string $token, int $timestamp): string
    {
        $expires = (int)$options[OssConst::OSS_PREAUTH] - $timestamp;
        $maxExpires = $token === null ? 604800 : 43200;
        if ($expires < 1 || $expires > $maxExpires) {
            throw new OssException("V4 presigned URL validity must be between 1 and {$maxExpires} seconds");
        }

        $params = [
            'x-oss-signature-version' => self::ALGORITHM,
            'x-oss-date' => gmdate('Ymd\THis\Z', $timestamp),
            'x-oss-expires' => $expires,
            'x-oss-credential' => $this->credential($timestamp),
        ];
        if ($token !== null) {
            $params['x-oss-security-token'] = $token;
        }
        unset($headers[OssConst::OSS_SECURITY_TOKEN], $headers['x-oss-content-sha256']);
        $separator = str_contains($url, '?') ? '&' : '?';
        $url .= $separator . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        [, $signature] = $this->calculate($options, $headers, $this->canonicalQuery($url), $timestamp);
        return $url . '&x-oss-signature=' . $signature;
    }

    private function credential(int $timestamp): string
    {
        return $this->config->getAccessKeyId() . '/' . gmdate('Ymd', $timestamp)
            . '/' . $this->config->getRegion() . '/oss/aliyun_v4_request';
    }

    private function canonicalQuery(string $url): string
    {
        $query = parse_url($url, PHP_URL_QUERY);
        if ($query === null || $query === '') {
            return '';
        }

        $parameters = [];
        foreach (explode('&', $query) as $parameter) {
            [$name, $value] = array_pad(explode('=', $parameter, 2), 2, '');
            $name = rawurlencode(urldecode($name));
            $value = rawurlencode(urldecode($value));
            $parameters[$name][] = $value === '' ? $name : $name . '=' . $value;
        }
        ksort($parameters, SORT_STRING);
        $canonical = [];
        foreach ($parameters as $values) {
            array_push($canonical, ...$values);
        }
        return implode('&', $canonical);
    }

    private function calculate(array $options, array $headers, string $query, int $timestamp): array
    {
        $bucket = $options[OssConst::OSS_BUCKET];
        $object = $options[OssConst::OSS_OBJECT];
        $path = '/' . ($bucket === '' ? '' : $bucket . '/');
        if ($object !== '/') {
            $path .= $object;
        }
        $uri = str_replace('%2F', '/', rawurlencode($path));

        $signedHeaders = [];
        foreach ($headers as $name => $value) {
            $name = strtolower($name);
            if (str_starts_with($name, 'x-oss-') || $name === 'content-type' || $name === 'content-md5') {
                $signedHeaders[$name] = trim($value);
            }
        }
        ksort($signedHeaders, SORT_STRING);
        $canonicalHeaders = '';
        foreach ($signedHeaders as $name => $value) {
            $canonicalHeaders .= $name . ':' . $value . "\n";
        }
        $request = implode("\n", [
            $options[OssConst::OSS_METHOD],
            $uri,
            $query,
            $canonicalHeaders,
            '',
            $signedHeaders['x-oss-content-sha256'] ?? self::UNSIGNED_PAYLOAD,
        ]);

        $credential = $this->credential($timestamp);
        $scope = substr($credential, strpos($credential, '/') + 1);
        $stringToSign = self::ALGORITHM . "\n" . gmdate('Ymd\THis\Z', $timestamp)
            . "\n" . $scope . "\n" . hash('sha256', $request);
        $key = 'aliyun_v4' . $this->config->getAccessKeySecret();
        foreach (explode('/', $scope) as $component) {
            $key = hash_hmac('sha256', $component, $key, true);
        }
        return [$credential, hash_hmac('sha256', $stringToSign, $key), $stringToSign];
    }
}
