<?php

namespace EasySwoole\Oss\QiNiu\Storage;

final class UploadProgress
{
    private $record = null;
    private string $fingerprint;
    public array $state = [];

    public function __construct(?string $path, $stream, array $identity)
    {
        if ($path === null) {
            return;
        }
        $this->record = fopen($path, 'c+b');
        if ($this->record === false) {
            throw new \RuntimeException('Could not open the Qiniu upload resume record');
        }
        $input = fstat($stream);
        $recordFile = fstat($this->record);
        if ($recordFile['dev'] === $input['dev'] && $recordFile['ino'] === $input['ino']) {
            throw new \InvalidArgumentException('Upload source and resume record must be different files');
        }
        if (!flock($this->record, LOCK_EX | LOCK_NB)) {
            throw new \RuntimeException('Qiniu upload resume record is already in use');
        }
        $hash = hash_init('sha256');
        rewind($stream);
        hash_update_stream($hash, $stream);
        rewind($stream);
        $this->fingerprint = hash('sha256', serialize([$identity, hash_final($hash)]));
        $record = json_decode(stream_get_contents($this->record), true);
        if (($record['fingerprint'] ?? null) === $this->fingerprint && is_array($record['state'] ?? null)) {
            $this->state = $record['state'];
        }
    }

    public function save(array $state): void
    {
        $this->state = $state;
        if ($this->record === null) {
            return;
        }
        $data = $state === [] ? '' : json_encode([
            'fingerprint' => $this->fingerprint,
            'state' => $state,
        ], JSON_THROW_ON_ERROR);
        rewind($this->record);
        if (fwrite($this->record, $data) !== strlen($data)
            || !ftruncate($this->record, strlen($data)) || !fflush($this->record)) {
            throw new \RuntimeException('Could not persist the Qiniu upload resume record');
        }
    }

    public function __destruct()
    {
        if (is_resource($this->record)) {
            fclose($this->record);
        }
    }
}
