<?php
namespace Altair\Sanitation\Resolver;

use Altair\Container\Container;
use Altair\Container\Definition;
use Altair\Sanitation\Contracts\FilterInterface;
use Altair\Sanitation\Contracts\ResolverInterface;

class FilterResolver implements ResolverInterface
{
    /**
     * @var Container
     */
    protected $container;

    /**
     * RuleResolver constructor.
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * @param mixed $entry
     *
     * @return FilterInterface
     */
    public function __invoke($entry): FilterInterface
    {
        if (is_object($entry)) { // string
            return $entry;
        }
        $arguments = [];
        if (is_array($entry)) { // ['class' => FilterB::class, ':argument1' => 'value1', ':argument2' => 'value2']
            $arguments = array_slice($entry, 1);
            $entry = $entry['class']; // force error if key is not configured
        } // else is a string

        return $this->container->make($entry, new Definition($arguments));
    }
}
