<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Validation;

use Altair\Middleware\Contracts\PayloadInterface as MiddlewarePayloadInterface;
use Altair\Middleware\Payload;
use Altair\Validation\Contracts\PayloadInterface;
use Altair\Validation\Contracts\RulesRunnerInterface;
use Altair\Validation\Contracts\ValidatableInterface;
use Altair\Validation\Contracts\ValidatorInterface;

class Validator implements ValidatorInterface
{
    /**
     * @var RulesRunnerInterface
     */
    protected $runner;
    /**
     * @var Payload
     */
    protected $payload;

    /**
     * Validator constructor.
     *
     * @param RulesRunnerInterface $runner
     */
    public function __construct(RulesRunnerInterface $runner)
    {
        $this->runner = $runner;
    }

    /**
     * @param ValidatableInterface $validatable
     *
     * @return bool
     */
    public function validate(ValidatableInterface $validatable): bool
    {
        $this->payload = $this->buildPayload($validatable);

        foreach ($validatable->getRules() as $key => $value) {
            $keys = explode(',', $this->sanitize($key));
            foreach ($keys as $attribute) {
                $rules = is_array($value) ? $value : [$value];
                $runner = $this->runner->withRules($rules);
                $payload = $this->payload->withAttribute(PayloadInterface::ATTRIBUTE_KEY, $attribute);

                $this->payload = call_user_func($runner, $payload);
            }
        }

        return $this->payload->getAttribute(PayloadInterface::ATTRIBUTE_RESULT) === true;
    }

    /**
     * @inheritDoc
     */
    public function getPayload(): ?MiddlewarePayloadInterface
    {
        return $this->payload;
    }

    /**
     * Create a Payload instance with ValidatableInterface as its subject and add the rest of the subject's attributes
     * that are going to be validated. That way we could make use of a LoggingMiddleware class and extract the
     * attributes using "Payload::getAttributes()".
     *
     * @param ValidatableInterface $validatable
     *
     * @return MiddlewarePayloadInterface
     */
    protected function buildPayload(ValidatableInterface $validatable): MiddlewarePayloadInterface
    {
        $attributes = [
            PayloadInterface::ATTRIBUTE_SUBJECT => $validatable
        ];

        foreach ($validatable->getRules()->keys() as $key) {
            $keys = explode(',', $this->sanitize($key));
            foreach ($keys as $attribute) {
                if (isset($attributes[$attribute])) {
                    continue;
                }
                $attributes[$attribute] = $validatable->$attribute;
            }
        }

        return new Payload($attributes);
    }

    /**
     * @param string $value
     *
     * @return string
     */
    protected function sanitize(string $value): string
    {
        return preg_replace('/\s+/', '', $value);
    }
}
