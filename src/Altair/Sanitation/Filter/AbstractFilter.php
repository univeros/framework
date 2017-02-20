<?php
namespace Altair\Sanitation\Filter;

use Altair\Middleware\Contracts\PayloadInterface as MiddlewarePayloadInterface;
use Altair\Sanitation\Contracts\PayloadInterface;
use Altair\Sanitation\Contracts\FilterInterface;

abstract class AbstractFilter implements FilterInterface
{
    /**
     * @inheritdoc
     */
    public function __invoke(MiddlewarePayloadInterface $payload, callable $next): MiddlewarePayloadInterface
    {
        $subject = (object)$payload->getAttribute(PayloadInterface::ATTRIBUTE_SUBJECT);
        $attribute = $payload->getAttribute(PayloadInterface::ATTRIBUTE_KEY);
        $subject->$attribute = $this->parse($subject->$attribute);

        return $next($payload->withAttribute(PayloadInterface::ATTRIBUTE_SUBJECT, $subject));
    }
}
