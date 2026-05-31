<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Webhooks\Exception;

final class SignatureVerificationException extends WebhookException
{
    public static function failed(): self
    {
        return new self('Webhook signature verification failed.');
    }
}
