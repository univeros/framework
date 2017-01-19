<?php
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
