<?php

namespace AliReaza\Atomic\Events;

class HttpResponseEvent
{
    public function __construct(public string $content, public int $status_code)
    {
    }
}
