<?php

declare(strict_types=1);

namespace Altair\Tests\Http\Fixtures\Action;

use Altair\Http\Base\Payload;
use Altair\Http\Collection\InputCollection;
use Altair\Http\Contracts\DomainInterface;
use Altair\Http\Contracts\PayloadInterface;
use Override;

/**
 * The legacy domain shape: implements DomainInterface and receives an
 * InputCollection.
 */
final class LegacyDomain implements DomainInterface
{
    #[Override]
    public function __invoke(InputCollection $input): PayloadInterface
    {
        return (new Payload())
            ->withStatus(200)
            ->withOutput(['echo' => $input->get('echo')]);
    }
}
