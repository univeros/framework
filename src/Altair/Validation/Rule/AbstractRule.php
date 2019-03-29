<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Validation\Rule;

use Altair\Middleware\Contracts\PayloadInterface as MiddlewarePayloadInterface;
use Altair\Validation\Contracts\PayloadInterface;
use Altair\Validation\Contracts\RuleInterface;

abstract class AbstractRule implements RuleInterface
{
    /**
     * @inheritdoc
     */
    public function __invoke(MiddlewarePayloadInterface $payload, callable $next): MiddlewarePayloadInterface
    {
        $subject = (object)$payload->getAttribute(PayloadInterface::ATTRIBUTE_SUBJECT);
        $attribute = $payload->getAttribute(PayloadInterface::ATTRIBUTE_KEY);

        if ($this->assert($subject->$attribute)) {
            $result = $payload->getAttribute(PayloadInterface::ATTRIBUTE_RESULT, true) && true;

            return $next($payload->withAttribute(PayloadInterface::ATTRIBUTE_RESULT, $result));
        }

        $failures = $payload->getAttribute(PayloadInterface::ATTRIBUTE_FAILURES, []);

        $value = is_array($subject->$attribute) ? gettype($subject->$attribute) : $subject->$attribute;
        $failures[$attribute] = $this->buildErrorMessage($value);

        return $next(
            $payload
                ->withAttribute(PayloadInterface::ATTRIBUTE_FAILURES, $failures)
                ->withAttribute(PayloadInterface::ATTRIBUTE_RESULT, false)
        );
    }

    abstract protected function buildErrorMessage($value): string;
}
