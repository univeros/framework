<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Sanitation;

use Altair\Middleware\Contracts\PayloadInterface as MiddlewarePayloadInterface;
use Altair\Middleware\Payload;
use Altair\Sanitation\Contracts\FiltersRunnerInterface;
use Altair\Sanitation\Contracts\PayloadInterface;
use Altair\Sanitation\Contracts\SanitizableInterface;
use Altair\Sanitation\Contracts\SanitizerInterface;

class Sanitizer implements SanitizerInterface
{
    /**
     * @var FiltersRunnerInterface
     */
    protected $runner;
    /**
     * @var Payload
     */
    protected $payload;

    /**
     * Validator constructor.
     *
     * @param FiltersRunnerInterface $runner
     */
    public function __construct(FiltersRunnerInterface $runner)
    {
        $this->runner = $runner;
    }

    /**
     * @inheritdoc
     */
    public function sanitize(SanitizableInterface $sanitizable): SanitizableInterface
    {
        $this->payload = $this->buildPayload($sanitizable);

        foreach ($sanitizable->getFilters() as $key => $value) {
            $keys = explode(',', preg_replace('/\s+/', '', $key));
            foreach ($keys as $attribute) {
                $filters = is_array($value) ? $value : [$value];
                $runner = $this->runner->withFilters($filters);
                $payload = $this->payload->withAttribute(PayloadInterface::ATTRIBUTE_KEY, $attribute);

                $this->payload = call_user_func($runner, $payload);
            }
        }

        return $this->payload->getAttribute(PayloadInterface::ATTRIBUTE_SUBJECT);
    }

    /**
     * @return MiddlewarePayloadInterface|null
     */
    public function getPayload(): ?MiddlewarePayloadInterface
    {
        return $this->payload;
    }

    /**
     * Create a Payload instance with SanitizableInterface as its subject and add the rest of the subject's attributes
     * that are going to be filtered. That way we could make use of a LoggingMiddleware class and extract the attributes
     * using "Payload::getAttributes()".
     *
     * @param SanitizableInterface $sanitizable
     *
     * @return MiddlewarePayloadInterface
     */
    protected function buildPayload(SanitizableInterface $sanitizable): MiddlewarePayloadInterface
    {
        $attributes = [
            PayloadInterface::ATTRIBUTE_SUBJECT => clone $sanitizable // immutability
        ];

        foreach ($sanitizable->getFilters()->keys() as $key) {
            $keys = explode(',', preg_replace('/\s+/', '', $key));
            foreach ($keys as $attribute) {
                if (isset($attributes[$attribute])) {
                    continue;
                }
                $attributes[$attribute] = $sanitizable->$attribute;
            }
        }

        return new Payload($attributes);
    }
}
