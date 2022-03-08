<?php

namespace AliReaza\Atomic\Containers;

use AliReaza\DependencyInjection\DependencyInjectionContainer as DIC;
use AliReaza\EventDriven\Kafka\ListenerProvider as KafkaListenerProvider;
use AliReaza\EventDriven\ListenerProvider;
use Exception;
use RdKafka;

class KafkaListenerProviderContainer
{
    public function __construct(private DIC $container)
    {
    }

    public function __invoke(): ListenerProvider
    {
        $conf = $this->container->make(RdKafka\Conf::class);

        $conf->set('auto.offset.reset', 'earliest');

        $conf->setRebalanceCb(function (RdKafka\KafkaConsumer $consumer, $err, array $partitions = null) {
            switch ($err) {
                case RD_KAFKA_RESP_ERR__ASSIGN_PARTITIONS:
                    $consumer->assign($partitions);
                    break;

                case RD_KAFKA_RESP_ERR__REVOKE_PARTITIONS:
                    $consumer->assign();
                    break;

                default:
                    throw new Exception($err);
            }
        });

        $kafkaListenerProvider = new KafkaListenerProvider($conf);

        $kafkaListenerProvider->setMessageProvider(function (RdKafka\Message $message): RdKafka\Message {
            return match ($message->err) {
                RD_KAFKA_RESP_ERR_NO_ERROR => $message,
                default => throw new Exception($message->errstr(), $message->err),
            };
        });

        $kafkaListenerProvider->setListenerProvider(function (string $event, mixed $listener, RdKafka\Message $message): void {
            $headers = $message->headers;

            if (!is_null($headers) && array_key_exists('correlation_id', $headers)) {
                $content = json_decode($message->payload, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $event_object = $this->container->call($event, $content);

                    $parameters = [$event_object, ...$headers];
                } else {
                    $parameters = [$message->payload, ...$headers];
                }

                $this->container->call($listener, $parameters);
            }
        });

        return new ListenerProvider($kafkaListenerProvider);
    }
}
