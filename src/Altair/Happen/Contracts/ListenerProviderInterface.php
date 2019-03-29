<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Happen;

interface ListenerProviderInterface
{
    /**
     * Provide event
     *
     * @param EventDispatcherInterface $listenerAcceptor
     *
     * @return ListenerProviderInterface
     */
    public function provideListeners(EventDispatcherInterface $listenerAcceptor): ListenerProviderInterface;
}
