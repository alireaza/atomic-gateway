<?php

namespace AliReaza\Atomic\Containers;

use AliReaza\ErrorHandler\ErrorHandler;
use AliReaza\ErrorHandler\Render\JsonResponse as RenderErrorHandler;

class ErrorHandlerContainer
{
    public function __invoke(): ErrorHandler
    {
        $error_handler = new ErrorHandler();
        $error_handler->register(true, false);
        $error_handler->setRender(RenderErrorHandler::class);

        return $error_handler;
    }
}
