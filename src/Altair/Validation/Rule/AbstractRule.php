<?php
namespace Altair\Validation\Rule;

use Altair\Middleware\Contracts\PayloadInterface;
use Altair\Validation\Contracts\PayloadInterface as ValidationPayloadInterface;
use Altair\Validation\Contracts\RuleInterface;

abstract class AbstractRule implements RuleInterface
{
    /**
     * @inheritdoc
     */
    public function __invoke(PayloadInterface $payload, callable $next): PayloadInterface
    {
        $subject = (object)$payload->getAttribute(ValidationPayloadInterface::SUBJECT_KEY);
        $attribute = $payload->getAttribute(ValidationPayloadInterface::ATTRIBUTE_KEY);

        if ($this->assert($subject->$attribute)) {
            $result = $payload->getAttribute(ValidationPayloadInterface::RESULT_KEY, true) && true;

            return $next($payload->withAttribute(ValidationPayloadInterface::RESULT_KEY, $result));
        }

        $failures = $payload->getAttribute(ValidationPayloadInterface::FAILURES_KEY, []);

        $value = is_array($subject->$attribute) ? gettype($subject->$attribute) : $subject->$attribute;
        $failures[$attribute] = $this->buildErrorMessage($value);

        return $next(
            $payload
                ->withAttribute(ValidationPayloadInterface::FAILURES_KEY, $failures)
                ->withAttribute(ValidationPayloadInterface::RESULT_KEY, false)
        );
    }

    abstract protected function buildErrorMessage($value): string;
}
