<?php

use AliReaza\DependencyInjection\DependencyInjectionContainerBuilder as DICBuilder;

if (!function_exists('path')) {
    function path(?string $path = null): string
    {
        $container = DICBuilder::getInstance();

        if (!$container->has('app.path')) {
            $container->set('app.path', realpath(dirname(__FILE__)));
        }

        $app_path = $container->resolve('app.path');

        return $app_path . array_reduce(explode(DIRECTORY_SEPARATOR, ($path ? DIRECTORY_SEPARATOR . $path : '')), function ($carry, $item) {
                if ($item === '' || $item === '.') {
                    return $carry;
                }

                if ($item === '..') {
                    return dirname($carry);
                }

                return preg_replace("/\/+/", "/", $carry . DIRECTORY_SEPARATOR . $item);
            }, DIRECTORY_SEPARATOR);
    }
}

if (!function_exists('require_once_if_exists')) {
    function require_once_if_exists(string $path, array $data = []): mixed
    {
        if (is_file($path)) {
            return (static function () use ($path, $data) {
                extract($data, EXTR_SKIP);

                return require_once $path;
            })();
        }

        throw new Exception("File does not exist at path $path.");
    }
}
