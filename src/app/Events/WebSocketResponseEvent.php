<?php

namespace AliReaza\Atomic\Events;

class WebSocketResponseEvent
{
    public function __construct(public string $content, public int $status_code, public int $fd, public string $sec)
    {
    }
}
