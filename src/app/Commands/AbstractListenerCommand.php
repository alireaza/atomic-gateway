<?php

namespace AliReaza\Atomic\Commands;

use AliReaza\EventDriven\EventDispatcher;
use AliReaza\EventDriven\ListenerProvider;
use Predis;
use RdKafka;

abstract class AbstractListenerCommand
{
    public function __construct(public EventDispatcher $dispatcher, public ListenerProvider $listener, public Predis\Client $redis_client)
    {
    }

    protected function listenTo(string $event_class, callable $callback): void
    {
        if (property_exists($this->listener->provider, 'conf') && $this->listener->provider->conf instanceof RdKafka\Conf) {
            $this->listener->provider->conf->set('group.id', 'Gateway-Http-' . date("YmdHis") . time() . rand(1111, 9999));
        }

        $this->listener->addListener($event_class, function (object $object, string $event_id, string $correlation_id) use ($callback): void {
            $callback($object, $event_id, $correlation_id);
        });
    }
}
