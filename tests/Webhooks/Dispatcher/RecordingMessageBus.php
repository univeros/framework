<?php

declare(strict_types=1);

namespace Altair\Tests\Webhooks\Dispatcher;

use Altair\Messaging\Contracts\MessageBusInterface;
use Altair\Webhooks\Dispatcher\WebhookMessage;
use Override;
use Symfony\Component\Messenger\Envelope;

/**
 * Test bus that records every dispatched message instead of routing it.
 */
final class RecordingMessageBus implements MessageBusInterface
{
    /** @var list<object> */
    public array $dispatched = [];

    #[Override]
    public function dispatch(object $message, array $stamps = []): Envelope
    {
        $this->dispatched[] = $message;

        return new Envelope($message, $stamps);
    }

    public function lastWebhookMessage(): ?WebhookMessage
    {
        for ($i = count($this->dispatched) - 1; $i >= 0; --$i) {
            $message = $this->dispatched[$i];
            if ($message instanceof WebhookMessage) {
                return $message;
            }
        }

        return null;
    }
}
