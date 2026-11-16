<?php

namespace EasySwoole\Oss\QiNiu\Storage;

use EasySwoole\Oss\QiNiu\Config;
use EasySwoole\Oss\QiNiu\Http\Client;
use EasySwoole\Oss\QiNiu\Http\Error;
use EasySwoole\Oss\QiNiu\Util;

final class MultipartUploader
{
    public function __construct(private Config $config)
    {
    }

    public function upload($token, $key, $file, int $size, string $name, array $params, string $mime, bool $check, ?string $resumeFile, int $partSize): array
    {
        if ($partSize < 1048576 || $partSize > 1073741824 || ceil($size / $partSize) > 10000) {
            throw new \InvalidArgumentException('Qiniu multipart V2 requires 1 MiB to 1 GiB parts and at most 10000 parts');
        }
        [$ak, $bucket, $error] = Util::explodeUpToken($token);
        if ($error !== null) {
            return [null, $error];
        }

        $host = $this->config->getUpHost($ak, $bucket);
        $object = $key === null ? '~' : Util::base64_urlSafeEncode($key);
        $url = $host . '/buckets/' . rawurlencode($bucket) . '/objects/' . $object . '/uploads';
        $headers = ['Authorization' => 'UpToken ' . $token];
        $progress = new UploadProgress($resumeFile, $file, ['v2', $url, $ak, $partSize]);
        $state = $progress->state;
        if (($state['expires'] ?? 0) <= time()) {
            $response = Client::post($url, '', $headers);
            $result = $response->json();
            if (!$response->ok() || empty($result['uploadId'])) {
                return [null, new Error($url, $response)];
            }
            $state = ['id' => $result['uploadId'], 'expires' => $result['expireAt'] ?? 0, 'parts' => []];
            $progress->save($state);
        }

        $uploadUrl = $url . '/' . rawurlencode($state['id']);
        for ($offset = 0, $number = 1; $offset < $size; $offset += $partSize, ++$number) {
            if (isset($state['parts'][$number])) {
                continue;
            }
            $length = min($size - $offset, $partSize);
            if (fseek($file, $offset) !== 0 || strlen($data = (string)fread($file, $length)) !== $length) {
                throw new \RuntimeException('Could not read Qiniu upload part');
            }
            $partUrl = $uploadUrl . '/' . $number;
            $partHeaders = $headers + ['Content-Type' => 'application/octet-stream'];
            if ($check) {
                $partHeaders['Content-MD5'] = md5($data);
            }
            $response = Client::put($partUrl, $data, $partHeaders);
            if ($response->needRetry()) {
                $response = Client::put($partUrl, $data, $partHeaders);
            }
            $result = $response->json();
            if (!$response->ok() || empty($result['etag'])) {
                if ((int)$response->statusCode === 612) {
                    $progress->save([]);
                }
                return [null, new Error($partUrl, $response)];
            }
            $state['parts'][$number] = ['partNumber' => $number, 'etag' => $result['etag']];
            $progress->save($state);
        }

        ksort($state['parts'], SORT_NUMERIC);
        $body = ['parts' => array_values($state['parts']), 'fname' => $name, 'mimeType' => $mime];
        foreach ($params as $key => $value) {
            $body[str_starts_with($key, 'x:') ? 'customVars' : 'metadata'][$key] = $value;
        }
        $response = Client::post($uploadUrl, json_encode($body, JSON_THROW_ON_ERROR), $headers + ['Content-Type' => 'application/json']);
        if (!$response->ok()) {
            if ((int)$response->statusCode === 612) {
                $progress->save([]);
            }
            return [null, new Error($uploadUrl, $response)];
        }
        $progress->save([]);
        return [$response->json(), null];
    }
}
