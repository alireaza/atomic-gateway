<?php

namespace AliReaza\Atomic\Containers;

use AliReaza\DependencyInjection\DependencyInjectionContainer as DIC;
use AliReaza\EventDriven\EventDispatcher;
use AliReaza\EventDriven\Kafka\EventDispatcher as KafkaEventDispatcher;
use Exception;
use RdKafka;

class KafkaEventDispatcherContainer
{
    public function __construct(private DIC $container)
    {
    }

    public function __invoke(): EventDispatcher
    {
        $conf = $this->container->make(RdKafka\Conf::class);

        $conf->setDrMsgCb(function (RdKafka $kafka, RdKafka\Message $message) {
            if ($message->err) {
                throw new Exception(sprintf("Kafka error: %s", rd_kafka_err2str($message->err)));
            }
        });

        $kafkaEventDispatcher = new KafkaEventDispatcher($conf);

        $kafkaEventDispatcher->setMessageProvider(function (object $event): string {
            return json_encode($event);
        });

        return new EventDispatcher($kafkaEventDispatcher);
    }
}
