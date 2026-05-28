<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Support;

use Altair\Http\Contracts\CredentialsExtractorInterface;
use Override;
use Psr\Http\Message\ServerRequestInterface;

class BodyCredentialsExtractor implements CredentialsExtractorInterface
{
    /**
     * BodyCredentialsBuilder constructor.
     */
    public function __construct(private readonly string $identifier = 'username', private readonly string $password = 'password') {}

    /**
     * @inheritDoc
     * @return array<string, mixed>|null
     */
    #[Override]
    public function extract(ServerRequestInterface $request): ?array
    {
        $body = $request->getParsedBody();

        if (!\is_array($body)) {
            return null;
        }

        if (empty($body[$this->identifier]) || empty($body[$this->password])) {
            return null;
        }

        return [$this->identifier => $body[$this->identifier], $this->password => $body[$this->password]];
    }
}
