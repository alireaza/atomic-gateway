<?php

namespace AliReaza\Atomic\Commands;

use AliReaza\Atomic\Events\HttpResponseEvent;
use RdKafka;

class HttpResponseEarliestListenerCommand extends AbstractListenerCommand
{
    public function __invoke(): void
    {
        if (property_exists($this->listener->provider, 'conf') && $this->listener->provider->conf instanceof RdKafka\Conf) {
            $this->listener->provider->conf->set('auto.offset.reset', 'earliest');
        }

        $this->listenTo(HttpResponseEvent::class, function (HttpResponseEvent $response, string $event_id, string $correlation_id): void {
            if ($this->redis_client->exists($correlation_id)) {
                $this->redis_client->set($correlation_id, json_encode($response));
                $this->redis_client->expire($correlation_id, env('REDIS_EXPIRE_SEC', 10 * 60));
            }
        });

        $this->listener->subscribe();
    }
}
