<?php

namespace AliReaza\Atomic\Containers;

use AliReaza\DotEnv\DotEnv;
use AliReaza\DotEnv\DotEnvBuilder;

class DotEnvContainer
{
    public function __invoke(): DotEnv
    {
        $env = DotEnvBuilder::getInstance();

        if (file_exists($file = path('.env'))) {
            $env->load($file);
        }

        return $env;
    }
}
