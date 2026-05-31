<?php

declare(strict_types=1);

namespace Altair\Tests\Webhooks\Fixtures;

use Altair\Webhooks\Contracts\SecretResolverInterface;
use Override;

final readonly class StaticSecretResolver implements SecretResolverInterface
{
    public function __construct(
        private string $secret = 'whsec_test',
    ) {
    }

    #[Override]
    public function resolve(string $name): string
    {
        return $this->secret;
    }
}
