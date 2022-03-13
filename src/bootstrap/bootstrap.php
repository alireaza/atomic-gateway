<?php

use AliReaza\Atomic\Containers\DotEnvContainer;
use AliReaza\Atomic\Containers\ErrorHandlerContainer;
use AliReaza\Atomic\Containers\KafkaConfContainer;
use AliReaza\Atomic\Containers\KafkaEventDispatcherContainer;
use AliReaza\Atomic\Containers\KafkaListenerProviderContainer;
use AliReaza\Atomic\Containers\PredisClientContainer;
use AliReaza\DependencyInjection\DependencyInjectionContainer as DIC;
use AliReaza\DependencyInjection\DependencyInjectionContainerBuilder as DICBuilder;
use AliReaza\DotEnv\DotEnv;
use AliReaza\ErrorHandler\ErrorHandler;
use AliReaza\EventDriven\EventDispatcher;
use AliReaza\EventDriven\Kafka\EventDispatcher as KafkaEventDispatcher;
use AliReaza\EventDriven\Kafka\ListenerProvider as KafkaListenerProvider;
use AliReaza\EventDriven\ListenerProvider;

return (new class(DICBuilder::getInstance()) {
    private ErrorHandler $error_handler;

    public function __construct(private DIC $container)
    {
        $this->container->set(ErrorHandler::class, fn(): ErrorHandler => (new ErrorHandlerContainer())());
        $this->error_handler = $this->container->resolve(ErrorHandler::class);

        $this->container->set(DotEnv::class, fn(): DotEnv => (new DotEnvContainer())());
        $this->container->resolve(DotEnv::class);

        $this->container->set(RdKafka\Conf::class, fn(): RdKafka\Conf => (new KafkaConfContainer())());

        $this->container->set(KafkaEventDispatcher::class, fn(DIC $container): EventDispatcher => (new KafkaEventDispatcherContainer($container))());
        $this->container->set(EventDispatcher::class, fn(DIC $container): EventDispatcher => $container->resolve(KafkaEventDispatcher::class));

        $this->container->set(KafkaListenerProvider::class, fn(DIC $container): ListenerProvider => (new KafkaListenerProviderContainer($container))());
        $this->container->set(ListenerProvider::class, fn(DIC $container): ListenerProvider => $container->resolve(KafkaListenerProvider::class));

        $this->container->set(Predis\Client::class, fn(): Predis\Client => (new PredisClientContainer())());
    }

    public function __invoke(): void
    {
        $this->error_handler->setDebug(env('APP_DEBUG', false));
        $this->error_handler->addTrace(env('APP_DEBUG_ADD_TRACE', false));
    }
})();
