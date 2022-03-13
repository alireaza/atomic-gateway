<?php

namespace AliReaza\Atomic\Containers;

use Predis;

class PredisClientContainer
{
    public function __invoke(): Predis\Client
    {
        return new Predis\Client(env('REDIS_SERVERS', 'tcp://redis:6379'));
    }
}
