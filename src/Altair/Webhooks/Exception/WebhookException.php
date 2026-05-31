<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Webhooks\Exception;

use RuntimeException;

class WebhookException extends RuntimeException
{
    public static function unknownSigner(string $name): self
    {
        return new self(\sprintf('Unknown webhook signer "%s".', $name));
    }

    public static function signerUnavailable(string $name, string $reason): self
    {
        return new self(\sprintf('Webhook signer "%s" is not available: %s', $name, $reason));
    }

    public static function missingSecret(string $name): self
    {
        return new self(\sprintf('Webhook secret "%s" is not configured.', $name));
    }

    public static function unknownDelivery(string $deliveryId): self
    {
        return new self(\sprintf('Webhook delivery "%s" was not found.', $deliveryId));
    }
}
