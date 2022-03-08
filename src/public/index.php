<?php

require_once __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

use AliReaza\DependencyInjection\DependencyInjectionContainer as DIC;
use AliReaza\DependencyInjection\DependencyInjectionContainerBuilder as DICBuilder;
use AliReaza\Atomic\Application;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

return (new class(DICBuilder::getInstance()) {
    public function __construct(private DIC $container)
    {
        $this->container->set(Request::class, fn(): Request => Request::createFromGlobals());
        $this->container->set(JsonResponse::class, JsonResponse::class);
    }

    public function __invoke(): JsonResponse
    {
        return $this->container->call([Application::class, 'handle']);
    }
})();
