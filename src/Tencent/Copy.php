<?php

namespace EasySwoole\Oss\Tencent;

class Copy
{
    /**
     * const var: part size from 1MB to 5GB, and max parts of 10000 are allowed for each upload.
     */
    const MIN_PART_SIZE = 1048576;
    const MAX_PART_SIZE = 5368709120;
    const DEFAULT_PART_SIZE = 52428800;
    const MAX_PARTS = 10000;

    /**
     * @var $client OssClient
     */
    private $client;
    private $copySource;
    private $options;
    private $partSize;
    private $size;
    private int $retry;

    public function __construct($client, $source, $options = array())
    {
        $this->partSize = $this->calculatePartSize($options['PartSize'], $source['ContentLength']);
        $this->retry = max(1, (int)($options['Retry'] ?? 5));
        unset($options['PartSize'], $options['Retry'], $options['Concurrency']);
        $this->client = $client;
        $this->copySource = $source['Path'];
        $this->size = (int)$source['ContentLength'];
        $this->options = $options;
    }

    public function copy()
    {
        $uploadId = $this->initiateMultipartUpload();
        $params = [
            'Bucket' => $this->options['Bucket'],
            'Key' => $this->options['Key'],
            'UploadId' => $uploadId,
        ];
        try {
            return $this->client->completeMultipartUpload($params + ['Parts' => $this->uploadParts($uploadId)]);
        } catch (\Throwable $failure) {
            try {
                $this->client->abortMultipartUpload($params);
            } catch (\Throwable) {
                // Preserve the original copy failure.
            }
            throw $failure;
        }
    }

    public function uploadParts($uploadId)
    {
        $parts = [];
        for ($offset = 0, $number = 1; $offset < $this->size; $offset += $this->partSize, ++$number) {
            $end = min($offset + $this->partSize, $this->size) - 1;
            $params = [
                'Bucket' => $this->options['Bucket'],
                'Key' => $this->options['Key'],
                'UploadId' => $uploadId,
                'PartNumber' => $number,
                'CopySource' => $this->copySource,
                'CopySourceRange' => "bytes=$offset-$end",
            ];
            for ($attempt = 1; ; ++$attempt) {
                try {
                    $response = $this->client->uploadPartCopy($params + $this->options);
                    if (empty($response['ETag'])) {
                        throw new \RuntimeException("COS returned no ETag for copied part $number");
                    }
                    $parts[] = ['PartNumber' => $number, 'ETag' => $response['ETag']];
                    break;
                } catch (\Exception $failure) {
                    if ($attempt >= $this->retry) {
                        throw $failure;
                    }
                }
            }
        }
        return $parts;
    }

    private function calculatePartSize($minPartSize, $size)
    {
        $partSize = intval(ceil(($size / self::MAX_PARTS)));
        $partSize = max($minPartSize, $partSize);
        $partSize = min($partSize, self::MAX_PART_SIZE);
        $partSize = max($partSize, self::MIN_PART_SIZE);
        return $partSize;
    }

    private function initiateMultipartUpload()
    {
        $result = $this->client->createMultipartUpload($this->options);
        return $result['UploadId'];
    }

}
