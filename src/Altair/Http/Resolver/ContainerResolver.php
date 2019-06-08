<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Resolver;

use Altair\Container\Container;
use Relay\ResolverInterface;

class ContainerResolver implements ResolverInterface
{
    /**
     * @var Container
     */
    protected $container;

    /**
     * ContainerResolver constructor.
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * @inheritDoc
     */
    public function __invoke($entry)
    {
        return is_object($entry) ? $entry : $this->container->make($entry);
    }
}
