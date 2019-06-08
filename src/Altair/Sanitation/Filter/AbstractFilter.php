<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Sanitation\Filter;

use Altair\Middleware\Contracts\PayloadInterface as MiddlewarePayloadInterface;
use Altair\Sanitation\Contracts\FilterInterface;
use Altair\Sanitation\Contracts\PayloadInterface;

abstract class AbstractFilter implements FilterInterface
{
    /**
     * @inheritDoc
     */
    public function __invoke(MiddlewarePayloadInterface $payload, callable $next): MiddlewarePayloadInterface
    {
        $subject = (object)$payload->getAttribute(PayloadInterface::ATTRIBUTE_SUBJECT);
        $attribute = $payload->getAttribute(PayloadInterface::ATTRIBUTE_KEY);
        $subject->$attribute = $this->parse($subject->$attribute);

        return $next($payload->withAttribute(PayloadInterface::ATTRIBUTE_SUBJECT, $subject));
    }
}
