<?php
namespace Altair\Container\Contracts;


interface ReflectionCacheInterface
{
    const CLASSES_KEY_PREFIX = 'class.';
    const CONSTRUCTORS_KEY_PREFIX = 'const.';
    const CONSTRUCTOR_PARAMETERS_KEY_PREFIX = 'const-params.';
    const FUNCTIONS_KEY_PREFIX = 'func.';
    const FUNCTION_PARAMETERS_KEY_PREFIX = 'func-params.';
    const METHODS_KEY_PREFIX = 'method.';

    public function get(string $key);

    public function put(string $key, $data);
}
