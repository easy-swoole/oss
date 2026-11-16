<?php

namespace EasySwoole\Oss\QiNiu;

use EasySwoole\Oss\QiNiu\Http\Error;

final class RegionQueryException extends \RuntimeException
{
    public function __construct(private Error $error)
    {
        parent::__construct($error->message() ?: 'Could not resolve Qiniu region', (int)$error->code());
    }

    public function getError(): Error
    {
        return $this->error;
    }
}
