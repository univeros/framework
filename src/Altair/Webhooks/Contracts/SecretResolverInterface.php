<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Webhooks\Contracts;

interface SecretResolverInterface
{
    /**
     * Resolve the named webhook secret, e.g. 'stripe', 'github'. The name is
     * the lookup key that travels through the spec / OpenAPI; the secret value
     * itself never appears there. Implementations throw
     * {@see \Altair\Webhooks\Exception\WebhookException} when the named secret
     * is not configured.
     */
    public function resolve(string $name): string;
}
