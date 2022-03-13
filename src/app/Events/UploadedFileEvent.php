<?php

namespace AliReaza\Atomic\Events;

class UploadedFileEvent
{
    public function __construct(public string $name, public string $mime, public string $hash)
    {
    }
}
