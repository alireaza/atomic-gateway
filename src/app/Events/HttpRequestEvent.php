<?php

namespace AliReaza\Atomic\Events;

class HttpRequestEvent
{
    public function __construct(public string $content, public ?array $files = null)
    {
    }
}
