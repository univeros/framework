<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Formatter;

use Altair\Http\Contracts\OutputFormatterInterface;
use Altair\Http\Contracts\PayloadInterface;

class JsonFormatter implements OutputFormatterInterface
{
    /**
     * @var int
     */
    protected $options = 0;

    /**
     * @var int
     */
    protected $depth = 512;

    /**
     * @inheritDoc
     */
    #[\Override]
    public static function accepts(): array
    {
        return ['application/json'];
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function type(): string
    {
        return 'application/json';
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function body(PayloadInterface $payload): string
    {
        return json_encode($payload->getOutput(), $this->options, $this->depth);
    }
}
