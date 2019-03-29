<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Http\Middleware;

use Altair\Http\Contracts\MiddlewareInterface;
use Altair\Http\Exception\HttpBadRequestException;
use Relay\Middleware\JsonContentHandler;

class JsonContentMiddleware extends JsonContentHandler implements MiddlewareInterface
{
    /**
     * @inheritDoc
     */
    public function __construct($assoc = true, $maxDepth = 512, $options = 0)
    {
        return parent::__construct($assoc, $maxDepth, $options);
    }

    /**
     * @inheritDoc
     */
    protected function throwException($message)
    {
        throw new HttpBadRequestException($message);
    }
}
