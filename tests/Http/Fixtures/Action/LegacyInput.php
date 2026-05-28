<?php

declare(strict_types=1);

namespace Altair\Tests\Http\Fixtures\Action;

use Altair\Http\Collection\InputCollection;
use Altair\Http\Contracts\InputInterface;
use Override;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The legacy request-bag input shape: implements InputInterface and returns an
 * InputCollection. Proves the pre-existing execution path still works.
 */
final readonly class LegacyInput implements InputInterface
{
    public function __construct(private InputCollection $collection)
    {
    }

    #[Override]
    public function __invoke(ServerRequestInterface $request): InputCollection
    {
        $this->collection->put('echo', 'legacy');

        return $this->collection;
    }
}
