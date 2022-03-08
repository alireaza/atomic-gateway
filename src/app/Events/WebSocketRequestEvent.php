<?php

namespace AliReaza\Atomic\Events;

class WebSocketRequestEvent
{
    public function __construct(public string $content, public int $fd, public string $sec)
    {
    }
}
