<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Webhooks\Signing;

use Altair\Webhooks\Contracts\SecretResolverInterface;
use Altair\Webhooks\Exception\WebhookException;

/**
 * Default resolver: reads WEBHOOK_SECRET_<NAME> from the environment, where
 * <NAME> is the upper-cased secret name with non-alphanumerics folded to '_'.
 * Hosts needing KMS / vault integration implement SecretResolverInterface
 * themselves; this is the zero-config default.
 */
final readonly class EnvSecretResolver implements SecretResolverInterface
{
    public function __construct(
        private string $prefix = 'WEBHOOK_SECRET_',
    ) {}

    public function resolve(string $name): string
    {
        $envKey = $this->prefix . $this->normalise($name);
        $value = getenv($envKey);

        if ($value === false || $value === '') {
            throw WebhookException::missingSecret($name);
        }

        return $value;
    }

    private function normalise(string $name): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]+/', '_', $name));
    }
}
