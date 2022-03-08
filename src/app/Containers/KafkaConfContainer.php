<?php

namespace AliReaza\Atomic\Containers;

use Exception;
use RdKafka;

class KafkaConfContainer
{
    public function __invoke(): RdKafka\Conf
    {
        $kafkaConf = new RdKafka\Conf();

        $kafkaConf->set('bootstrap.servers', env('KAFKA_SERVERS', 'kafka:9092'));

        $kafkaConf->setErrorCb(function (RdKafka $kafka, int $err, string $reason) {
            throw new Exception(sprintf("Kafka error: %s (reason: %s)\n", rd_kafka_err2str($err), $reason));
        });

        return $kafkaConf;
    }
}
