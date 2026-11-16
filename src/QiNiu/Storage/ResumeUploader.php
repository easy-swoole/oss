<?php
namespace EasySwoole\Oss\QiNiu\Storage;

use EasySwoole\Oss\QiNiu\Config;
use EasySwoole\Oss\QiNiu\Http\Client;
use EasySwoole\Oss\QiNiu\Http\Error;
use EasySwoole\Oss\QiNiu\Util;

/**
 * 断点续上传类, 该类主要实现了断点续上传中的分块上传,
 * 以及相应地创建块和创建文件过程.
 *
 * @link http://developer.qiniu.com/docs/v6/api/reference/up/mkblk.html
 * @link http://developer.qiniu.com/docs/v6/api/reference/up/mkfile.html
 */
final class ResumeUploader
{
    private $upToken;
    private $key;
    private $inputStream;
    private $size;
    private $params;
    private $mime;
    private $contexts;
    private $host;
    private $currentUrl;
    private $config;
    private $tokenError;
    private $accessKey;
    private $bucket;
    private bool $checkCrc;
    private ?string $resumeFile;

    /**
     * 上传二进制流到七牛
     *
     * @param $upToken    上传凭证
     * @param $key        上传文件名
     * @param $inputStream 上传二进制流
     * @param $size       上传流的大小
     * @param $params     自定义变量
     * @param $mime       上传数据的mimeType
     *
     * @link http://developer.qiniu.com/docs/v6/api/overview/up/response/vars.html#xvar
     */
    public function __construct(
        $upToken,
        $key,
        $inputStream,
        $size,
        $params,
        $mime,
        $config,
        $checkCrc = true,
        $resumeFile = null
    ) {

        $this->upToken = $upToken;
        $this->key = $key;
        $this->inputStream = $inputStream;
        $this->size = $size;
        $this->params = $params;
        $this->mime = $mime;
        $this->contexts = array();
        $this->config = $config;

        $this->checkCrc = $checkCrc;
        $this->resumeFile = $resumeFile;
        [$this->accessKey, $this->bucket, $this->tokenError] = Util::explodeUpToken($upToken);
    }

    /**
     * 上传操作
     */
    public function upload($fname)
    {
        if ($this->tokenError !== null) {
            return [null, $this->tokenError];
        }
        $this->host = $this->config->getUpHost($this->accessKey, $this->bucket);
        $progress = new UploadProgress($this->resumeFile, $this->inputStream, [
            'v1', $this->host, $this->accessKey, $this->bucket, $this->key,
        ]);
        $state = $progress->state;
        $expires = $state['expires'] ?? 0;
        $this->contexts = $expires > time() ? $state['contexts'] : [];
        $uploaded = min($this->size, count($this->contexts) * Config::BLOCK_SIZE);
        if ($this->resumeFile !== null && fseek($this->inputStream, $uploaded) !== 0) {
            throw new \RuntimeException('Could not seek to Qiniu upload offset');
        }
        while ($uploaded < $this->size) {
            $blockSize = $this->blockSize($uploaded);
            $data = fread($this->inputStream, $blockSize);
            if ($data === false || strlen($data) !== $blockSize) {
                throw new \Exception("file read failed", 1);
            }
            $crc = $this->checkCrc ? Util::crc32_data($data) : null;
            $response = $this->makeBlock($data, $blockSize);
            $ret = null;
            if ($response->ok() && $response->json() != null) {
                $ret = $response->json();
            }
            if ($response->statusCode < 0) {
                list($accessKey, $bucket, $err) = Util::explodeUpToken($this->upToken);
                if ($err != null) {
                    return array(null, $err);
                }

                $upHostBackup = $this->config->getUpBackupHost($accessKey, $bucket);
                $this->host = $upHostBackup;
            }
            if ($response->needRetry() || empty($ret['ctx']) || ($this->checkCrc && ($ret['crc32'] ?? null) != $crc)) {
                $response = $this->makeBlock($data, $blockSize);
                $ret = $response->json();
            }

            if (!$response->ok() || empty($ret['ctx']) || ($this->checkCrc && ($ret['crc32'] ?? null) != $crc)) {
                return array(null, new Error($this->currentUrl, $response));
            }
            array_push($this->contexts, $ret['ctx']);
            $expires = min($expires > time() ? $expires : PHP_INT_MAX, (int)($ret['expired_at'] ?? time() + 3600));
            $progress->save(['expires' => $expires, 'contexts' => $this->contexts]);
            $uploaded += $blockSize;
        }
        $result = $this->makeFile($fname);
        if ($result[1] === null || in_array((int)$result[1]->code(), [612, 701], true)) {
            $progress->save([]);
        }
        return $result;
    }

    /**
     * 创建块
     */
    private function makeBlock($block, $blockSize)
    {
        $url = $this->host . '/mkblk/' . $blockSize;
        return $this->post($url, $block);
    }

    private function fileUrl($fname)
    {
        $url = $this->host . '/mkfile/' . $this->size;
        $url .= '/mimeType/' . Util::base64_urlSafeEncode($this->mime);
        if ($this->key != null) {
            $url .= '/key/' . Util::base64_urlSafeEncode($this->key);
        }
        $url .= '/fname/' . Util::base64_urlSafeEncode($fname);
        if (!empty($this->params)) {
            foreach ($this->params as $key => $value) {
                $val = Util::base64_urlSafeEncode($value);
                $url .= "/$key/$val";
            }
        }
        return $url;
    }

    /**
     * 创建文件
     */
    private function makeFile($fname)
    {
        $url = $this->fileUrl($fname);
        $body = implode(',', $this->contexts);
        $response = $this->post($url, $body);
        if ($response->needRetry()) {
            $response = $this->post($url, $body);
        }
        if (!$response->ok()) {
            return array(null, new Error($this->currentUrl, $response));
        }
        return array($response->json(), null);
    }

    private function post($url, $data)
    {
        $this->currentUrl = $url;
        $headers = ['Authorization' => 'UpToken ' . $this->upToken, 'Content-Type' => 'application/octet-stream'];
        return Client::post($url, $data, $headers);
    }

    private function blockSize($uploaded)
    {
        if ($this->size < $uploaded + Config::BLOCK_SIZE) {
            return $this->size - $uploaded;
        }
        return Config::BLOCK_SIZE;
    }
}
