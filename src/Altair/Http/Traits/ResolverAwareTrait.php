<?php
namespace Altair\Http\Traits;

trait ResolverAwareTrait
{
    /**
     * @var \Relay\ResolverInterface
     */
    protected $resolver;

    /**
     * Resolve a class spec into an object.
     *
     * @param string $spec Fully-qualified class name
     *
     * @return object
     */
    protected function resolve(string $spec)
    {
        return call_user_func($this->resolver, $spec);
    }
}
