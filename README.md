# EasySwoole OSS

为 Swoole 协程提供阿里云 OSS、腾讯云 COS 和七牛云 Kodo 的对象存储客户端。

## 安装

运行环境需要 PHP 8.1 或以上版本，以及 Swoole 和 cURL 扩展。

```bash
composer require easyswoole/oss
```

## 协程中的客户端使用约束

**每个协程必须在协程内创建自己的 OSS 客户端，并仅在该协程内复用。** 并发的子协程也要分别创建客户端。此约束适用于阿里云和腾讯云的 `OssClient`，以及七牛云的 `UploadManager` 和 `BucketManager`。

禁止将这些客户端注册为单例、存入全局或静态变量，或把同一个客户端传给多个协程使用。以下示例假设所需环境变量均已在本机设置。

## 阿里云 OSS

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use EasySwoole\Oss\AliYun\Config;
use EasySwoole\Oss\AliYun\OssClient;

\Swoole\Coroutine\run(function () {
    $config = new Config([
        'accessKeyId' => getenv('ALIYUN_ACCESS_KEY_ID'),
        'accessKeySecret' => getenv('ALIYUN_ACCESS_KEY_SECRET'),
        'endpoint' => 'https://oss-cn-hangzhou.aliyuncs.com',
    ]);
    $client = new OssClient($config);
    $bucket = getenv('ALIYUN_OSS_BUCKET');
    $key = 'example.txt';

    $client->uploadFile($bucket, $key, __DIR__ . '/example.txt');
    $contents = $client->getObject($bucket, $key);
    $downloadUrl = $client->signUrl($bucket, $key, 300);
});
```

- `uploadFile()` 的第三个参数是本地文件路径，`putObject()` 的第三个参数是对象内容。
- 普通请求和对象预签名使用 V4。标准公网或内网地域 Endpoint 会自动推导 Region。使用自定义域名、传输加速域名或 IP 时，在 `Config` 中显式设置 Bucket 所在地域，例如 `'region' => 'cn-hangzhou'`。使用已绑定的自定义域名时，设置 `'isCName' => true`。
- 使用 STS 时，将临时 AccessKey 填入配置，并把 Token 作为 `new OssClient($config, $token)` 的第二个参数。普通请求使用请求头传递 Token，预签名链接携带 Token。
- `signUrl()` 接收有效秒数，`generatePresignedUrl()` 接收 Unix 到期时间戳。两者默认签名 GET，上传可指定 `'PUT'`。

## 腾讯云 COS

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use EasySwoole\Oss\Tencent\Config;
use EasySwoole\Oss\Tencent\OssClient;

\Swoole\Coroutine\run(function () {
    $config = new Config([
        'appId' => getenv('TENCENT_COS_APP_ID'),
        'secretId' => getenv('TENCENT_COS_SECRET_ID'),
        'secretKey' => getenv('TENCENT_COS_SECRET_KEY'),
        'region' => 'ap-guangzhou',
        'schema' => 'https',
    ]);
    $client = new OssClient($config);
    $bucket = getenv('TENCENT_COS_BUCKET');
    $key = 'example.txt';

    $client->upload($bucket, $key, 'example content');
    $result = $client->getObject(['Bucket' => $bucket, 'Key' => $key]);
    $contents = (string) $result['Body'];

    $downloadUrl = $client->getObjectUrl($bucket, $key, '+10 minutes');
    $uploadUrl = $client->getPreSignedUrl('putObject', [
        'Bucket' => $bucket,
        'Key' => 'incoming/example.txt',
    ], '+10 minutes');

    // 从上海地域的源 Bucket 复制到当前客户端配置的广州地域。
    $client->copy($bucket, 'copied/example.txt', [
        'Bucket' => getenv('TENCENT_COS_SOURCE_BUCKET'),
        'Region' => 'ap-shanghai',
        'Key' => $key,
    ]);
});
```

- Bucket 推荐填写完整名称，例如 `example-1250000000`。跨账号复制时，源 Bucket 使用其自身的 APPID 后缀。
- 使用临时凭证时，在 `Config` 中填写临时 SecretId、SecretKey 和 `token`。预签名方法返回 URL 字符串。若通过 `Headers` 指定签名头，使用 URL 发请求时须发送对应的头和值。
- `copy()` 可通过第四个参数传入 `['PartSize' => 64 * 1024 * 1024]`。分块复制依次处理各分块，失败时尝试中止该任务。源对象有指定版本时，可在源参数中添加 `VersionId`。

## 七牛云 Kodo

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use EasySwoole\Oss\QiNiu\Auth;
use EasySwoole\Oss\QiNiu\Config;
use EasySwoole\Oss\QiNiu\Storage\UploadManager;

\Swoole\Coroutine\run(function () {
    $auth = new Auth(getenv('QINIU_ACCESS_KEY'), getenv('QINIU_SECRET_KEY'));
    $bucket = getenv('QINIU_BUCKET');
    $key = 'example.zip';
    $token = $auth->uploadToken($bucket, $key);

    $config = new Config();
    $config->useHTTPS = true;
    $uploader = new UploadManager($config);
    [$result, $error] = $uploader->putFile(
        $token,
        $key,
        __DIR__ . '/example.zip',
        null,
        'application/zip',
        true,
        __DIR__ . '/example.zip.progress',
        'v2',
        4 * 1024 * 1024
    );

    if ($error !== null) {
        throw new RuntimeException(is_string($error) ? $error : $error->message());
    }
});
```

- 文件不超过 4 MiB 时使用表单上传，包括 0 字节文件。更大的文件默认走 V1 分片，也可显式选择 `v2`。V1 分片固定为 4 MiB，V2 支持自定义分片大小。
- 要恢复中断的分片上传，使用同一文件、Bucket、对象键、版本、分片大小和断点记录路径重新调用。Token 过期时重新生成。成功后客户端会清空记录内容。不同上传任务应使用不同的记录文件。
- `$checkCrc` 在表单和 V1 分片中控制 CRC32，在 V2 分片中对应分片 MD5。
- 客户端默认查询 Bucket 所在地域。也可在创建 `Config` 时传入明确的 `Region`。请求超时和连接超时可用 `Config::setTimeout()` 和 `Config::setConnectTimeout()` 设置，单位为秒，作用于当前 PHP 进程。
