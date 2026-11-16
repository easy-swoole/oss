<?php
namespace EasySwoole\Oss\QiNiu\Storage;

use EasySwoole\Oss\QiNiu\Config;
use EasySwoole\Oss\QiNiu\Http\HttpClient;
use EasySwoole\Oss\QiNiu\Storage\ResumeUploader;
use EasySwoole\Oss\QiNiu\Storage\FormUploader;

/**
 * 主要涉及了资源上传接口的实现
 *
 * @link http://developer.qiniu.com/docs/v6/api/reference/up/
 */
final class UploadManager
{
    private $config;

    public function __construct(Config $config = null)
    {
        if ($config === null) {
            $config = new Config();
        }
        $this->config = $config;
    }

    /**
     * 上传二进制流到七牛
     *
     * @param $upToken    上传凭证
     * @param $key        上传文件名
     * @param $data       上传二进制流
     * @param $params     自定义变量，规格参考
     *                    http://developer.qiniu.com/docs/v6/api/overview/up/response/vars.html#xvar
     * @param $mime       上传数据的mimeType
     * @param $checkCrc   是否校验crc32
     *
     * @return array    包含已上传文件的信息，类似：
     *                                              [
     *                                                  "hash" => "<Hash string>",
     *                                                  "key" => "<Key string>"
     *                                              ]
     */
    public function put(
        $upToken,
        $key,
        $data,
        $params = null,
        $mime = 'application/octet-stream',
        $fname = "default_filename"
    ) {
    
        $params = self::trimParams($params);
        return FormUploader::put(
            $upToken,
            $key,
            $data,
            $this->config,
            $params,
            $mime,
            $fname
        );
    }


    /**
     * 上传文件到七牛
     *
     * @param $upToken    上传凭证
     * @param $key        上传文件名
     * @param $filePath   上传文件的路径
     * @param $params     自定义变量，规格参考
     *                    http://developer.qiniu.com/docs/v6/api/overview/up/response/vars.html#xvar
     * @param $mime       上传数据的mimeType
     * @param $checkCrc   是否校验crc32
     *
     * @return array    包含已上传文件的信息，类似：
     *                                              [
     *                                                  "hash" => "<Hash string>",
     *                                                  "key" => "<Key string>"
     *                                              ]
     */
    public function putFile(
        $upToken,
        $key,
        $filePath,
        $params = null,
        $mime = 'application/octet-stream',
        $checkCrc = false,
        $resumeFile = null,
        $version = 'v1',
        $partSize = Config::BLOCK_SIZE
    ) {
    
        $file = fopen($filePath, 'rb');
        if ($file === false) {
            throw new \Exception("file can not open", 1);
        }
        try {
            if (!in_array($version, ['v1', 'v2'], true)) {
                throw new \InvalidArgumentException('Unsupported Qiniu multipart upload version');
            }
            $params = self::trimParams($params);
            $size = fstat($file)['size'];
            if ($size <= Config::BLOCK_SIZE) {
                $data = $size === 0 ? '' : fread($file, $size);
                if ($data === false || strlen($data) !== $size) {
                    throw new \RuntimeException('Could not read the complete upload file');
                }
                return FormUploader::put($upToken, $key, $data, $this->config, $params, $mime, basename($filePath), $checkCrc);
            }

            if ($version === 'v2') {
                $uploader = new MultipartUploader($this->config);
                return $uploader->upload($upToken, $key, $file, $size, basename($filePath), $params ?? [], $mime, $checkCrc, $resumeFile, $partSize);
            }
            $uploader = new ResumeUploader($upToken, $key, $file, $size, $params, $mime, $this->config, $checkCrc, $resumeFile);
            return $uploader->upload(basename($filePath));
        } catch (\EasySwoole\Oss\QiNiu\RegionQueryException $error) {
            return [null, $error->getError()];
        } finally {
            fclose($file);
        }
    }

    public static function trimParams($params)
    {
        if ($params === null) {
            return null;
        }
        $ret = array();
        foreach ($params as $k => $v) {
            $pos1 = strpos($k, 'x:');
            $pos2 = strpos($k, 'x-qn-meta-');
            if (($pos1 === 0 || $pos2 === 0) && (is_string($v) || is_int($v) || is_float($v))) {
                $ret[$k] = (string)$v;
            }
        }
        return $ret;
    }
}
