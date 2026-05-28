<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Formatter;

use Altair\Http\Contracts\OutputFormatterInterface;
use Altair\Http\Contracts\PayloadInterface;
use Override;

class JsonFormatter implements OutputFormatterInterface
{
    protected int $options = 0;

    /**
     * @var int<1, max>
     */
    protected int $depth = 512;

    /**
     * @inheritDoc
     * @return list<string>
     */
    #[Override]
    public static function accepts(): array
    {
        return ['application/json'];
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function type(): string
    {
        return 'application/json';
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function body(PayloadInterface $payload): string
    {
        return json_encode($payload->getOutput(), $this->options | JSON_THROW_ON_ERROR, $this->depth);
    }
}
